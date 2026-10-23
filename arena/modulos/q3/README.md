# Arena · Quake 3 (OpenArena) — módulo en construcción

Espiga superada el 24-10-2026: OpenArena 0.8.8 jugable en el navegador contra un servidor
nativo, todo por el relé común de la Arena (`arena/comun/relay.py`, modo `/dg`).

## Piezas
- **Cliente:** ioquake3 upstream (commit en `parche/BASE`), compilado con emsdk 4.0.10
  (`emcmake cmake -S . -B build-web -DCMAKE_BUILD_TYPE=Release`; hace falta CMake ≥ 3.25: se usa
  la imagen de build de WZ, `arena/wz2100-webbuild:4.7.0`) + `parche/0001-arena-web-net.patch`.
  - El parche: en Emscripten `ioctl(FIONBIO)` da EINVAL y ioq3 tiraba el socket. Los de SOCKFS ya
    son no bloqueantes.
  - Sin hilos: no necesita aislamiento entre orígenes.
- **Shell propio** (`espiga/index.html`, `arena-q3.js`, `arena-q3.css`), sin inline:
  - `net_enabled 1` (el shell oficial la apaga);
  - `Module.websocket.url` = `/dg?token=salaN`;
  - carga los pk3 de `baseoa` y hace `+connect` a una IP cualquiera (SOCKFS manda todo a wsurl).
- **Servidor:** `ioq3ded` nativo del mismo commit (`-DBUILD_CLIENT=OFF -DBUILD_GAME_LIBRARIES=OFF
  -DBUILD_GAME_QVMS=OFF`; solo depende de libc y libm), con `com_basegame baseoa`.
  - Los QVM de juego son los de OpenArena (`pak6-patch088.pk3`).
  - Bots con `bot_minplayers`.
- **Contenido:** OpenArena 0.8.8 (GPLv2; zip oficial de tuxfamily, sha256 `5a8faf7f…0bac4a`).
  En la espiga van ~366 MB de `baseoa` (sin `pak2-players-mature`). **Pendiente: recortar** a lo
  que pidan los mapas de cada preset.

## Probado
- Chrome → nginx → relé `/dg` → `ioq3ded` (oa_dm1, 3 bots): entra, juega, muere a manos de un bot.
- El relé identifica al socio (`127.10.0.3 → 3:RawSmoke`) y el límite de 10 conexiones nuevas por
  minuto salta con los reintentos.
- Servidor: ~45 MB y ~4 % de CPU con 3 bots + 1 humano.

## Lecciones
- El relé tiene que aceptar el subprotocolo `binary` (lo piden los sockets de Emscripten; el
  navegador corta si el servidor no lo acepta).
- Cada reintento de conexión del cliente abre un WebSocket nuevo (getchallenge cada 3 s): cuenta
  para el límite de conexiones nuevas por minuto.

## Siguiente
Imagen de salas (agente: salas = procesos `ioq3ded`, presets FFA/TDM/CTF con bots) + imagen web
(cliente + shell + pk3 recortados), detrás del gateway en el origen propio de la Arena, y entrada
`q3` en `config/arena.php`.
