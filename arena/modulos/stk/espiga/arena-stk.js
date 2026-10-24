// Arena NOBS · SuperTuxKart — shell del cliente (sin inline). Datos en .tar.gz volcados en flujo a /data
// (Cache Storage + URL versionada); IDBFS en /home para el perfil del jugador.
const BASE = '/data/';
const $ = (id) => document.getElementById(id);
function aviso(texto, error) { const d = $('detalle'); d.textContent = texto; d.className = 'detalle' + (error ? ' error' : ''); }
// Respuesta de los datos: de la Cache Storage si está la versión, si no de la red (y se guarda)
async function datos(config, alAvanzar) {
  const url = new URL(config.datos.src, location.href).href;
  const clave = `${url}?v=${config.version}`;
  let cache = null;
  try {
    cache = await caches.open('arena-stk');
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
      const destino = BASE + ruta.replace(/^\.\//, '');
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


// El motor llama a globalThis.sync_idbfs() (EM_ASM) para guardar el perfil
let sincronizando = false;
globalThis.sync_idbfs = () => {
  if (sincronizando) { return; }
  sincronizando = true;
  FS.syncfs(false, () => { sincronizando = false; });
};

function montarHome() {
  return new Promise((ok) => {
    try { FS.mkdir('/home'); } catch (e) {}
    FS.mount(IDBFS, {}, '/home');
    FS.syncfs(true, () => ok());
  });
}

async function jugar() {
  $('jugar').disabled = true;
  $('progreso').hidden = false;
  try {
    const config = await fetch('arena-stk.json', { cache: 'no-store' }).then((r) => r.json());
    const total = config.datos.size || 1;
    let hecho = 0;
    const flujo = await datos(config, (n) => {
      hecho += n;
      $('barra').style.width = `${Math.min(100, (100 * hecho) / total).toFixed(1)}%`;
      aviso(`Cargando SuperTuxKart… ${(hecho / 1048576).toFixed(0)} / ${(total / 1048576).toFixed(0)} MB`);
    });
    await volcarTar(flujo, FS);
    await montarHome();
    $('portada').style.display = 'none';
    $('canvas').focus();
    run();
    setInterval(globalThis.sync_idbfs, 30000);
  } catch (e) {
    console.error(e);
    aviso(`No se pudo arrancar: ${e.message}`, true);
    $('jugar').disabled = false;
  }
}

const espera = setInterval(() => {
  if (globalThis.ready) { clearInterval(espera); $('jugar').disabled = false; }
}, 100);
$('jugar').addEventListener('click', jugar);
