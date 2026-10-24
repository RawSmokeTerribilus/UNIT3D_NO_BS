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

## Módulo en staging (24-10, ~06:00, `git-arena2`)
- **Imágenes:** `rawsmoke/nobs-arena-stk-{web,salas}`. Se preparan con `build/preparar.sh` (descarga de la demo con sha256 de `BASE` + build del servidor) y se publican con `arena/bin/publicar-stk.sh`.
- **Agente** (calco del de Q3):
  - salas = `supertuxkart --lan-server --network-console` (UDP 2759+);
  - 6 presets por modo: carrera, carrera experto, contrarreloj, batalla, fútbol y bandera. La pista se vota en el lobby: las ~40 pistas están disponibles, la filosofía del museo.
  - Bots = proceso `--connect-now … --network-ai=N` desde 127.0.0.1, que se lanza al entrar un socio y se para a los 60 s sin humanos; `ai-handling` los retira según entran humanos.
  - `server_config.xml` propio: owner-less, `min-start-game-players=1` (por defecto 2, y los bots no cuentan: con un socio solo no arrancaba nunca), `start-game-counter=30`, sin validación.
  - Identidad por el log («New player X … from IP»), expulsar = listpeers + kickban + veto. Sin chat por consola: avisos y silenciar → 400 con explicación.
- **Cliente:** nombre del socio en el perfil (`players.xml` en IDBFS; sin él, el servidor lo veía como «unnamed») y aviso con el motivo del cierre 4001 (envoltorio de `WebSocket` en `pre.js`).
- **Probado en nabs:**
  - /arena con los cuatro juegos;
  - desde /arena/stk: lobby con «RawSmoke» y Bot 1–3, arranque solo tras la cuenta atrás, kart, votación y carrera en «Oliver's Math Class» contra los bots.

## Rendimiento (24-10, `git-arena4`)
Medido en batalla en red (Alien Signal), mismo equipo:

| Cliente | Render | fps |
|---|---|---|
| Demo (build de depuración) | 1920×927 (pantalla entera) | lo que el operador notó «injugable, se fumaba pulsaciones» |
| Demo | 1491×720 (`ajustar_motor.py`) | 19,7 |
| Demo | 994×479 (`?alto=480`, con bots) | 16,8 |
| **Build propia** (`build/build-web.sh`) | 1491×720 | **27,2** |

- **Cuello de botella:** la CPU, no la resolución. En red STK hace rollback y re-simula la física.
- **La demo es de depuración:** `-sASSERTIONS=1`, y su `build.sh` entrecomilla mal el tipo de build (`"${1:-'Release'}"` deja las comillas dentro y CMake no optimiza).
- **Build propia:** emsdk 4.0.10, Release de verdad, sin ASSERTIONS, `PTHREAD_POOL_SIZE=8`.
- **Tropiezos de su receta:** ragel (harfbuzz), embuilder sin `sdl2_image_jpg/png`, `CHECK_ASSETS=off`, reintentos de cmake (su CMakeLists avisa de que falla las primeras veces).
- **Su parche `force_wsproxy`** no encaja con el SOCKFS de 4.0.10: sin efecto, porque una URL completa en `Module.websocket.url` se usa tal cual.

## Pendiente
- Más rendimiento: probar `-msimd128` / LTO; ver si STK limita los fps en web.
- Medir con varios socios y fijar topes. El motor avisa «thread pool exhausted» (PTHREAD_POOL_SIZE de la build de la demo).
- Riesgo: el cliente es la build de una demo de terceros; si desaparece, compilar nosotros la rama `wasm` (hay receta en su `wasm/README.md`; los assets salen de stk-assets).
