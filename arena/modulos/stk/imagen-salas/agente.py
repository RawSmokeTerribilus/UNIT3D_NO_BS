#!/usr/bin/env python3
"""Arena NOBS — agente de salas de SuperTuxKart (servidor nativo SERVER_ONLY + bots en un cliente --network-ai).

Calco del agente de Quake 3 (mismo contrato de API que WZ/Q3/CS): cada sala es un `supertuxkart --lan-server`
en su puerto UDP (2759, 2760…) con consola por stdin (--network-console: listpeers/kick/kickban); los socios
entran por el relé común (/dg) con su IP de loopback. Los bots son un segundo proceso (`--connect-now
--network-ai=N`, desde 127.0.0.1) que se lanza al entrar un humano y se para si no queda ninguno;
ai-handling=true hace que el servidor retire bots según entran humanos.

  - Sin «lobby» propio del agente: la sala está siempre abierta (la pista se vota en el lobby del juego).
  - Identidad: líneas «New player X with online id N from IP:PUERTO» y «X disconnected» del servidor.
  - Expulsar: listpeers (IP → host id) + kickban, y veto en el relé hasta readmitir.
  - Avisos y silenciar: la consola de STK no tiene chat → la API lo rechaza con un mensaje claro.

Nunca toca el socket de Docker. Solo biblioteca estándar.

Variables de entorno: ARENA_CONTROL_TOKEN, ARENA_MAX_SALAS (6), ARENA_SALAS_INICIALES ("carrera"),
ARENA_PUERTO_BASE (2759), ARENA_DATOS (/datos), ARENA_SALA_SOCIO_CADUCA_SEG (900), ARENA_BOTS_DUERMEN_SEG (60).
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

STK_BIN = os.environ.get('STK_BIN', '/opt/stk/supertuxkart')
STK_DATOS = os.environ.get('STK_DATOS', '/opt/stk')       # SUPERTUXKART_DATADIR (lleva data/)
DATOS = os.environ.get('ARENA_DATOS', '/datos')
PRESETS_SEMILLA = '/opt/arena/presets'
TOKEN = os.environ.get('ARENA_CONTROL_TOKEN', '')
MAX_SALAS = int(os.environ.get('ARENA_MAX_SALAS', '6'))
PUERTO_BASE = int(os.environ.get('ARENA_PUERTO_BASE', '2759'))
SALAS_INICIALES = [p for p in os.environ.get('ARENA_SALAS_INICIALES', 'carrera').split(',') if p.strip()]
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
RE_ENTRA = re.compile(r'New player (.+) with online id \d+ from ([\d.]+):\d+ with (.+)\.$')
RE_SALE = re.compile(r'ServerLobby: (.+) disconnected$')
RE_PEER = re.compile(r'^(\d+): ([\d.]+):\d+ ')
RE_ANSI = re.compile(r'\x1b\[[0-9;]*m')
# --mode de STK: 0 normal, 1 contrarreloj, 2 batalla, 3 fútbol, 5 capturar la bandera
MODOS = {'carrera': 0, 'contrarreloj': 1, 'batalla': 2, 'futbol': 3, 'bandera': 5}
NOMBRES_MODO = {'carrera': 'Carrera', 'contrarreloj': 'Contrarreloj', 'batalla': 'Batalla',
                'futbol': 'Fútbol', 'bandera': 'Captura la bandera'}
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
    """Preset de STK → config limpia (todo lo que llega a la línea de comandos del servidor pasa por aquí)."""
    modo = str(config.get('modo', 'carrera'))
    if modo not in MODOS:
        raise ValueError('modo no válido (carrera, contrarreloj, batalla, futbol, bandera)')
    humanos = _entero(config, 'humanos', 2, 8, 8)
    return {
        'nombre': _texto(config.get('nombre'), 60) or NOMBRES_MODO[modo],
        'mapa': 'votación',
        'modo': modo,
        'humanos': humanos,
        'bots': 0 if modo == 'contrarreloj' else _entero(config, 'bots', 0, 7, 3),
        'dificultad': _entero(config, 'dificultad', 0, 3, 1),
        'vueltas': _entero(config, 'vueltas', 1, 10, 3),
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

    def _config_xml(self):
        """server_config.xml mínimo: bots gestionados (ai-handling) y sin cuentas ni validación."""
        ruta = os.path.join(self.dir, 'server_config.xml')
        escribir_atomico(ruta, '<?xml version="1.0"?>\n<server-config version="6" >\n'
                         '    <ai-handling value="true" />\n    <validating-player value="false" />\n'
                         '    <owner-less value="true" />\n    <kick-idle-player-seconds value="600" />\n'
                         # Sin anfitrión, la partida arranca sola tras la cuenta atrás si hay este mínimo de
                         # humanos (los bots no cuentan; por defecto 2 → con un socio solo no arrancaba nunca)
                         '    <min-start-game-players value="1" />\n    <start-game-counter value="30" />\n'
                         '</server-config>\n')
        return ruta

    def arrancar(self):
        os.makedirs(self.dir, exist_ok=True)
        c = self.config
        args = [STK_BIN, f'--lan-server={c["nombre"]}', '--network-console', f'--port={self.puerto}',
                f'--max-players={min(8, c["humanos"] + c["bots"])}', f'--difficulty={c["dificultad"]}',
                f'--mode={MODOS[c["modo"]]}', f'--laps={c["vueltas"]}', f'--server-config={self._config_xml()}',
                '--no-auto-end']
        if c['modo'] == 'batalla':
            args.append('--battle-mode=0')
        if c['modo'] == 'futbol':
            args.append('--soccer-goals')
        if c['contrasena']:
            args.append(f'--server-password={c["contrasena"]}')
        entorno = dict(os.environ, HOME=self.dir, SUPERTUXKART_DATADIR=STK_DATOS)
        self.proc = subprocess.Popen(args, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                     cwd=self.dir, start_new_session=True, env=entorno)
        self.bots_proc = None
        self.arranques += 1
        self.ultimo_arranque = time.time()
        self.fase = 'partida'
        self.jugadores = 0
        self.nombres = []
        self.detalle = []
        self._en_sala = {}           # nombre -> ip (socios: 127.10.x.x)
        self._peers = {}             # ip -> host id (de listpeers)
        self._muestra = None
        self._bots_dormidos = True
        self._sin_humanos_desde = time.time()
        threading.Thread(target=self._leer, daemon=True).start()
        log(f'{self.id}: arrancada (pid {self.proc.pid}, puerto {self.puerto}, preset {self.preset})')

    def _leer(self):
        ruta = os.path.join(self.dir, 'agente.log')
        for crudo in iter(self.proc.stdout.readline, b''):
            linea = RE_ANSI.sub('', crudo.decode('utf-8', 'replace').rstrip('\n'))
            m = RE_PEER.match(linea)
            if m:
                self._peers[m.group(2)] = m.group(1)
                continue
            m = RE_ENTRA.search(linea)
            if m and m.group(2).startswith('127.10.'):
                self._en_sala[m.group(1)] = m.group(2)
                self._actualizar()
            m = RE_SALE.search(linea)
            if m and m.group(1) in self._en_sala:
                del self._en_sala[m.group(1)]
                self._actualizar()
            try:
                if os.path.exists(ruta) and os.path.getsize(ruta) > TAM_MAX_LOG:
                    os.replace(ruta, ruta + '.1')
                with open(ruta, 'a', encoding='utf-8') as f:
                    f.write(linea + '\n')
            except OSError:
                pass

    def _actualizar(self):
        mapa = leer_socios()
        detalle = []
        for nombre, ip in list(self._en_sala.items()):
            socio = mapa.get(ip, '')
            detalle.append({'nombre': nombre[:32], 'socio': socio, 'ip': ip, 'clave': self._peers.get(ip, ''), 'estado': ''})
            if socio and (socio, nombre) not in self._anunciados:
                self._anunciados.add((socio, nombre))
                log(f'{self.id}: «{nombre}» es el socio {socio}')
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

    def _parar_bots(self):
        if getattr(self, 'bots_proc', None) and self.bots_proc.poll() is None:
            os.killpg(self.bots_proc.pid, signal.SIGTERM)
            try:
                self.bots_proc.wait(5)
            except subprocess.TimeoutExpired:
                os.killpg(self.bots_proc.pid, signal.SIGKILL)
        self.bots_proc = None

    def _lanzar_bots(self):
        dir_bots = os.path.join(self.dir, 'bots')
        os.makedirs(dir_bots, exist_ok=True)
        entorno = dict(os.environ, HOME=dir_bots, SUPERTUXKART_DATADIR=STK_DATOS)
        args = [STK_BIN, f'--connect-now=127.0.0.1:{self.puerto}', f'--network-ai={self.config["bots"]}']
        if self.config['contrasena']:
            args.append(f'--server-password={self.config["contrasena"]}')
        self.bots_proc = subprocess.Popen(args, stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                                          cwd=dir_bots, start_new_session=True, env=entorno)

    def parar(self, espera=5.0):
        self._parar_bots()
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
                self._parar_bots()
                vivio = time.time() - self.ultimo_arranque
                log(f'{self.id}: salió con código {self.proc.returncode} tras {vivio:.0f} s')
                self.espera = 2.0 if vivio > 60 else min(self.espera * 2, 60.0)
                self.proc = None
                self.fase = 'reiniciando'
                self._proximo = time.time() + self.espera
            if time.time() >= getattr(self, '_proximo', 0):
                self.arrancar()
            return
        self.comando('listpeers')
        if self.jugadores > 0:
            self._sin_humanos_desde = None
            if self._bots_dormidos and self.config['bots']:
                self._lanzar_bots()
                self._bots_dormidos = False
            elif self.bots_proc and self.bots_proc.poll() is not None and self.config['bots']:
                self._lanzar_bots()      # el cliente de bots se cayó: otra vez
        else:
            if self._sin_humanos_desde is None:
                self._sin_humanos_desde = time.time()
            if not self._bots_dormidos and time.time() - self._sin_humanos_desde > BOTS_DUERMEN_SEG:
                log(f'{self.id}: sin humanos {BOTS_DUERMEN_SEG} s; los bots se retiran')
                self._parar_bots()
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
    # El nombre del servidor LAN se lee al arrancar: se aplica ya si la sala está vacía
    if sala.jugadores == 0:
        sala.parar()
        sala.espera = 2.0
        return 'aplicado'
    return 'al reiniciar'


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
    j = _jugador(sala, socio_id)
    if j and j['clave']:
        sala.comando(f'kickban {j["clave"]}')
    return bool(j)


def readmitir(sala_id, socio_id):
    with cerrojo:
        sala = salas[sala_id]
        sala.vetos.discard(str(socio_id))
    guardar_estado()   # el veto del relé es la puerta; el ban de IP del servidor se olvida al reiniciar la sala


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
    server_version = 'arena-stk'

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
                    raise ValueError('SuperTuxKart no tiene creador a medida: usa un preset')
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
                    raise ValueError('SuperTuxKart no permite silenciar a un jugador: expúlsalo si hace falta')
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
                if partes[1] not in salas:
                    raise KeyError(partes[1])
                raise ValueError('la consola de SuperTuxKart no tiene chat: no se pueden mandar avisos')
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
