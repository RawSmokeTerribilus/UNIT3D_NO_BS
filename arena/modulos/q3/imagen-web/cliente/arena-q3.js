// Arena NOBS · Quake 3 (OpenArena) — shell propio del cliente (sin inline: CSP).
//  - Todo socket UDP del juego va al relé /dg del módulo (SOCKFS: un mensaje WS = un datagrama).
//  - Los pk3 se guardan en la Cache Storage del navegador: la primera vez se descargan (~370 MB),
//    las siguientes salen de ahí. La versión (ioquake3-config.json) invalida la caché vieja.
//  - Si el relé cierra con 4001 (vetado, límites, sala cerrada…), se enseña el motivo.
const q = new URLSearchParams(location.search);
const token = /^sala[0-9]{1,2}$/.test(q.get('token') || '') ? q.get('token') : 'sala1';
const raiz = location.pathname.replace(/cliente\/[^/]*$/, '');            // …/arena-app/q3/
const wsurl = `${location.protocol === 'https:' ? 'wss:' : 'ws:'}//${location.host}${raiz}dg?token=${token}`;
const $ = (id) => document.getElementById(id);

function aviso(texto, error) {
  const d = $('detalle');
  d.textContent = texto;
  d.className = 'detalle' + (error ? ' error' : '');
}

// Motivo de cierre del relé (código 4001): el juego solo vería que no contesta nadie
const WebSocketOriginal = window.WebSocket;
window.WebSocket = class extends WebSocketOriginal {
  constructor(url, protocolos) {
    super(url, protocolos);
    this.addEventListener('close', (ev) => {
      if (ev.code === 4001) { fueraDeLaSala(ev.reason || 'el relé cerró la conexión'); }
    });
  }
};

function fueraDeLaSala(motivo) {
  if (document.exitPointerLock) { document.exitPointerLock(); }
  const portada = $('portada');
  portada.hidden = false;
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

async function nombreSocio() {
  try {
    const d = await (await fetch(raiz + 'yo.json', { cache: 'no-store' })).json();
    const usuario = decodeURIComponent(String(d.socio || '').split(':').slice(1).join(':'));
    return usuario.replace(/[^A-Za-z0-9_\-]/g, '').slice(0, 24) || 'Socio';
  } catch (e) {
    return 'Socio';
  }
}

async function pk3(f, cache, version, alAvanzar) {
  const url = new URL(f.src, location.href).href;
  const clave = `${url}?v=${version}`;
  let r = cache ? await cache.match(clave) : null;
  if (!r) {
    // ?v=: URL inmutable por versión (nginx la sirve con caché de un año)
    const red = await fetch(clave);
    if (!red.ok) { throw new Error(`no se pudo descargar ${f.src} (${red.status})`); }
    // Leer en trozos para la barra de progreso
    const lector = red.body.getReader();
    const trozos = [];
    for (;;) {
      const { done, value } = await lector.read();
      if (done) { break; }
      trozos.push(value);
      alAvanzar(value.length);
    }
    const blob = new Blob(trozos);
    if (cache) {
      try { await cache.put(clave, new Response(blob)); } catch (e) { /* sin sitio: se juega igual */ }
    }
    return new Uint8Array(await blob.arrayBuffer());
  }
  const datos = new Uint8Array(await r.arrayBuffer());
  alAvanzar(datos.length);
  return datos;
}

async function jugar() {
  const boton = $('jugar');
  boton.disabled = true;
  $('progreso').hidden = false;
  try {
    const [config, nombre] = await Promise.all([fetch('ioquake3-config.json', { cache: 'no-store' }).then((r) => r.json()), nombreSocio()]);
    const files = config.baseoa.files;
    const total = files.reduce((n, f) => n + (f.size || 0), 0);
    let hecho = 0;
    const alAvanzar = (n) => {
      hecho += n;
      $('barra').style.width = `${Math.min(100, (100 * hecho) / (total || 1)).toFixed(1)}%`;
      aviso(`Cargando OpenArena… ${(hecho / 1048576).toFixed(0)} / ${(total / 1048576).toFixed(0)} MB`);
    };
    let cache = null;
    try {
      cache = await caches.open('arena-q3');
      // Fuera las versiones viejas
      for (const req of await cache.keys()) {
        if (!req.url.endsWith(`?v=${config.version}`)) { await cache.delete(req); }
      }
    } catch (e) { cache = null; }

    const ioquake3 = (await import('./ioquake3.js')).default;
    await ioquake3({
      canvas: $('canvas'),
      arguments: ['+set', 'net_enabled', '1', '+set', 'com_basegame', 'baseoa', '+set', 'fs_game', '',
        '+set', 'r_mode', '-2', '+set', 'sv_pure', '0', '+set', 'cl_allowDownload', '0',
        '+set', 'name', nombre,
        // F10 abre el menú (en Firefox, Esc lo usa el navegador para salir de pantalla completa)
        '+bind', 'F10', 'togglemenu',
        '+connect', '10.21.0.1:27960'],
      websocket: { url: wsurl, subprotocol: 'binary' },
      locateFile: (f) => f,
      print: (t) => console.log(t),
      printErr: (t) => console.warn(t),
      preRun: [async (module) => {
        module.addRunDependency('arena-pk3');
        try {
          for (const f of files) {
            const datos = await pk3(f, cache, config.version, alAvanzar);
            module.FS.mkdirTree(f.dst);
            module.FS.writeFile(`${f.dst}/${f.src.split('/').pop()}`, datos);
          }
          $('portada').style.display = 'none';
          $('canvas').focus();
        } catch (e) {
          aviso(e.message, true);
          throw e;
        } finally {
          module.removeRunDependency('arena-pk3');
        }
      }],
    });
  } catch (e) {
    aviso(`No se pudo arrancar: ${e.message}`, true);
    boton.disabled = false;
  }
}

$('jugar').addEventListener('click', jugar);
