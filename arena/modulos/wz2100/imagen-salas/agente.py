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
PUERTO_WS = 8080
PUERTO_API = 8081

DIR_PRESETS = os.path.join(DATOS, 'presets')
DIR_SALAS = os.path.join(DATOS, 'salas')
DIR_CONTROL = os.path.join(DATOS, 'control')
FICHERO_ESTADO = os.path.join(DIR_CONTROL, 'estado.json')
FICHERO_ACCIONES = os.path.join(DIR_CONTROL, 'acciones.jsonl')
FICHERO_TOKENS = '/tmp/arena/tokens'
FICHERO_FASES = '/tmp/arena/fases'   # salaN: lobby|partida|… — el relé suelta antes las sesiones caídas en el lobby

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


def huecos_humanos(preset):
    """Jugadores humanos que necesita el preset (maxPlayers menos las IA fijadas)."""
    reto = preset.get('challenge', {})
    total = int(reto.get('maxPlayers', 2))
    ias = sum(1 for i in range(total) if preset.get(f'player_{i}', {}).get('ai'))
    return max(1, total - ias)


class Sala:
    def __init__(self, sala_id, preset):
        self.id = sala_id
        self.preset = preset
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
        info = leer_preset(preset)
        self.nombre = info.get('challenge', {}).get('name', sala_id)
        self.huecos = huecos_humanos(info)

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
        shutil.copyfile(os.path.join(DIR_PRESETS, self.preset + '.json'),
                        os.path.join(self.dir, 'autohost', self.preset + '.json'))
        args = [WZ_BIN, f'--autohost={self.preset}.json', f'--gameport={self.puerto}',
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
                'jugadores': self.jugadores, 'huecos': self.huecos}

    def privado(self):
        d = self.publico()
        d.update({'nombres': self.nombres, 'puerto': self.puerto, 'pid': self.proc.pid if self.proc and self.proc.poll() is None else None,
                  'arranques': self.arranques, 'cpu': round(self.cpu, 1), 'rss_mb': round(self.rss_mb, 1),
                  'desde': int(self.ultimo_arranque)})
        return d


# -- estado persistente --
def guardar_estado():
    with cerrojo:
        datos = {'salas': [{'id': s.id, 'preset': s.preset} for s in salas.values()]}
    escribir_atomico(FICHERO_ESTADO, json.dumps(datos, indent=1))
    escribir_tokens()


def escribir_tokens():
    with cerrojo:
        lineas = ''.join(f'{s.id}: 127.0.0.1:{s.puerto}\n' for s in salas.values())
    escribir_atomico(FICHERO_TOKENS, lineas)


def crear_sala(preset, sala_id=None):
    with cerrojo:
        if len(salas) >= MAX_SALAS:
            raise ValueError(f'tope de salas alcanzado ({MAX_SALAS})')
        if sala_id is None:
            libres = [f'sala{n}' for n in range(1, MAX_SALAS + 1) if f'sala{n}' not in salas]
            sala_id = libres[0]
        if not ID_SALA.match(sala_id) or sala_id in salas:
            raise ValueError('id de sala no válido o ya en uso')
        sala = Sala(sala_id, preset)
        salas[sala_id] = sala
    guardar_estado()
    sala.arrancar()
    return sala


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
            salas[d['id']] = Sala(d['id'], d['preset'])
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
            with cerrojo:
                self._json(200, {'salas': [s.publico() for s in salas.values()]})
            return
        if partes == ['salud']:
            self._json(200, {'ok': True})
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
            nombres = sorted(n[:-5] for n in os.listdir(DIR_PRESETS) if n.endswith('.json'))
            self._json(200, {'presets': nombres})
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
        if not self._autorizado():
            return
        partes = [p for p in urlparse(self.path).path.split('/') if p]
        try:
            cuerpo = self._cuerpo()
            if partes == ['salas']:
                sala = crear_sala(str(cuerpo.get('preset', '')), cuerpo.get('id'))
                auditar(self._actor(), 'crear', {'sala': sala.id, 'preset': sala.preset})
                self._json(201, sala.privado())
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
        if not self._autorizado():
            return
        partes = [p for p in urlparse(self.path).path.split('/') if p]
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
    threading.Thread(target=bucle_poda, daemon=True).start()
    log(f'agente listo: {len(salas)} salas, tope {MAX_SALAS}, API :{PUERTO_API}, puente :{PUERTO_WS}')
    ThreadingHTTPServer(('0.0.0.0', PUERTO_API), Api).serve_forever()


if __name__ == '__main__':
    main()
