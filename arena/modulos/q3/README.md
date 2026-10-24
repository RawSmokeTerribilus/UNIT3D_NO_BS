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

## Módulo en staging (24-10, `oa088-arena1`)
- **Imágenes:** `rawsmoke/nobs-arena-q3-{web,salas}`. Se construyen con `arena/bin/publicar-q3.sh <versión> [--subir]` (contexto mínimo, escaneo de secretos, smoke test, nunca latest). Entradas: `build/preparar_fuente.sh` + `build/build.sh`.
- **Agente** (`imagen-salas/agente.py`), mismo contrato de API que el de WZ (panel y lobby sin cambios):
  - salas = procesos `ioq3ded` (UDP 27960+ en 127.0.0.1); presets `todos-contra-todos` (oa_dm1), `duelo` (czest1tourney), `por-equipos` (oa_dm4), `captura-la-bandera` (oa_ctf2);
  - identidad por el `status` del servidor + `socios.json` del relé, con anuncio en el chat;
  - bots con `bot_minplayers`, que se retiran a los 60 s sin humanos y vuelven al entrar alguien;
  - expulsar = `addip` + `clientkick` + veto en el relé; readmitir = `removeip`; silenciar no existe en Q3 (400 con explicación);
  - sin creador a medida (el panel lo oculta: no hay `/catalogo`).
- **Cliente** (`imagen-web/cliente`): botón «Jugar» (gesto para el audio y la captura del ratón); pk3 en la Cache Storage del navegador (la versión invalida la caché vieja; la segunda vez carga en segundos); nombre del socio desde `yo.json`; aviso «Fuera de la sala» con el motivo del cierre 4001 del relé (envoltorio de `WebSocket`).
- **Probado en nabs (arena-nabs):**
  - entra y juega desde `/arena/q3`;
  - identidad detectada (`127.10.0.3 → 3:RawSmoke`) y los bots se despiertan al entrar;
  - expulsar → «WAS KICKED»; al volver, el lobby marca «Vetado» y el cliente avisa «el staff te ha vetado»;
  - ~40 MB y ~2 % de CPU por sala.
- **Pendiente:**
  - recortar los pk3 (hoy ~370 MB la primera vez);
  - el cliente abre DOS WebSockets por jugador (gasta 2 de las 6 sesiones por socio): averiguar por qué (¿dos peers en SOCKFS?);
  - medir con salas llenas y fijar topes a la mitad.

## Siguiente
Imagen de salas (agente: salas = procesos `ioq3ded`, presets FFA/TDM/CTF con bots) + imagen web
(cliente + shell + pk3 recortados), detrás del gateway en el origen propio de la Arena, y entrada
`q3` en `config/arena.php`.
