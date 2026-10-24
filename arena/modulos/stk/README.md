# Arena · SuperTuxKart — espiga superada (24-10-2026, ~05:45)

Carrera en red en el navegador contra un servidor nativo, por el relé común (`/dg`).

## Piezas
- **Cliente web:** el port de ading2210 (PR #5106, rama `wasm` de `ading2210/stk-code`), los ficheros compilados de su demo (`supertuxkart.pages.dev`, GPLv3). Commit y sha256 en `build/BASE`.
  - Rendimiento medido aquí: ~31 fps con texturas bajas (el mantenedor midió 6 en otro equipo).
  - Su JS parchea SOCKFS: `url = Module.websocket.url + "/" + host:puerto`, y solo abre WebSocket si `globalThis.config.ws_enabled`.
  - El relé limpia el sufijo del token (`sala1/127.0.0.1:2759` → `sala1`).
- **Shell propio** (`espiga/`, sin inline):
  - `pre.js` define `Module` antes del motor (`--connect-now=127.0.0.1:2759`, canvas, URL del relé) y `globalThis.config`;
  - `aplazar.js` retrasa `run()`;
  - `arena-stk.js` vuelca el `.tar.gz` de datos en flujo a `/data` (el mismo volcado con Cache Storage que en CS), monta IDBFS en `/home` y define `globalThis.sync_idbfs`, que el motor llama por EM_ASM.
  - Usa hilos: necesita aislamiento entre orígenes (COOP/COEP, lo tiene la Arena).
- **Servidor:** el mismo commit compilado nativo `-DSERVER_ONLY=ON` (`build/build-servidor.sh`; runtime: libcurl4t64, libssl3t64, libenet7). Los datos son los del cliente (texturas reducidas: el servidor no pinta).
  - `--lan-server=NOMBRE --port=2759 --max-players=8 --difficulty=1 --mode=0 --laps=3`.
  - Sin cuentas de STK: el cliente entra con `--connect-now`.

## Probado (local)
- Chrome → nginx → relé → servidor STK: lobby (7 ms, anfitrión), elección de kart, votación de pista, carrera.
- El servidor ve al socio en `127.10.0.3` (= `3:RawSmoke`). Servidor ~62 MB y ~8 % de CPU.

## Lecciones
- **Relé:** con `subprotocols=['binary']`, websockets 15 RECHAZA a quien no pide subprotocolo («missing subprotocol», 400). STK y el ArenaSocket de WZ no piden ninguno.
  - Arreglado con `select_subprotocol`: binary si lo ofrecen, si no nada.
  - Las imágenes de WZ arena10 llevan el relé de antes del cambio: no les afecta, pero el próximo build de WZ ya sale bien.
- El motor avisa «thread pool exhausted» (PTHREAD_POOL_SIZE corto), pero funciona.

## Pendiente para el módulo
- Agente (consola del servidor STK por stdin: listpeers/kick/kickban), presets (carrera normal, contrarreloj, batalla, fútbol, bandera; pistas), imágenes, compose, gateway y tarjeta.
- Nombre del jugador (sale «unnamed»): perfil por defecto con el nombre del socio.
