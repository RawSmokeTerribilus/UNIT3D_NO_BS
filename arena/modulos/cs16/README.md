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

## Pendiente para el módulo
- **CSP:** el JS de Emscripten hace `eval` del EM_ASM de los módulos laterales → seguramente `'unsafe-eval'`
  (asumible: la Arena vive en su propio origen).
- **Bots yapb**, el agente de salas (calco del de Q3: `status`, `kick`, `addip`), presets por mapa, imágenes y
  compose, como Q3.
- **Riesgo:** nadie mantiene el motor web; si el CS se queda, tocará compilar nosotros xash3d-fwgs + cs16-client
  a wasm.
- **Lista de ficheros que falten** (para el operador): ninguno por ahora; HLDS trae el 1.6 completo.
