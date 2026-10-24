// El motor arranca main() al cargar; se aplaza hasta que el shell haya volcado los datos (como el index de la demo).
globalThis.ready = false;
var arenaRunReal = run;
run = function () {
  if (globalThis.ready) { arenaRunReal(); }
  globalThis.ready = true;
};
