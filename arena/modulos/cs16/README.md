# Arena · Counter-Strike 1.6 — espiga superada (24-10-2026)

CS 1.6 jugable en el navegador contra un servidor nativo, por el relé común (`arena/comun/relay.py`, `/dg`).

## Piezas
- **Servidor:** dedicado de **Xash3D FWGS** (64 bits, `waf configure -T release -d -8`) + la librería de servidor de
  **cs16-client** (ReGameDLL_CS 5.30, `cs_amd64.so`) + **yapb** (`yapb_amd64.so`, bots; falta engancharlo).
  Compilado desde la fuente con `build/build-nativo.sh` (commits en `build/BASE`).
  - El HLDS de Valve **no vale de servidor:** Xash3D solo entra en servidores GoldSrc con tickets de Steam
    (`connect ip:port gs` + steam-broker). Entre Xash3D y Xash3D no hace falta Steam.
- **Datos:** HLDS (SteamCMD anónimo, app 90, `mod cstrike`, 1.1.2.7).
  - El servidor usa `valve/` + `cstrike/` tal cual (sin las .so/.dll de Valve).
  - El cliente recibe un `.tar.gz` recortado: sin mapas, vídeos ni overviews de Half-Life, sin voces ni
    criaturas de HL, sin binarios de Valve; solo 7 mapas (de_dust2, de_inferno, de_nuke, de_train, de_aztec,
    cs_office, cs_italy). 358 MB → **206 MB comprimido**.
- **Cliente web:** el motor web de yohimik (`xash3d-fwgs` 1.2.2, deprecado en npm) + el cliente de CS en wasm
  (`cs16-client` 0.1.2, despublicado; sacado de la web de webXash). Copias con sha256 en
  `~/backups/manual/arena-cs16/`.
  - `espiga/arena-cs-main.js`, empaquetado con esbuild en `arena-cs.js` (el paquete usa imports sin extensión):
    - **transporte WebSocket propio** hacia `/dg` en vez del WebRTC de webXash, que exigía UDP directo al
      servidor (puerto abierto e IP real fuera del túnel);
    - el `.tar.gz` se descomprime en flujo (`DecompressionStream`) y se vuelca al FS del motor; Cache Storage;
    - aviso con el motivo de los cierres 4001 del relé.

## Probado (local)
Chrome → nginx → relé `/dg` → xash dedicado: carga, conecta a `de_dust2`, menú de equipos, juega como
antiterrorista con HUD completo. Una sola sesión WebSocket por jugador. Identidad `127.10.0.3 → 3:RawSmoke`.
Servidor ~170 MB / ~12 % CPU.

## Módulo en staging (24-10, `16-arena2`)
- **Imágenes:** `rawsmoke/nobs-arena-cs16-{web,salas}`.
  - Se preparan con `build/preparar.sh` (fuentes en los commits de `BASE`, build nativo, HLDS por SteamCMD, servidor y datos recortados, motor web desde las copias guardadas).
  - Se publican con `arena/bin/publicar-cs16.sh <versión> [--subir]`.
- **Agente** (`imagen-salas/agente.py`), calco del de Q3:
  - salas = procesos `xash` dedicados (UDP 27015+), datos en solo lectura (`-rodir /opt/cs16`) y `cstrike/` escribible por sala (con `dlls` y `filesystem_stdio.so` enlazados: yapb busca `./cstrike/dlls/cs_amd64.so`);
  - el `liblist.gam` del servidor tiene `gamedll_linux = dlls/yapb.so` (yapb carga ReGameDLL);
  - bots con `yb_quota` en modo fill, que se retiran sin humanos;
  - 7 presets (dust2, inferno, nuke, train, aztec, office, italy);
  - expulsar = `addip 0` + `kick #userid` + veto en el relé.
- **CSP con `'unsafe-eval'`** (EM_ASM del motor web).
- **Probado en nabs:**
  - entra desde `/arena/cs16` en ~30 s la primera vez;
  - identidad detectada y bots despiertos;
  - unos 140 MB y ~17 % de CPU por sala con 1 humano y bots.
- **Lecciones:**
  - El cliente necesita el `liblist.gam` **original**. Con el del servidor, el motor web intenta cargar yapb, lanza un `alert()` y bloquea la página; la automatización lo ve como «página ocupada». Se descubrió con una captura del operador.
  - El volcado del tar tiene que usar una cola de trozos: concatenar el búfer era cuadrático.
  - Al probar en local por http, la CSP con `https://$host` bloquea los fetch: hay que sobreescribirla en el frente de prueba.

## Pendiente para el módulo
- Nombres de los bots en castellano (`addons/yapb/conf/names.cfg`); vigilar que yapb no rote bots de más al entrar humanos.
- Medir con salas llenas y fijar topes a la mitad.
- **Riesgo:** nadie mantiene el motor web; si el CS se queda, tocará compilar nosotros xash3d-fwgs + cs16-client
  a wasm.
- **Lista de ficheros que falten** (para el operador): ninguno por ahora; HLDS trae el 1.6 completo.

## Resolución de render: probado y VUELTO ATRÁS (24-10)
- Se aplicó `arena/comun/ajustar_resolucion.py` (el que sirve para STK) al JS del motor Xash: el buffer quedaba a 1491×720.
- El operador: «muy roto, he perdido la cruz que marca la posición del arma y va fatal, iba mejor antes».
- Vuelto a `16-arena4`. Sospecha, sin confirmar: Xash compara el tamaño del canvas con el de su ventana al dibujar el HUD/la mira o al redimensionar, y con el tamaño escalado entra en desajuste (¿bucle de cambios de modo?).
- Si se retoma: probar con la resolución propia del motor (`-width/-height`, `vid_mode`) y ventana NO redimensionable, como en Q3, en vez de escalar el tamaño CSS.
- **Después (16-arena6):** el ajuste queda **opcional**. Por defecto está apagado (factor 1 = igual que sin parche). Con `?alto=720` en la URL del cliente se activa y se guarda en el navegador (`localStorage: arena-cs-alto`); `?alto=0` lo quita.
  - Sin pantalla completa no se reproduce la rotura: la mira sale bien con 1491×720.
  - Solo se puede probar en las condiciones del operador (pantalla completa en el iframe, ratón capturado, Keyboard Lock).

