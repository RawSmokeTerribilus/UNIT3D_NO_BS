// Arena NOBS · Quake 3 (OpenArena) — shell de la espiga. Sin inline (CSP).
// Todo socket UDP del juego va a wsurl (relé /dg: un mensaje WS = un datagrama).
const q = new URLSearchParams(location.search);
const wsProto = location.protocol === 'https:' ? 'wss:' : 'ws:';
const base = location.pathname.replace(/[^/]*$/, '');
const token = q.get('token') || 'sala1';
const wsurl = q.get('wsurl') || `${wsProto}//${location.host}${base}dg?token=${encodeURIComponent(token)}`;
const nombre = (q.get('nombre') || 'Socio').replace(/[^A-Za-z0-9_\-]/g, '').slice(0, 20) || 'Socio';
const estado = document.getElementById('estado');

const args = [
  '+set', 'net_enabled', '1', '+set', 'com_basegame', 'baseoa', '+set', 'fs_game', '',
  '+set', 'r_mode', '-2', '+set', 'sv_pure', '0', '+set', 'cl_allowDownload', '0',
  '+set', 'name', nombre, '+connect', '10.21.0.1:27960',
];

const config = await fetch('ioquake3-config.json').then(r => r.json());
const ioquake3 = (await import('./ioquake3.js')).default;
ioquake3({
  canvas: document.getElementById('canvas'),
  arguments: args,
  websocket: { url: wsurl, subprotocol: 'binary' },
  locateFile: (f) => f,
  print: (t) => console.log(t),
  printErr: (t) => console.warn(t),
  preRun: [async (module) => {
    module.addRunDependency('arena-fs');
    try {
      const files = config.baseoa.files;
      let hechos = 0;
      for (const f of files) {
        const r = await fetch(f.src);
        if (!r.ok) continue;
        const datos = new Uint8Array(await r.arrayBuffer());
        module.FS.mkdirTree(f.dst);
        module.FS.writeFile(`${f.dst}/${f.src.split('/').pop()}`, datos);
        estado.textContent = `Cargando OpenArena… ${++hechos}/${files.length}`;
      }
      estado.remove();
    } finally {
      module.removeRunDependency('arena-fs');
    }
  }],
});
