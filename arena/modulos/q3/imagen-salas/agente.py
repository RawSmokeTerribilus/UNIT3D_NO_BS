#!/usr/bin/env python3
"""Arena NOBS — agente de salas de Quake 3 (ioquake3 + OpenArena).

Mismo contrato de API que el agente de Warzone 2100 (el panel de staff y el lobby no distinguen):
cada sala es un proceso `ioq3ded` en su puerto UDP (27960, 27961…) escuchando solo en 127.0.0.1;
el relé común (/opt/arena/relay.py, modo /dg) traduce WebSocket ↔ UDP con la IP de loopback de
cada socio, que el agente cruza con el `status` del servidor para saber quién es quién.

  - Sin «lobby»: una sala de Q3 está siempre en partida y admite entrar mientras haya hueco.
  - Bots: bot_minplayers del preset; si no queda ningún humano, se retiran (CPU cero) y vuelven
    cuando entra alguien.
  - Expulsar: addip <IP de loopback> + clientkick, y veto en el relé hasta readmitir (removeip).
  - Silenciar: Q3 no tiene silencio por jugador → la API lo rechaza con un mensaje claro.

Nunca toca el socket de Docker. Solo biblioteca estándar.

Variables de entorno: ARENA_CONTROL_TOKEN, ARENA_MAX_SALAS (8), ARENA_SALAS_INICIALES
("todos-contra-todos"), ARENA_PUERTO_BASE (27960), ARENA_DATOS (/datos), ARENA_SALA_SOCIO_CADUCA_SEG
(900), ARENA_BOTS_DUERMEN_SEG (60).
"""

import hmac
import json
import os
import re
import shutil
import signal
import subprocess
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

Q3_BIN = os.environ.get('Q3_BIN', '/opt/q3/ioq3ded')
Q3_BASE = os.environ.get('Q3_BASE', '/opt/q3')          # fs_basepath (lleva baseoa/)
DATOS = os.environ.get('ARENA_DATOS', '/datos')
PRESETS_SEMILLA = '/opt/arena/presets'
TOKEN = os.environ.get('ARENA_CONTROL_TOKEN', '')
MAX_SALAS = int(os.environ.get('ARENA_MAX_SALAS', '8'))
PUERTO_BASE = int(os.environ.get('ARENA_PUERTO_BASE', '27960'))
SALAS_INICIALES = [p for p in os.environ.get('ARENA_SALAS_INICIALES', 'todos-contra-todos').split(',') if p.strip()]
SALA_SOCIO_CADUCA_SEG = int(os.environ.get('ARENA_SALA_SOCIO_CADUCA_SEG', '900'))
BOTS_DUERMEN_SEG = int(os.environ.get('ARENA_BOTS_DUERMEN_SEG', '60'))
PUERTO_WS = 8080
PUERTO_API = 8081

DIR_PRESETS = os.path.join(DATOS, 'presets')
DIR_SALAS = os.path.join(DATOS, 'salas')
DIR_CONTROL = os.path.join(DATOS, 'control')
FICHERO_ESTADO = os.path.join(DIR_CONTROL, 'estado.json')
FICHERO_ACCIONES = os.path.join(DIR_CONTROL, 'acciones.jsonl')
FICHERO_TOKENS = '/tmp/arena/tokens'
FICHERO_FASES = '/tmp/arena/fases'
FICHERO_SOCIOS = '/tmp/arena/socios.json'
FICHERO_VETOS = '/tmp/arena/vetos'

ID_SALA = re.compile(r'^sala([1-9][0-9]?)$')
NOMBRE_PRESET = re.compile(r'^[a-z0-9][a-z0-9-]{0,40}$')
MAPA = re.compile(r'^[a-z0-9_]{1,40}$')
# status de ioq3: «cl score ping name ^7address rate»; los bots tienen address «bot»
RE_STATUS = re.compile(r'^\s*(\d+)\s+(-?\d+)\s+(\d+|CON|ZMB)\s+(.*?)\s*\^7(\S+)\s+(\d+)\s*$')
RE_COLOR = re.compile(r'\^.')
MODOS = {'ffa': 0, 'duelo': 1, 'tdm': 3, 'ctf': 4}
NOMBRES_MODO = {'ffa': 'Todos contra todos', 'duelo': 'Duelo', 'tdm': 'Por equipos', 'ctf': 'Captura la bandera'}
TAM_MAX_LOG = 5 * 1024 * 1024
TICKS = os.sysconf('SC_CLK_TCK')

cerrojo = threading.RLock()
salas = {}
parando = False


def log(msg):
    print(time.strftime('%Y-%m-%dT%H:%M:%S'), msg, flush=True)


def escribir_atomico(ruta, contenido):
    tmp = ruta + '.tmp'
    with open(tmp, 'w', encoding='utf-8') as f:
        f.write(contenido)
    os.replace(tmp, ruta)


def auditar(actor, accion, detalle=None):
    linea = {'cuando': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()), 'actor': actor, 'accion': accion}
    if detalle:
        linea['detalle'] = detalle
    with open(FICHERO_ACCIONES, 'a', encoding='utf-8') as f:
        f.write(json.dumps(linea, ensure_ascii=False) + '\n')
    log(f'acción: {actor} {accion} {detalle or ""}')


def _texto(valor, maximo):
    """Texto de usuario para la consola de Q3: sin comillas, punto y coma ni saltos (romperían el comando)."""
    limpio = re.sub(r'[\x00-\x1f\x7f";\\]', '', str(valor or '')).strip()
    return limpio[:maximo]


def _entero(datos, clave, minimo, maximo, defecto):
    try:
        n = int(datos.get(clave, defecto))
    except (TypeError, ValueError):
        n = defecto
    return max(minimo, min(maximo, n))


def validar(config):
    """Preset de Q3 → config limpia (todo lo que llega a la línea de comandos del servidor pasa por aquí)."""
    modo = str(config.get('modo', 'ffa'))
    if modo not in MODOS:
        raise ValueError('modo no válido (ffa, duelo, tdm, ctf)')
    mapa = str(config.get('mapa', ''))
    if not MAPA.match(mapa):
        raise ValueError('mapa no válido')
    humanos = 2 if modo == 'duelo' else _entero(config, 'humanos', 2, 12, 8)
    return {
        'nombre': _texto(config.get('nombre'), 60) or NOMBRES_MODO[modo],
        'mapa': mapa,
        'modo': modo,
        'humanos': humanos,
        'bots': 0 if modo == 'duelo' else _entero(config, 'bots', 0, 8, 4),
        'nivel_bots': _entero(config, 'nivel_bots', 1, 5, 2),
        'limite_frags': _entero(config, 'limite_frags', 0, 200, 20),
        'limite_tiempo': _entero(config, 'limite_tiempo', 0, 60, 15),
        'limite_capturas': _entero(config, 'limite_capturas', 0, 50, 8),
        'contrasena': _texto(config.get('contrasena'), 30),
    }


def leer_preset(nombre):
    if not NOMBRE_PRESET.match(nombre or ''):
        raise ValueError('nombre de preset no válido')
    ruta = os.path.join(DIR_PRESETS, nombre + '.json')
    if not os.path.isfile(ruta):
        raise ValueError('no existe el preset ' + nombre)
    with open(ruta, encoding='utf-8') as f:
        return validar(json.load(f))


def resumen(config):
    return {'nombre': config['nombre'], 'mapa': config['mapa'], 'modo': NOMBRES_MODO[config['modo']],
            'humanos': config['humanos'], 'ias': config['bots'], 'huecos': config['humanos']}


def ip_de_socio(socio_id):
    """La misma IP de loopback que asigna el relé (relay.ip_de_socio) a un id de socio."""
    n = int(socio_id)
    return f'127.{10 + (n >> 16) % 240}.{(n >> 8) & 255}.{n & 255}'


class Sala:
    def __init__(self, sala_id, config, origen):
        self.id = sala_id
        self.config = config
        self.preset = origen
        self.puerto = PUERTO_BASE + int(ID_SALA.match(sala_id).group(1)) - 1
        self.dir = os.path.join(DIR_SALAS, sala_id)
        self.proc = None
        self.arranques = 0
        self.ultimo_arranque = 0.0
        self.espera = 2.0
        self.fase = 'parada'
        self.jugadores = 0
        self.nombres = []
        self.detalle = []
        self.cpu = 0.0
        self.rss_mb = 0.0
        self._muestra = None
        self.borrada = False
        self.dueno = None
        self.vetos = set()
        self._anunciados = set()
        self._creada = time.time()
        self._sin_humanos_desde = time.time()
        self._bots_dormidos = False
        self._status = None         # líneas del status en curso (None = no se está leyendo uno)

    @property
    def nombre(self):
        return self.config['nombre']

    @property
    def huecos(self):
        return self.config['humanos']

    def renombrar(self, nombre):
        self.config['nombre'] = nombre

    def arrancar(self):
        os.makedirs(self.dir, exist_ok=True)
        c = self.config
        args = [Q3_BIN,
                '+set', 'fs_basepath', Q3_BASE, '+set', 'fs_homepath', self.dir, '+set', 'com_basegame', 'baseoa',
                '+set', 'dedicated', '1', '+set', 'net_ip', '127.0.0.1', '+set', 'net_port', str(self.puerto),
                '+set', 'net_enabled', '1', '+set', 'sv_pure', '0', '+set', 'sv_allowDownload', '0',
                '+set', 'sv_maxclients', str(min(16, c['humanos'] + c['bots'])),
                '+set', 'sv_hostname', c['nombre'], '+set', 'g_gametype', str(MODOS[c['modo']]),
                '+set', 'fraglimit', str(c['limite_frags']), '+set', 'timelimit', str(c['limite_tiempo']),
                '+set', 'capturelimit', str(c['limite_capturas']), '+set', 'g_spSkill', str(c['nivel_bots']),
                '+set', 'bot_minplayers', '0', '+set', 'g_filterBan', '1', '+set', 'sv_floodProtect', '1',
                '+set', 'g_password', c['contrasena'], '+set', 'nextmap', 'map_restart 0',
                '+set', 'com_hunkmegs', '64', '+map', c['mapa']]
        # Los vetos los aplica el relé (no deja conectar); addip/removeip (g_banIPs, archivado) es el refuerzo
        self.proc = subprocess.Popen(args, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                     cwd=self.dir, start_new_session=True)
        self.arranques += 1
        self.ultimo_arranque = time.time()
        self.fase = 'partida'
        self.jugadores = 0
        self.nombres = []
        self.detalle = []
        self._muestra = None
        self._bots_dormidos = True       # sin humanos todavía: los bots entran cuando llegue alguien
        self._sin_humanos_desde = time.time()
        threading.Thread(target=self._leer, daemon=True).start()
        log(f'{self.id}: arrancada (pid {self.proc.pid}, puerto {self.puerto}, preset {self.preset})')

    def _leer(self):
        ruta = os.path.join(self.dir, 'agente.log')
        for crudo in iter(self.proc.stdout.readline, b''):
            linea = crudo.decode('utf-8', 'replace').rstrip('\n')
            if linea.startswith('cl score ping name'):
                self._status = []
                continue
            if self._status is not None:
                if linea.startswith('--'):
                    continue
                m = RE_STATUS.match(linea)
                if m:
                    self._status.append(m)
                    continue
                self._aplicar_status(self._status)
                self._status = None
            if linea.startswith('map: '):
                continue          # cabecera del status: no ensucia el log
            try:
                if os.path.exists(ruta) and os.path.getsize(ruta) > TAM_MAX_LOG:
                    os.replace(ruta, ruta + '.1')
                with open(ruta, 'a', encoding='utf-8') as f:
                    f.write(linea + '\n')
            except OSError:
                pass

    def _aplicar_status(self, filas):
        mapa = leer_socios()
        detalle = []
        for m in filas:
            direccion = m.group(5)
            if direccion == 'bot':
                continue
            ip = direccion.rsplit(':', 1)[0]
            nombre = RE_COLOR.sub('', m.group(4)).strip()[:32]
            socio = mapa.get(ip, '')
            detalle.append({'nombre': nombre, 'socio': socio, 'ip': ip, 'clave': m.group(1),
                            'estado': 'conectando' if m.group(3) in ('CON', 'ZMB') else ''})
            # Solo con el jugador ya dentro: mientras conecta, el status trae el nombre vacío
            if socio and nombre and m.group(3) not in ('CON', 'ZMB') and (socio, nombre) not in self._anunciados:
                self._anunciados.add((socio, nombre))
                usuario = socio.split(':', 1)[-1]
                log(f'{self.id}: «{nombre}» es el socio {socio}')
                if nombre != usuario:
                    self.comando(f'say "{_texto(nombre, 32)} es el socio {_texto(usuario, 32)} de NOBS"')
        self.detalle = detalle
        self.jugadores = len(detalle)
        self.nombres = [d['nombre'] for d in detalle]

    def comando(self, texto):
        if self.proc and self.proc.poll() is None:
            try:
                self.proc.stdin.write((texto.replace('\n', ' ') + '\n').encode('utf-8'))
                self.proc.stdin.flush()
            except OSError:
                pass

    def parar(self, espera=5.0):
        if not self.proc or self.proc.poll() is not None:
            return
        self.comando('quit')
        try:
            self.proc.wait(espera)
        except subprocess.TimeoutExpired:
            os.killpg(self.proc.pid, signal.SIGKILL)
            self.proc.wait()
        self.fase = 'parada'

    def vigilar(self):
        if self.borrada or parando:
            return
        if self.proc is None or self.proc.poll() is not None:
            if self.proc is not None:
                vivio = time.time() - self.ultimo_arranque
                log(f'{self.id}: salió con código {self.proc.returncode} tras {vivio:.0f} s')
                self.espera = 2.0 if vivio > 60 else min(self.espera * 2, 60.0)
                self.proc = None
                self.fase = 'reiniciando'
                self._proximo = time.time() + self.espera
            if time.time() >= getattr(self, '_proximo', 0):
                self.arrancar()
            return
        self.comando('status')
        # Bots: fuera si no queda ningún humano (CPU cero); dentro en cuanto entra alguien
        if self.jugadores > 0:
            self._sin_humanos_desde = None
            if self._bots_dormidos and self.config['bots']:
                self.comando(f'set bot_minplayers {self.config["bots"]}')
                self._bots_dormidos = False
        else:
            if self._sin_humanos_desde is None:
                self._sin_humanos_desde = time.time()
            if not self._bots_dormidos and time.time() - self._sin_humanos_desde > BOTS_DUERMEN_SEG:
                log(f'{self.id}: sin humanos {BOTS_DUERMEN_SEG} s; los bots se retiran')
                self.comando('set bot_minplayers 0')
                self.comando('kick allbots')
                self._bots_dormidos = True

    def medir(self):
        if not self.proc or self.proc.poll() is not None:
            self.cpu = 0.0
            self.rss_mb = 0.0
            return
        try:
            with open(f'/proc/{self.proc.pid}/stat') as f:
                campos = f.read().rsplit(')', 1)[1].split()
            ticks = int(campos[11]) + int(campos[12])
            with open(f'/proc/{self.proc.pid}/status') as f:
                for linea in f:
                    if linea.startswith('VmRSS:'):
                        self.rss_mb = int(linea.split()[1]) / 1024
            ahora = time.monotonic()
            if self._muestra:
                t0, k0 = self._muestra
                self.cpu = 100.0 * (ticks - k0) / TICKS / max(ahora - t0, 0.001)
            self._muestra = (ahora, ticks)
        except (OSError, IndexError, ValueError):
            pass

    def publico(self):
        return {'id': self.id, 'nombre': self.nombre, 'preset': self.preset, 'fase': self.fase,
                'jugadores': self.jugadores, 'huecos': self.huecos,
                'modo': NOMBRES_MODO[self.config['modo']], 'mapa': self.config['mapa'],
                'socios': [d['socio'].split(':', 1)[-1] for d in self.detalle if d['socio']],
                'dueno': self.dueno.split(':', 1)[-1] if self.dueno else None,
                'contrasena': bool(self.config['contrasena'])}

    def privado(self):
        d = self.publico()
        r = resumen(self.config)
        d.update({'resumen': r, 'detalle': self.detalle, 'vetos': sorted(self.vetos), 'dueno_completo': self.dueno,
                  'nombres': self.nombres, 'puerto': self.puerto,
                  'pid': self.proc.pid if self.proc and self.proc.poll() is None else None,
                  'arranques': self.arranques, 'cpu': round(self.cpu, 1), 'rss_mb': round(self.rss_mb, 1),
                  'desde': int(self.ultimo_arranque), 'bots_dormidos': self._bots_dormidos})
        return d


# -- estado persistente --
def guardar_estado():
    with cerrojo:
        datos = {'salas': [{'id': s.id, 'origen': s.preset, 'config': s.config, 'dueno': s.dueno,
                            'vetos': sorted(s.vetos)} for s in salas.values()]}
        vetos = ''.join(f'{s.id} {v}\n' for s in salas.values() for v in sorted(s.vetos))
        tokens = ''.join(f'{s.id}: 127.0.0.1:{s.puerto}\n' for s in salas.values())
    escribir_atomico(FICHERO_ESTADO, json.dumps(datos, indent=1))
    escribir_atomico(FICHERO_VETOS, vetos)
    escribir_atomico(FICHERO_TOKENS, tokens)


def leer_socios():
    try:
        with open(FICHERO_SOCIOS, encoding='utf-8') as f:
            return json.load(f)
    except (OSError, ValueError):
        return {}


def crear_sala(config, origen, sala_id=None, dueno=None):
    with cerrojo:
        if len(salas) >= MAX_SALAS:
            raise ValueError(f'tope de salas alcanzado ({MAX_SALAS})')
        if dueno and any(s.dueno == dueno for s in salas.values()):
            raise ValueError('ya tienes una sala abierta: ciérrala antes de crear otra')
        if sala_id is None:
            libres = [f'sala{n}' for n in range(1, MAX_SALAS + 1) if f'sala{n}' not in salas]
            sala_id = libres[0]
        if not ID_SALA.match(sala_id) or sala_id in salas:
            raise ValueError('id de sala no válido o ya en uso')
        sala = Sala(sala_id, config, origen)
        sala.dueno = dueno
        salas[sala_id] = sala
    guardar_estado()
    sala.arrancar()
    return sala


def renombrar_sala(sala_id, nombre):
    nombre = _texto(nombre, 60)
    if not nombre:
        raise ValueError('falta el nombre')
    with cerrojo:
        sala = salas[sala_id]
        sala.renombrar(nombre)
    guardar_estado()
    sala.comando(f'set sv_hostname "{nombre}"')
    return 'aplicado'


def _jugador(sala, socio_id):
    for d in sala.detalle:
        if d['socio'].split(':', 1)[0] == str(socio_id):
            return d
    return None


def expulsar(sala_id, socio_id):
    with cerrojo:
        sala = salas[sala_id]
        sala.vetos.add(str(socio_id))
    guardar_estado()
    sala.comando(f'addip {ip_de_socio(socio_id)}')
    j = _jugador(sala, socio_id)
    if j:
        sala.comando(f'clientkick {j["clave"]}')
    return bool(j)


def readmitir(sala_id, socio_id):
    with cerrojo:
        sala = salas[sala_id]
        sala.vetos.discard(str(socio_id))
    guardar_estado()
    sala.comando(f'removeip {ip_de_socio(socio_id)}')


def listar_presets():
    lista = []
    for n in sorted(os.listdir(DIR_PRESETS)):
        if n.endswith('.json'):
            try:
                lista.append(dict(resumen(leer_preset(n[:-5])), slug=n[:-5]))
            except (ValueError, OSError):
                pass
    return lista


def borrar_sala(sala_id):
    with cerrojo:
        sala = salas.pop(sala_id, None)
    if sala is None:
        raise KeyError(sala_id)
    sala.borrada = True
    guardar_estado()
    sala.parar()


def cargar_estado():
    for d in (DIR_PRESETS, DIR_SALAS, DIR_CONTROL, os.path.dirname(FICHERO_TOKENS)):
        os.makedirs(d, exist_ok=True)
    for nombre in os.listdir(PRESETS_SEMILLA):
        destino = os.path.join(DIR_PRESETS, nombre)
        if not os.path.exists(destino):
            shutil.copyfile(os.path.join(PRESETS_SEMILLA, nombre), destino)
    if os.path.isfile(FICHERO_ESTADO):
        with open(FICHERO_ESTADO, encoding='utf-8') as f:
            definidas = json.load(f).get('salas', [])
    else:
        definidas = [{'id': f'sala{i + 1}', 'preset': p.strip()} for i, p in enumerate(SALAS_INICIALES[:MAX_SALAS])]
    for d in definidas[:MAX_SALAS]:
        try:
            if 'config' in d:
                sala = Sala(d['id'], validar(d['config']), d.get('origen', 'personalizada'))
                sala.dueno = d.get('dueno')
                sala.vetos = set(str(v) for v in d.get('vetos', []))
            else:
                sala = Sala(d['id'], leer_preset(d['preset']), d['preset'])
            salas[d['id']] = sala
        except (ValueError, KeyError) as e:
            log(f'sala {d} descartada: {e}')
    guardar_estado()


def bucle_vigilancia():
    rele = None
    while not parando:
        if rele is None or rele.poll() is not None:
            if rele is not None:
                log(f'el relé salió con código {rele.returncode}; relanzando')
            entorno = dict(os.environ, ARENA_FICHERO_TOKENS=FICHERO_TOKENS, ARENA_FICHERO_FASES=FICHERO_FASES,
                           ARENA_PUERTO_WS=str(PUERTO_WS))
            rele = subprocess.Popen(['/opt/arena-venv/bin/python', '/opt/arena/relay.py'], env=entorno)
        with cerrojo:
            actuales = list(salas.values())
        for s in actuales:
            try:
                s.vigilar()
                s.medir()
            except Exception as e:
                log(f'{s.id}: error vigilando: {e}')
            if s.dueno and s.jugadores == 0:
                if time.time() - max(s._creada, getattr(s, '_ocupada', 0)) > SALA_SOCIO_CADUCA_SEG:
                    log(f'{s.id}: sala del socio {s.dueno} vacía {SALA_SOCIO_CADUCA_SEG} s; se cierra')
                    auditar('agente', 'caducar', {'sala': s.id, 'dueno': s.dueno})
                    try:
                        borrar_sala(s.id)
                    except KeyError:
                        pass
            elif s.jugadores > 0:
                s._ocupada = time.time()
        escribir_atomico(FICHERO_FASES, ''.join(f'{s.id}: {s.fase}\n' for s in actuales))
        time.sleep(3)
    if rele and rele.poll() is None:
        rele.terminate()


def memoria_contenedor_mb():
    for ruta in ('/sys/fs/cgroup/memory.current', '/sys/fs/cgroup/memory/memory.usage_in_bytes'):
        try:
            with open(ruta) as f:
                return round(int(f.read()) / 1048576, 1)
        except (OSError, ValueError):
            continue
    return None


class Api(BaseHTTPRequestHandler):
    server_version = 'arena-q3'

    def log_message(self, fmt, *args):
        pass

    def _json(self, codigo, cuerpo):
        datos = json.dumps(cuerpo, ensure_ascii=False).encode('utf-8')
        self.send_response(codigo)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Cache-Control', 'no-store')
        self.send_header('Content-Length', str(len(datos)))
        self.end_headers()
        self.wfile.write(datos)

    def _autorizado(self):
        if not TOKEN:
            self._json(503, {'error': 'ARENA_CONTROL_TOKEN sin configurar'})
            return False
        if not hmac.compare_digest(self.headers.get('X-Arena-Token', ''), TOKEN):
            self._json(401, {'error': 'token no válido'})
            return False
        return True

    def _socio(self):
        socio = (self.headers.get('X-Arena-User') or '').strip()
        if not re.match(r'^\d+:.{1,64}$', socio):
            self._json(401, {'error': 'sin identidad de socio'})
            return None
        if self.command != 'GET' and self.headers.get('X-Requested-With') != 'arena':
            self._json(403, {'error': 'petición no válida'})
            return None
        return socio

    def _actor(self):
        return (self.headers.get('X-Arena-Actor') or 'desconocido')[:64]

    def _cuerpo(self):
        largo = int(self.headers.get('Content-Length') or 0)
        if largo > 65536:
            raise ValueError('cuerpo demasiado grande')
        return json.loads(self.rfile.read(largo) or b'{}') if largo else {}

    def do_GET(self):
        url = urlparse(self.path)
        partes = [p for p in url.path.split('/') if p]
        if partes == ['publico']:
            socio_id = (self.headers.get('X-Arena-User') or '').split(':', 1)[0]
            with cerrojo:
                lista = [dict(s.publico(), vetado=socio_id in s.vetos) for s in salas.values()]
            self._json(200, {'salas': lista})
            return
        if partes == ['salud']:
            self._json(200, {'ok': True})
            return
        if partes == ['publico', 'presets']:
            self._json(200, {'presets': [{k: p[k] for k in ('slug', 'nombre', 'mapa', 'humanos', 'ias')} for p in listar_presets()]})
            return
        if not self._autorizado():
            return
        if partes == ['estado']:
            with cerrojo:
                lista = [s.privado() for s in salas.values()]
            self._json(200, {'salas': lista, 'max_salas': MAX_SALAS, 'memoria_contenedor_mb': memoria_contenedor_mb(),
                             'carga': os.getloadavg(), 'cpus': os.cpu_count()})
        elif partes == ['presets']:
            self._json(200, {'presets': listar_presets()})
        elif len(partes) == 3 and partes[0] == 'salas' and partes[2] == 'log':
            sala = salas.get(partes[1])
            if not sala:
                self._json(404, {'error': 'no existe la sala'})
                return
            lineas = min(int(parse_qs(url.query).get('lineas', ['200'])[0]), 2000)
            ruta = os.path.join(sala.dir, 'agente.log')
            texto = []
            if os.path.exists(ruta):
                with open(ruta, encoding='utf-8', errors='replace') as f:
                    texto = f.readlines()[-lineas:]
            self._json(200, {'sala': sala.id, 'lineas': [t.rstrip('\n') for t in texto]})
        else:
            self._json(404, {'error': 'ruta desconocida'})

    def do_POST(self):
        partes = [p for p in urlparse(self.path).path.split('/') if p]
        if partes == ['publico', 'salas']:
            socio = self._socio()
            if not socio:
                return
            try:
                cuerpo = self._cuerpo()
                origen = str(cuerpo.get('preset', ''))
                config = leer_preset(origen)
                config['nombre'] = _texto(cuerpo.get('nombre'), 60) or f'Sala de {socio.split(":", 1)[1]}'
                config['contrasena'] = _texto(cuerpo.get('contrasena'), 30)
                sala = crear_sala(validar(config), origen, dueno=socio)
                auditar(socio, 'crear-sala-socio', {'sala': sala.id, 'origen': origen, 'nombre': sala.nombre})
                self._json(201, sala.publico())
            except (ValueError, json.JSONDecodeError) as e:
                self._json(400, {'error': str(e)})
            return
        if not self._autorizado():
            return
        try:
            cuerpo = self._cuerpo()
            if partes == ['salas']:
                if cuerpo.get('plan'):
                    raise ValueError('Quake 3 no tiene creador a medida: usa un preset')
                origen = str(cuerpo.get('preset', ''))
                config = leer_preset(origen)
                if cuerpo.get('nombre'):
                    config['nombre'] = _texto(cuerpo['nombre'], 60)
                sala = crear_sala(validar(config), origen, cuerpo.get('id'))
                auditar(self._actor(), 'crear', {'sala': sala.id, 'origen': origen, **resumen(config)})
                self._json(201, sala.privado())
            elif len(partes) == 3 and partes[0] == 'salas' and partes[2] == 'nombre':
                cuando = renombrar_sala(partes[1], cuerpo.get('nombre'))
                auditar(self._actor(), 'renombrar', {'sala': partes[1], 'nombre': salas[partes[1]].nombre})
                self._json(200, {'ok': True, 'cuando': cuando})
            elif len(partes) == 3 and partes[0] == 'salas' and partes[2] in ('expulsar', 'readmitir', 'silenciar', 'desilenciar'):
                socio_id = str(cuerpo.get('socio', ''))
                if not socio_id.isdigit():
                    raise ValueError('falta el id del socio')
                if partes[1] not in salas:
                    raise KeyError(partes[1])
                if partes[2] in ('silenciar', 'desilenciar'):
                    raise ValueError('Quake 3 no permite silenciar a un jugador: expúlsalo si hace falta')
                if partes[2] == 'expulsar':
                    resultado = {'ok': True, 'estaba': expulsar(partes[1], socio_id)}
                else:
                    readmitir(partes[1], socio_id)
                    resultado = {'ok': True}
                auditar(self._actor(), partes[2], {'sala': partes[1], 'socio': socio_id})
                self._json(200, resultado)
            elif len(partes) == 3 and partes[0] == 'salas' and partes[2] == 'reiniciar':
                sala = salas[partes[1]]
                auditar(self._actor(), 'reiniciar', {'sala': sala.id})
                sala.parar()
                sala.espera = 2.0
                self._json(200, {'ok': True})
            elif len(partes) == 3 and partes[0] == 'salas' and partes[2] == 'aviso':
                sala = salas[partes[1]]
                mensaje = _texto(cuerpo.get('mensaje', ''), 200)
                sala.comando(f'say "{mensaje}"')
                auditar(self._actor(), 'aviso', {'sala': sala.id, 'mensaje': mensaje})
                self._json(200, {'ok': True})
            elif partes == ['apagar-todo']:
                with cerrojo:
                    ids = list(salas)
                for sala_id in ids:
                    borrar_sala(sala_id)
                auditar(self._actor(), 'apagar-todo', {'salas': ids})
                self._json(200, {'ok': True, 'salas': ids})
            else:
                self._json(404, {'error': 'ruta desconocida'})
        except KeyError:
            self._json(404, {'error': 'no existe la sala'})
        except (ValueError, json.JSONDecodeError) as e:
            self._json(400, {'error': str(e)})

    def do_DELETE(self):
        partes = [p for p in urlparse(self.path).path.split('/') if p]
        if len(partes) == 3 and partes[:2] == ['publico', 'salas']:
            socio = self._socio()
            if not socio:
                return
            sala = salas.get(partes[2])
            if sala is None or sala.dueno != socio:
                self._json(404, {'error': 'no tienes esa sala'})
                return
            borrar_sala(partes[2])
            auditar(socio, 'cerrar-sala-socio', {'sala': partes[2]})
            self._json(200, {'ok': True})
            return
        if not self._autorizado():
            return
        if len(partes) == 2 and partes[0] == 'salas':
            try:
                borrar_sala(partes[1])
                auditar(self._actor(), 'borrar', {'sala': partes[1]})
                self._json(200, {'ok': True})
            except KeyError:
                self._json(404, {'error': 'no existe la sala'})
        else:
            self._json(404, {'error': 'ruta desconocida'})


def apagar(*_):
    global parando
    parando = True
    log('apagando salas…')
    with cerrojo:
        actuales = list(salas.values())
    for s in actuales:
        s.parar(espera=5.0)
    sys.exit(0)


def main():
    if not TOKEN:
        log('AVISO: sin ARENA_CONTROL_TOKEN la API privada responde 503 (las salas funcionan igual)')
    cargar_estado()
    signal.signal(signal.SIGTERM, apagar)
    signal.signal(signal.SIGINT, apagar)
    threading.Thread(target=bucle_vigilancia, daemon=True).start()
    log(f'agente listo: {len(salas)} salas, tope {MAX_SALAS}, API :{PUERTO_API}, relé :{PUERTO_WS}')
    ThreadingHTTPServer(('0.0.0.0', PUERTO_API), Api).serve_forever()


if __name__ == '__main__':
    main()
