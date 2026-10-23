#!/usr/bin/env python3
"""Arena NOBS — agente de salas de Warzone 2100.

Gestiona PROCESOS, no contenedores: cada sala es un `warzone2100 --headless --autohost`
en su propio puerto (2101, 2102, ...). Además:
  - lanza y vigila el relé de sesiones reanudables (relay.py) y le escribe el fichero de tokens
    (salaN -> 127.0.0.1:puerto); fija en cada sala el autokick por lag a ARENA_PAUSA_MAX_SEG;
  - expone una API interna (HTTP en la red de la Arena) para el dashboard de NOBS;
  - mide CPU/RAM de cada sala y poda replays viejas.

Nunca toca el socket de Docker. Solo biblioteca estándar.

Variables de entorno:
  ARENA_CONTROL_TOKEN      token compartido con UNIT3D (sin él, la API privada responde 503)
  ARENA_MAX_SALAS          tope de salas simultáneas (por defecto 10)
  ARENA_SALAS_INICIALES    salas a crear si no hay estado guardado, p. ej. "contra-ia,duelo"
  ARENA_PUERTO_BASE        primer puerto de juego (por defecto 2101)
  ARENA_PODA_REPLAYS_DIAS  borrar replays más viejas que esto (por defecto 14; 0 = nunca)
  ARENA_PAUSA_MAX_SEG      cuánto puede estar fuera un jugador sin perder la partida (por defecto 7200)
  ARENA_DATOS              raíz persistente (por defecto /datos)
"""

import hmac
import json
import os
import re
import shutil
import zipfile
import signal
import subprocess
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

WZ_BIN = os.environ.get('WZ_BIN', '/opt/wz/bin/warzone2100')
DATOS = os.environ.get('ARENA_DATOS', '/datos')
PRESETS_SEMILLA = '/opt/arena/presets'
TOKEN = os.environ.get('ARENA_CONTROL_TOKEN', '')
MAX_SALAS = int(os.environ.get('ARENA_MAX_SALAS', '10'))
PUERTO_BASE = int(os.environ.get('ARENA_PUERTO_BASE', '2101'))
SALAS_INICIALES = [p for p in os.environ.get('ARENA_SALAS_INICIALES', 'contra-ia').split(',') if p.strip()]
PODA_DIAS = int(os.environ.get('ARENA_PODA_REPLAYS_DIAS', '14'))
# Cuánto puede estar fuera un jugador sin perder la partida: el relé guarda su sesión y el host no
# lo expulsa por lag durante este tiempo (la partida se queda en pausa para todos mientras tanto)
PAUSA_MAX_SEG = int(os.environ.get('ARENA_PAUSA_MAX_SEG', '7200'))
# Partida empezada sin ningún humano conectado (se fueron todos; quien está fuera un rato con la
# sesión reanudable sigue contando como conectado): pasado este tiempo, la sala se reinicia
VACIA_REINICIO_SEG = int(os.environ.get('ARENA_VACIA_REINICIO_SEG', '20'))
PUERTO_WS = 8080
PUERTO_API = 8081

DIR_PRESETS = os.path.join(DATOS, 'presets')
DIR_SALAS = os.path.join(DATOS, 'salas')
DIR_CONTROL = os.path.join(DATOS, 'control')
FICHERO_ESTADO = os.path.join(DIR_CONTROL, 'estado.json')
FICHERO_ACCIONES = os.path.join(DIR_CONTROL, 'acciones.jsonl')
FICHERO_TOKENS = '/tmp/arena/tokens'
FICHERO_FASES = '/tmp/arena/fases'
FICHERO_SOCIOS = '/tmp/arena/socios.json'   # ip de loopback -> «id:usuario» (lo escribe el relé)
FICHERO_VETOS = '/tmp/arena/vetos'          # «salaN id» por línea (lo lee el relé)
# Salas que crea un socio (no el staff): una por socio, y se cierran si nadie entra en este tiempo
SALA_SOCIO_CADUCA_SEG = int(os.environ.get('ARENA_SALA_SOCIO_CADUCA_SEG', '900'))   # salaN: lobby|partida|… — el relé suelta antes las sesiones caídas en el lobby

WZ_DATOS = os.environ.get('WZ_DATOS', '/opt/wz/share/warzone2100')
ID_SALA = re.compile(r'^sala([1-9][0-9]?)$')
NOMBRE_PRESET = re.compile(r'^[a-z0-9][a-z0-9-]{0,40}$')
RE_UNE = re.compile(r'\] .* has joined, IP is: ')
RE_SALE = re.compile(r'Player \d+ has left the game\.')
TAM_MAX_LOG = 5 * 1024 * 1024
TICKS = os.sysconf('SC_CLK_TCK')

cerrojo = threading.RLock()
salas = {}          # id -> Sala
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


def leer_preset(nombre):
    if not NOMBRE_PRESET.match(nombre or ''):
        raise ValueError('nombre de preset no válido')
    ruta = os.path.join(DIR_PRESETS, nombre + '.json')
    if not os.path.isfile(ruta):
        raise ValueError('no existe el preset ' + nombre)
    with open(ruta, encoding='utf-8') as f:
        return json.load(f)


def huecos_humanos(config):
    """Huecos para humanos: los de maxPlayers que no son IA, ni están cerrados, ni son de espectador."""
    reto = config.get('challenge', {})
    total = int(reto.get('maxPlayers', 2))
    humanos = 0
    for i in range(total):
        j = config.get(f'player_{i}', {})
        if not (j.get('ai') or j.get('closed') or j.get('spectator')):
            humanos += 1
    return max(1, humanos)


# -- catálogo del juego (mapas e IA), leído de mp.wz al arrancar --
DIFICULTADES = ('Easy', 'Medium', 'Hard', 'Insane')
FACCIONES = ('Normal', 'NEXUS', 'Collective')
catalogo = {'mapas': [], 'ias': []}


def cargar_catalogo():
    try:
        with zipfile.ZipFile(os.path.join(WZ_DATOS, 'mp.wz')) as z:
            lev = z.read('addon.lev').decode('latin-1')
            mapas = {}
            for nombre, jugadores, tipo in re.findall(r'level\s+(\S+)\s+players\s+(\d+)\s+type\s+(\d+)', lev):
                if tipo == '14':
                    mapas[nombre] = int(jugadores)
            ias = []
            for f in sorted(n for n in z.namelist() if n.startswith('multiplay/skirmish/') and n.endswith('.json')):
                ai = json.loads(z.read(f)).get('AI', {})
                if ai.get('js'):
                    ias.append({'js': ai['js'], 'nombre': ai.get('name', ai['js']), 'pista': ai.get('tip', '')})
        catalogo['mapas'] = [{'nombre': n, 'jugadores': j} for n, j in sorted(mapas.items(), key=lambda x: (x[1], x[0].lower()))]
        catalogo['ias'] = ias
        log(f'catálogo: {len(catalogo["mapas"])} mapas, {len(ias)} IA')
    except (OSError, KeyError, zipfile.BadZipFile, ValueError) as e:
        log(f'AVISO: no se pudo leer el catálogo de mp.wz: {e}')


def _entero(plan, clave, minimo, maximo, defecto):
    try:
        v = int(plan.get(clave, defecto))
    except (TypeError, ValueError):
        raise ValueError(f'{clave}: no es un número')
    if not minimo <= v <= maximo:
        raise ValueError(f'{clave}: fuera de rango ({minimo}–{maximo})')
    return v


def _texto(valor, maximo):
    limpio = re.sub(r'[\x00-\x1f\x7f]', '', str(valor or '')).strip()
    return limpio[:maximo]


def construir_config(plan):
    """Plan del panel → autohost de WZ, validado. Formato del plan:
    {nombre, mapa, huecos: [{tipo: humano|ia|cerrado, ia, dificultad, faccion, equipo}], alianzas, energia,
     bases, tecnologia, chatarreros, espectadores, contrasena, bloquear}"""
    nombre = _texto(plan.get('nombre'), 60)
    if not nombre:
        raise ValueError('falta el nombre de la sala')
    mapas = {m['nombre']: m['jugadores'] for m in catalogo['mapas']}
    mapa = str(plan.get('mapa', ''))
    if mapa not in mapas:
        raise ValueError('mapa desconocido: ' + mapa)
    huecos = plan.get('huecos')
    if not isinstance(huecos, list) or len(huecos) != mapas[mapa]:
        raise ValueError(f'el mapa {mapa} tiene {mapas[mapa]} huecos')
    ias = {a['js'] for a in catalogo['ias']}
    config = {'challenge': {
        'map': mapa,
        'maxPlayers': mapas[mapa],
        'name': nombre,
        'alliances': _entero(plan, 'alianzas', 0, 3, 2),
        'powerLevel': _entero(plan, 'energia', 0, 2, 1),
        'bases': _entero(plan, 'bases', 1, 3, 2),
        'techLevel': _entero(plan, 'tecnologia', 1, 4, 1),
        'scavengers': _entero(plan, 'chatarreros', 0, 2, 0),
        'spectatorHost': True,
        'openSpectatorSlots': _entero(plan, 'espectadores', 0, 4, 1),
        'blindMode': 'none',
    }}
    contrasena = _texto(plan.get('contrasena'), 30)
    if contrasena:
        config['challenge']['gamePassword'] = contrasena
    bloquear = bool(plan.get('bloquear', True))
    config['locked'] = {k: bloquear for k in ('power', 'alliances', 'teams', 'difficulty', 'ai', 'scavengers', 'bases')}
    config['locked']['position'] = False
    humanos = 0
    for i, h in enumerate(huecos):
        if not isinstance(h, dict):
            raise ValueError(f'hueco {i + 1}: formato no válido')
        tipo = h.get('tipo')
        equipo = _entero(h, 'equipo', 0, 9, i)
        if tipo == 'humano':
            config[f'player_{i}'] = {'team': equipo}
            humanos += 1
        elif tipo == 'ia':
            if h.get('ia') not in ias:
                raise ValueError(f'hueco {i + 1}: IA desconocida')
            if h.get('dificultad') not in DIFICULTADES:
                raise ValueError(f'hueco {i + 1}: dificultad no válida')
            faccion = h.get('faccion') or 'Normal'
            if faccion not in FACCIONES:
                raise ValueError(f'hueco {i + 1}: facción no válida')
            config[f'player_{i}'] = {'team': equipo, 'ai': h['ia'], 'difficulty': h['dificultad'], 'faction': faccion}
        elif tipo == 'cerrado':
            config[f'player_{i}'] = {'closed': True}
        else:
            raise ValueError(f'hueco {i + 1}: tipo no válido')
    if humanos == 0:
        raise ValueError('hace falta al menos un hueco para humanos')
    return config


def resumen(config):
    reto = config.get('challenge', {})
    total = int(reto.get('maxPlayers', 0))
    ias = sum(1 for i in range(total) if config.get(f'player_{i}', {}).get('ai'))
    return {'nombre': reto.get('name', ''), 'mapa': reto.get('map', ''), 'huecos': total,
            'humanos': huecos_humanos(config), 'ias': ias}


class Sala:
    def __init__(self, sala_id, config, origen):
        self.id = sala_id
        self.config = config
        self.preset = origen          # slug del preset de partida, o «personalizada»
        self.puerto = PUERTO_BASE + int(ID_SALA.match(sala_id).group(1)) - 1
        self.dir = os.path.join(DIR_SALAS, sala_id)
        self.proc = None
        self.arranques = 0
        self.ultimo_arranque = 0.0
        self.espera = 2.0
        self.fase = 'parada'
        self.jugadores = 0
        self.nombres = []
        self.cpu = 0.0
        self.rss_mb = 0.0
        self._muestra = None
        self.borrada = False
        self._vacia_desde = None
        self.dueno = None          # «id:usuario» si la creó un socio; None = sala del staff
        self.vetos = set()         # ids de socio vetados en esta sala
        self.detalle = []          # [{nombre, socio, ip, clave, estado}]
        self._anunciados = set()
        self._creada = time.time()
        self.nombre = config.get('challenge', {}).get('name', sala_id)
        self.huecos = huecos_humanos(config)

    def renombrar(self, nombre):
        self.config.setdefault('challenge', {})['name'] = nombre
        self.nombre = nombre

    # -- proceso --
    def _fijar_config(self):
        """hostAutoLagKickSeconds = PAUSA_MAX_SEG en el config del host (INI, sección [General]).
        WZ lo vuelve a escribir al salir con lo que leyó, así que se fija antes de cada arranque."""
        ruta = os.path.join(self.dir, 'config')
        clave = f'hostAutoLagKickSeconds={PAUSA_MAX_SEG}'
        lineas = []
        if os.path.exists(ruta):
            with open(ruta, encoding='utf-8', errors='replace') as f:
                lineas = f.read().splitlines()
        if not lineas:
            lineas = ['[General]']
        hecho = False
        for i, linea in enumerate(lineas):
            if linea.startswith('hostAutoLagKickSeconds='):
                lineas[i] = clave
                hecho = True
        if not hecho:
            pos = lineas.index('[General]') + 1 if '[General]' in lineas else 0
            lineas.insert(pos, clave)
        escribir_atomico(ruta, '\n'.join(lineas) + '\n')

    def arrancar(self):
        os.makedirs(os.path.join(self.dir, 'autohost'), exist_ok=True)
        self._fijar_config()
        escribir_atomico(os.path.join(self.dir, 'autohost', self.id + '.json'), json.dumps(self.config, indent=1))
        args = [WZ_BIN, f'--autohost={self.id}.json', f'--gameport={self.puerto}',
                f'--startplayers={self.huecos}', '--headless', '--nosound',
                f'--configdir={self.dir}', '--gamelog-output=log', '--enablecmdinterface=stdin']
        self.proc = subprocess.Popen(args, stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                                     stderr=subprocess.PIPE, cwd=self.dir, start_new_session=True)
        self.arranques += 1
        self.ultimo_arranque = time.time()
        self.fase = 'lobby'
        self.jugadores = 0
        self.nombres = []
        self._muestra = None
        threading.Thread(target=self._leer, args=(self.proc.stdout, 'out'), daemon=True).start()
        threading.Thread(target=self._leer, args=(self.proc.stderr, 'err'), daemon=True).start()
        log(f'{self.id}: arrancada (pid {self.proc.pid}, puerto {self.puerto}, preset {self.preset})')

    def _leer(self, flujo, canal):
        ruta = os.path.join(self.dir, 'agente.log')
        for crudo in iter(flujo.readline, b''):
            linea = crudo.decode('utf-8', 'replace').rstrip('\n')
            if '__WZROOMSTATUS__' in linea:
                self._estado_sala(linea)
            elif canal == 'err' and linea.startswith('WZEVENT: startMultiplayerGame'):
                self.fase = 'partida'
            elif RE_UNE.search(linea):
                self.jugadores += 1
            elif RE_SALE.search(linea):
                self.jugadores = max(0, self.jugadores - 1)
            try:
                if os.path.exists(ruta) and os.path.getsize(ruta) > TAM_MAX_LOG:
                    os.replace(ruta, ruta + '.1')
                with open(ruta, 'a', encoding='utf-8') as f:
                    f.write(f'{canal} {linea}\n')
            except OSError:
                pass

    def _estado_sala(self, linea):
        """El host vuelca el estado entero de la sala en stderr como
        __WZROOMSTATUS__{json}__ENDWZROOMSTATUS__: fase y jugadores (con su estado)."""
        try:
            crudo = linea.split('__WZROOMSTATUS__', 1)[1].split('__ENDWZROOMSTATUS__', 1)[0]
            estado = json.loads(crudo)
        except (IndexError, ValueError):
            return
        if estado.get('data', {}).get('state') == 'started':
            self.fase = 'partida'
        humanos = [p for p in estado.get('players', []) if p.get('type') == 'player' and p.get('status') != 'left']
        self.jugadores = len(humanos)
        self.nombres = [str(p.get('name', ''))[:32] for p in humanos]
        # Quién es quién: la IP de loopback con la que conecta el relé identifica al socio
        mapa = leer_socios()
        detalle = []
        for p in humanos:
            ip = str(p.get('ip', ''))
            socio = mapa.get(ip, '')
            nombre = str(p.get('name', ''))[:32]
            detalle.append({'nombre': nombre, 'socio': socio, 'ip': ip, 'clave': str(p.get('pk', '')),
                            'estado': p.get('status') or ''})
            clave_anuncio = (socio, nombre)
            if socio and clave_anuncio not in self._anunciados:
                self._anunciados.add(clave_anuncio)
                usuario = socio.split(':', 1)[-1]
                log(f'{self.id}: «{nombre}» es el socio {socio}')
                if nombre != usuario:
                    self.comando(f'chat bcast «{nombre}» es el socio {usuario} de NOBS')
        self.detalle = detalle

    def comando(self, texto):
        if self.proc and self.proc.poll() is None:
            self.proc.stdin.write((texto.replace('\n', ' ') + '\n').encode('utf-8'))
            self.proc.stdin.flush()

    def parar(self, espera=5.0):
        if not self.proc or self.proc.poll() is not None:
            return
        try:
            self.comando('shutdown now')
        except OSError:
            pass
        try:
            self.proc.wait(espera)
        except subprocess.TimeoutExpired:
            os.killpg(self.proc.pid, signal.SIGKILL)
            self.proc.wait()
        self.fase = 'parada'

    def vigilar(self):
        """Rearranca la sala si su proceso murió (al acabar una partida, el autohost sale)."""
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
        # Partida en marcha sin humanos: las IA juegan solas y nadie puede entrar → reiniciar
        if self.fase == 'partida' and self.jugadores == 0:
            if self._vacia_desde is None:
                self._vacia_desde = time.time()
            elif time.time() - self._vacia_desde > VACIA_REINICIO_SEG:
                log(f'{self.id}: partida sin jugadores humanos desde hace {VACIA_REINICIO_SEG} s; se reinicia')
                self._vacia_desde = None
                self.parar()
                self.espera = 2.0
        else:
            self._vacia_desde = None

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
                'socios': [d['socio'].split(':', 1)[-1] for d in self.detalle if d['socio']],
                'dueno': self.dueno.split(':', 1)[-1] if self.dueno else None,
                'contrasena': bool(self.config.get('challenge', {}).get('gamePassword'))}

    def privado(self):
        d = self.publico()
        d.update({'mapa': self.config.get('challenge', {}).get('map', ''), 'resumen': resumen(self.config),
                  'detalle': self.detalle, 'vetos': sorted(self.vetos), 'dueno_completo': self.dueno,
                  'nombres': self.nombres, 'puerto': self.puerto, 'pid': self.proc.pid if self.proc and self.proc.poll() is None else None,
                  'arranques': self.arranques, 'cpu': round(self.cpu, 1), 'rss_mb': round(self.rss_mb, 1),
                  'desde': int(self.ultimo_arranque)})
        return d


# -- estado persistente --
def guardar_estado():
    with cerrojo:
        datos = {'salas': [{'id': s.id, 'origen': s.preset, 'config': s.config, 'dueno': s.dueno,
                            'vetos': sorted(s.vetos)} for s in salas.values()]}
        vetos = ''.join(f'{s.id} {v}\n' for s in salas.values() for v in sorted(s.vetos))
    escribir_atomico(FICHERO_ESTADO, json.dumps(datos, indent=1))
    escribir_atomico(FICHERO_VETOS, vetos)
    escribir_tokens()


def leer_socios():
    try:
        with open(FICHERO_SOCIOS, encoding='utf-8') as f:
            return json.load(f)
    except (OSError, ValueError):
        return {}


def escribir_tokens():
    with cerrojo:
        lineas = ''.join(f'{s.id}: 127.0.0.1:{s.puerto}\n' for s in salas.values())
    escribir_atomico(FICHERO_TOKENS, lineas)


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
    """El host lee el nombre al arrancar: si la sala está vacía en el lobby se reinicia ya;
    si no, el nombre nuevo entra en el siguiente reinicio (al acabar la partida)."""
    nombre = _texto(nombre, 60)
    if not nombre:
        raise ValueError('falta el nombre')
    with cerrojo:
        sala = salas[sala_id]
        sala.renombrar(nombre)
    guardar_estado()
    if sala.fase == 'lobby' and sala.jugadores == 0:
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
    """Fuera de la sala ya (ban ip con la IP de loopback del socio) y vetado hasta que se readmita."""
    with cerrojo:
        sala = salas[sala_id]
        sala.vetos.add(str(socio_id))
    guardar_estado()
    j = _jugador(sala, socio_id)
    if j and j['ip']:
        sala.comando(f'ban ip {j["ip"]}')
    return bool(j)


def readmitir(sala_id, socio_id):
    with cerrojo:
        sala = salas[sala_id]
        sala.vetos.discard(str(socio_id))
    guardar_estado()


def silenciar(sala_id, socio_id, si=True):
    sala = salas[sala_id]
    j = _jugador(sala, socio_id)
    if not j or not j['clave']:
        raise ValueError('ese socio no está en la sala')
    sala.comando(f'set chat {"quickchat" if si else "allow"} {j["clave"]}')


def guardar_preset(slug, config):
    if not NOMBRE_PRESET.match(slug or ''):
        raise ValueError('nombre de preset no válido (minúsculas, números y guiones)')
    escribir_atomico(os.path.join(DIR_PRESETS, slug + '.json'), json.dumps(config, indent=1))


def borrar_preset(slug):
    if not NOMBRE_PRESET.match(slug or ''):
        raise ValueError('nombre de preset no válido')
    ruta = os.path.join(DIR_PRESETS, slug + '.json')
    if not os.path.isfile(ruta):
        raise KeyError(slug)
    os.remove(ruta)


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
    # Semilla de presets: solo los que falten; lo que haya en disco manda
    for nombre in os.listdir(PRESETS_SEMILLA):
        destino = os.path.join(DIR_PRESETS, nombre)
        if not os.path.exists(destino):
            shutil.copyfile(os.path.join(PRESETS_SEMILLA, nombre), destino)
    definidas = []
    if os.path.isfile(FICHERO_ESTADO):
        with open(FICHERO_ESTADO, encoding='utf-8') as f:
            definidas = json.load(f).get('salas', [])
    else:
        definidas = [{'id': f'sala{i + 1}', 'preset': p.strip()} for i, p in enumerate(SALAS_INICIALES[:MAX_SALAS])]
    for d in definidas[:MAX_SALAS]:
        try:
            if 'config' in d:
                salas[d['id']] = Sala(d['id'], d['config'], d.get('origen', 'personalizada'))
                salas[d['id']].dueno = d.get('dueno')
                salas[d['id']].vetos = set(str(v) for v in d.get('vetos', []))
            else:  # estado de antes de las salas a medida: solo el nombre del preset
                salas[d['id']] = Sala(d['id'], leer_preset(d['preset']), d['preset'])
        except (ValueError, KeyError) as e:
            log(f'sala {d} descartada: {e}')
    guardar_estado()


# -- bucles de fondo --
def bucle_vigilancia():
    ws = None
    fases_escritas = None
    while not parando:
        if ws is None or ws.poll() is not None:
            if ws is not None:
                log(f'el relé salió con código {ws.returncode}; relanzando')
            entorno = dict(os.environ, ARENA_FICHERO_TOKENS=FICHERO_TOKENS, ARENA_FICHERO_FASES=FICHERO_FASES,
                           ARENA_PUERTO_WS=str(PUERTO_WS), ARENA_REANUDAR_SEG=str(PAUSA_MAX_SEG))
            ws = subprocess.Popen(['/opt/arena-venv/bin/python', '/opt/arena/relay.py'], env=entorno)
        with cerrojo:
            actuales = list(salas.values())
        for s in actuales:
            try:
                s.vigilar()
                s.medir()
            except Exception as e:  # una sala rota no tumba al agente
                log(f'{s.id}: error vigilando: {e}')
        for s in actuales:
            if s.dueno and s.fase == 'lobby' and s.jugadores == 0:
                if time.time() - max(s._creada, getattr(s, '_ocupada', 0)) > SALA_SOCIO_CADUCA_SEG:
                    log(f'{s.id}: sala del socio {s.dueno} vacía {SALA_SOCIO_CADUCA_SEG} s; se cierra')
                    auditar('agente', 'caducar', {'sala': s.id, 'dueno': s.dueno})
                    try:
                        borrar_sala(s.id)
                    except KeyError:
                        pass
            elif s.jugadores > 0:
                s._ocupada = time.time()
        fases = ''.join(f'{s.id}: {s.fase}\n' for s in actuales)
        if fases != fases_escritas:
            escribir_atomico(FICHERO_FASES, fases)
            fases_escritas = fases
        time.sleep(2)
    if ws and ws.poll() is None:
        ws.terminate()


def bucle_poda():
    while not parando and PODA_DIAS > 0:
        limite = time.time() - PODA_DIAS * 86400
        borradas = 0
        for raiz, _, ficheros in os.walk(DIR_SALAS):
            if os.sep + 'replay' not in raiz:
                continue
            for nombre in ficheros:
                ruta = os.path.join(raiz, nombre)
                try:
                    if os.path.getmtime(ruta) < limite:
                        os.remove(ruta)
                        borradas += 1
                except OSError:
                    pass
        if borradas:
            log(f'poda: {borradas} replays de más de {PODA_DIAS} días')
        for _ in range(3600):
            if parando:
                return
            time.sleep(1)


def memoria_contenedor_mb():
    for ruta in ('/sys/fs/cgroup/memory.current', '/sys/fs/cgroup/memory/memory.usage_in_bytes'):
        try:
            with open(ruta) as f:
                return round(int(f.read()) / 1048576, 1)
        except (OSError, ValueError):
            continue
    return None


# -- API --
class Api(BaseHTTPRequestHandler):
    server_version = 'arena-salas'

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
        """Rutas públicas (las que el web del módulo deja pasar): el socio lo pone el nginx de UNIT3D en
        X-Arena-User. Para escribir se exige además X-Requested-With (corta CSRF: un formulario de otra
        web no puede mandar cabeceras propias ni JSON)."""
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
            # Si viene el socio (X-Arena-User), se le marca en qué salas está vetado
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
            self._json(200, {'salas': lista, 'max_salas': MAX_SALAS,
                             'memoria_contenedor_mb': memoria_contenedor_mb(),
                             'carga': os.getloadavg(), 'cpus': os.cpu_count()})
        elif partes == ['presets']:
            self._json(200, {'presets': listar_presets()})
        elif partes == ['catalogo']:
            self._json(200, {'mapas': catalogo['mapas'], 'ias': catalogo['ias'],
                             'dificultades': list(DIFICULTADES), 'facciones': list(FACCIONES)})
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
                nombre = _texto(cuerpo.get('nombre'), 60) or f'Sala de {socio.split(":", 1)[1]}'
                config.setdefault('challenge', {})['name'] = nombre
                contrasena = _texto(cuerpo.get('contrasena'), 30)
                if contrasena:
                    config['challenge']['gamePassword'] = contrasena
                sala = crear_sala(config, origen, dueno=socio)
                auditar(socio, 'crear-sala-socio', {'sala': sala.id, 'origen': origen, 'nombre': nombre})
                self._json(201, sala.publico())
            except (ValueError, json.JSONDecodeError) as e:
                self._json(400, {'error': str(e)})
            return
        if not self._autorizado():
            return
        try:
            cuerpo = self._cuerpo()
            if partes == ['salas']:
                # Desde un preset guardado, o a medida con un plan del panel (y opcionalmente guardarlo)
                if cuerpo.get('plan'):
                    config, origen = construir_config(cuerpo['plan']), 'personalizada'
                    if cuerpo.get('guardar_como'):
                        guardar_preset(str(cuerpo['guardar_como']), config)
                        origen = str(cuerpo['guardar_como'])
                else:
                    origen = str(cuerpo.get('preset', ''))
                    config = leer_preset(origen)
                    if cuerpo.get('nombre'):
                        config.setdefault('challenge', {})['name'] = _texto(cuerpo['nombre'], 60)
                sala = crear_sala(config, origen, cuerpo.get('id'))
                auditar(self._actor(), 'crear', {'sala': sala.id, 'origen': origen, **resumen(config)})
                self._json(201, sala.privado())
            elif len(partes) == 3 and partes[0] == 'salas' and partes[2] == 'nombre':
                cuando = renombrar_sala(partes[1], cuerpo.get('nombre'))
                auditar(self._actor(), 'renombrar', {'sala': partes[1], 'nombre': salas[partes[1]].nombre, 'cuando': cuando})
                self._json(200, {'ok': True, 'cuando': cuando})
            elif len(partes) == 3 and partes[0] == 'salas' and partes[2] in ('expulsar', 'readmitir', 'silenciar', 'desilenciar'):
                socio_id = str(cuerpo.get('socio', ''))
                if not socio_id.isdigit():
                    raise ValueError('falta el id del socio')
                if partes[2] == 'expulsar':
                    estaba = expulsar(partes[1], socio_id)
                    resultado = {'ok': True, 'estaba': estaba}
                elif partes[2] == 'readmitir':
                    readmitir(partes[1], socio_id)
                    resultado = {'ok': True}
                else:
                    silenciar(partes[1], socio_id, partes[2] == 'silenciar')
                    resultado = {'ok': True}
                auditar(self._actor(), partes[2], {'sala': partes[1], 'socio': socio_id})
                self._json(200, resultado)
            elif partes == ['presets']:
                slug = str(cuerpo.get('slug', ''))
                guardar_preset(slug, construir_config(cuerpo.get('plan') or {}))
                auditar(self._actor(), 'guardar-preset', {'preset': slug})
                self._json(201, {'ok': True})
            elif len(partes) == 3 and partes[0] == 'salas' and partes[2] == 'reiniciar':
                sala = salas[partes[1]]
                auditar(self._actor(), 'reiniciar', {'sala': sala.id})
                sala.parar()
                sala.espera = 2.0
                self._json(200, {'ok': True})
            elif len(partes) == 3 and partes[0] == 'salas' and partes[2] == 'aviso':
                sala = salas[partes[1]]
                mensaje = str(cuerpo.get('mensaje', ''))[:200]
                sala.comando('chat bcast ' + mensaje)
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
        elif len(partes) == 2 and partes[0] == 'presets':
            try:
                borrar_preset(partes[1])
                auditar(self._actor(), 'borrar-preset', {'preset': partes[1]})
                self._json(200, {'ok': True})
            except KeyError:
                self._json(404, {'error': 'no existe el preset'})
            except ValueError as e:
                self._json(400, {'error': str(e)})
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
    cargar_catalogo()
    cargar_estado()
    signal.signal(signal.SIGTERM, apagar)
    signal.signal(signal.SIGINT, apagar)
    threading.Thread(target=bucle_vigilancia, daemon=True).start()
    threading.Thread(target=bucle_poda, daemon=True).start()
    log(f'agente listo: {len(salas)} salas, tope {MAX_SALAS}, API :{PUERTO_API}, puente :{PUERTO_WS}')
    ThreadingHTTPServer(('0.0.0.0', PUERTO_API), Api).serve_forever()


if __name__ == '__main__':
    main()
