// Arena NOBS · Counter-Strike 1.6 — shell del cliente (se empaqueta con esbuild en arena-cs.js; sin inline: CSP).
//  - Motor: Xash3D FWGS web (paquete xash3d-fwgs 1.2.2 de yohimik, copia en backups/manual/arena-cs16)
//    + cliente de CS reescrito (cs16-client) en wasm.
//  - Red: transporte WebSocket propio hacia el relé /dg (un mensaje = un datagrama), en lugar del
//    WebRTC de webXash (que exigía UDP directo al servidor, fuera del túnel).
//  - Datos (valve/ + cstrike/ recortados, de HLDS): un .tar.gz que se descomprime en flujo y se
//    vuelca al sistema de ficheros del motor; se guarda en la Cache Storage (versión = invalida).
import { Xash3D, Net } from 'xash3d-fwgs';

const q = new URLSearchParams(location.search);
const token = /^sala[0-9]{1,2}$/.test(q.get('token') || '') ? q.get('token') : 'sala1';
const raiz = location.pathname.replace(/cliente\/[^/]*$/, '');
const wsurl = `${location.protocol === 'https:' ? 'wss:' : 'ws:'}//${location.host}${raiz}dg?token=${token}`;
const BASE = '/rodir/';
// Resolución de render: como mucho ALTO px de alto (720 por defecto; ?alto=1080 para probar). El motor ve el
// canvas reducido (arena/comun/ajustar_resolucion.py, aplicado al JS del motor al publicar) y CSS lo estira.
const alto = (() => { const a = parseInt(q.get('alto') || '720', 10); return a >= 360 && a <= 2160 ? a : 720; })();
globalThis.arenaFactor = (objetivo, rect) => (objetivo === document.getElementById('canvas') && rect.height > alto ? alto / rect.height : 1);
const $ = (id) => document.getElementById(id);

function aviso(texto, error) {
  const d = $('detalle');
  d.textContent = texto;
  d.className = 'detalle' + (error ? ' error' : '');
}

function fueraDeLaSala(motivo) {
  if (document.exitPointerLock) { document.exitPointerLock(); }
  const portada = $('portada');
  portada.style.display = 'flex';
  portada.querySelector('h1').textContent = 'Fuera de la sala';
  $('portada-texto').textContent = motivo.charAt(0).toUpperCase() + motivo.slice(1) + '.';
  $('progreso').hidden = true;
  const b = $('jugar');
  b.textContent = 'Volver a la sala de espera';
  b.disabled = false;
  b.onclick = () => { location.href = raiz; };
  aviso('');
}

class Xash3DArena extends Xash3D {
  constructor(opts) {
    super(opts);
    this.net = new Net(this);
  }

  async init() {
    await Promise.all([super.init(), this.conectar()]);
  }

  conectar() {
    return new Promise((listo) => {
      const ws = new WebSocket(wsurl, 'binary');
      ws.binaryType = 'arraybuffer';
      ws.onopen = () => listo();
      ws.onmessage = (ev) => this.net.incoming.enqueue({ ip: [127, 0, 0, 1], port: 27015, data: ev.data });
      ws.onclose = (ev) => {
        if (ev.code === 4001) { fueraDeLaSala(ev.reason || 'el relé cerró la conexión'); }
      };
      ws.onerror = () => listo();
      this.ws = ws;
    });
  }

  sendto(paquete) {
    if (this.ws && this.ws.readyState === 1) { this.ws.send(paquete.data); }
  }
}

async function nombreSocio() {
  try {
    const d = await (await fetch(raiz + 'yo.json', { cache: 'no-store' })).json();
    const usuario = decodeURIComponent(String(d.socio || '').split(':').slice(1).join(':'));
    return usuario.replace(/[^A-Za-z0-9_\-]/g, '').slice(0, 24) || 'Socio';
  } catch (e) {
    return 'Socio';
  }
}

// Respuesta de los datos: de la Cache Storage si está la versión, si no de la red (y se guarda)
async function datos(config, alAvanzar) {
  const url = new URL(config.datos.src, location.href).href;
  const clave = `${url}?v=${config.version}`;
  let cache = null;
  try {
    cache = await caches.open('arena-cs');
    for (const req of await cache.keys()) {
      if (!req.url.endsWith(`?v=${config.version}`)) { await cache.delete(req); }
    }
    const guardada = await cache.match(clave);
    if (guardada) {
      return guardada.body.pipeThrough(new TransformStream({
        transform(trozo, ctl) { alAvanzar(trozo.length); ctl.enqueue(trozo); },
      }));
    }
  } catch (e) { cache = null; }
  // ?v=: URL inmutable por versión (nginx la sirve con caché de un año)
  const red = await fetch(clave);
  if (!red.ok) { throw new Error(`no se pudieron descargar los datos (${red.status})`); }
  let [paraJugar, paraGuardar] = cache ? red.body.tee() : [red.body, null];
  if (paraGuardar) {
    cache.put(clave, new Response(paraGuardar)).catch(() => {});
  }
  // Contador de bytes comprimidos para la barra
  return paraJugar.pipeThrough(new TransformStream({
    transform(trozo, ctl) { alAvanzar(trozo.length); ctl.enqueue(trozo); },
  }));
}

// tar (ustar) en flujo → ficheros en el FS del motor. Cola de trozos: cada byte se copia una sola vez
// (concatenar el búfer en cada lectura era cuadrático con ficheros grandes como halflife.wad).
// Cede el hilo de vez en cuando para que la página no se quede colgada mientras vuelca.
async function volcarTar(flujo, FS, alVolcar) {
  const lector = flujo.pipeThrough(new DecompressionStream('gzip')).getReader();
  const trozos = [];
  let disponibles = 0;
  let fin = false;
  const llenar = async (n) => {
    while (disponibles < n && !fin) {
      const { done, value } = await lector.read();
      if (done) { fin = true; break; }
      trozos.push(value);
      disponibles += value.length;
    }
    return disponibles >= n;
  };
  const tomar = (n) => {
    const sal = new Uint8Array(n);
    let puesto = 0;
    while (puesto < n) {
      const t = trozos[0];
      const cuantos = Math.min(t.length, n - puesto);
      sal.set(cuantos === t.length ? t : t.subarray(0, cuantos), puesto);
      puesto += cuantos;
      if (cuantos === t.length) { trozos.shift(); } else { trozos[0] = t.subarray(cuantos); }
    }
    disponibles -= n;
    return sal;
  };
  const descartar = (n) => {
    while (n > 0) {
      const t = trozos[0];
      if (t.length <= n) { n -= t.length; disponibles -= t.length; trozos.shift(); }
      else { trozos[0] = t.subarray(n); disponibles -= n; n = 0; }
    }
  };
  const texto = (b, i, n) => new TextDecoder().decode(b.subarray(i, i + n)).replace(/\0.*$/s, '');
  const dirs = new Set();
  let ficheros = 0;
  let ultimaCesion = performance.now();
  for (;;) {
    if (!(await llenar(512))) { break; }
    const cab = tomar(512);
    const nombre = texto(cab, 0, 100);
    if (!nombre) { break; }                                   // bloque vacío: fin del tar
    const prefijo = texto(cab, 345, 155);
    const ruta = (prefijo ? prefijo + '/' : '') + nombre;
    const tam = parseInt(texto(cab, 124, 12).trim() || '0', 8);
    const tipo = String.fromCharCode(cab[156] || 48);
    const relleno = Math.ceil(tam / 512) * 512;
    if (!(await llenar(relleno))) { throw new Error('tar cortado'); }
    if (tipo === '0' || tipo === '\0') {
      const destino = BASE + ruta;
      const dir = destino.slice(0, destino.lastIndexOf('/'));
      if (!dirs.has(dir)) { FS.mkdirTree(dir); dirs.add(dir); }
      FS.writeFile(destino, tomar(tam));
      descartar(relleno - tam);
      if (alVolcar) { alVolcar(++ficheros); }
    } else {
      descartar(relleno);
    }
    if (performance.now() - ultimaCesion > 50) {
      await new Promise((r) => setTimeout(r, 0));
      ultimaCesion = performance.now();
    }
  }
}

async function jugar() {
  const boton = $('jugar');
  boton.disabled = true;
  $('progreso').hidden = false;
  try {
    const [config, nombre] = await Promise.all([fetch('arena-cs.json', { cache: 'no-store' }).then((r) => r.json()), nombreSocio()]);
    const lib = (f) => new URL(f, location.href).href;
    const xash = new Xash3DArena({
      canvas: $('canvas'),
      arguments: ['-game', 'cstrike', '+_vgui_menus', '0', '-windowed', '-ref', 'webgl2', '+hud_scale', '2',
        '+name', nombre, '+cl_allowdownload', '0', '+connect', '127.0.0.1:27015'],
      libraries: {
        filesystem: lib('xash/filesystem_stdio.wasm'),
        xash: lib('xash/xash.wasm'),
        menu: lib('cs/menu_emscripten_wasm32.wasm'),
        client: lib('cs/client_emscripten_wasm32.wasm'),
        server: lib('cs/cs_emscripten_wasm32.wasm'),
        render: {},
      },
      filesMap: {
        'libref_webgl2.wasm': lib('xash/libref_webgl2.wasm'),
        'cl_dlls/client_emscripten_wasm32.wasm': lib('cs/client_emscripten_wasm32.wasm'),
        'cl_dlls/menu_emscripten_wasm32.wasm': lib('cs/menu_emscripten_wasm32.wasm'),
        'dlls/cs_emscripten_wasm32.wasm': lib('cs/cs_emscripten_wasm32.wasm'),
      },
      dynamicLibraries: ['filesystem_stdio.wasm', 'libref_webgl2.wasm', 'cl_dlls/client_emscripten_wasm32.wasm',
        'cl_dlls/menu_emscripten_wasm32.wasm', 'dlls/cs_emscripten_wasm32.wasm'],
    });
    await xash.init();
    const total = config.datos.size || 1;
    let hecho = 0;
    const flujo = await datos(config, (n) => {
      hecho += n;
      $('barra').style.width = `${Math.min(100, (100 * hecho) / total).toFixed(1)}%`;
      aviso(`Cargando Counter-Strike… ${(hecho / 1048576).toFixed(0)} / ${(total / 1048576).toFixed(0)} MB`);
    });
    await volcarTar(flujo, xash.em.FS, (n) => {
      if (n % 50 === 0) { aviso(`Cargando Counter-Strike… ${(hecho / 1048576).toFixed(0)} / ${(total / 1048576).toFixed(0)} MB · ${n} ficheros`); }
    });
    const extras = await fetch('xash/extras.pk3').then((r) => r.arrayBuffer());
    xash.em.FS.writeFile(BASE + 'extras.pk3', new Uint8Array(extras));
    xash.em.FS.chdir(BASE);
    $('portada').style.display = 'none';
    $('canvas').focus();
    xash.main();
  } catch (e) {
    console.error(e);
    aviso(`No se pudo arrancar: ${e.message}`, true);
    boton.disabled = false;
  }
}

$('jugar').addEventListener('click', jugar);
