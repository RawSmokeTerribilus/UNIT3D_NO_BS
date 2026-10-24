# Arena NOBS

Juegos multijugador entre socios, en el navegador, como **módulos**: cada juego es un stack de contenedores propio que cuelga de `/arena-app/<juego>/`. UNIT3D solo pone la página, la puerta de acceso y el panel del staff.

Primer módulo: **Warzone 2100** (`modulos/wz2100/`), la Web Edition 4.7.0 parcheada para jugar en red contra un host nativo headless.

## Cómo encaja
```
/arena/<juego>  (Laravel, grupo auth + gaming.isolation; deja el pase arena_pase_hasta en sesión)
  └ iframe /arena-app/<juego>/ ─► nginx de UNIT3D: auth_request /__arena/whoami
                                    (204 + X-Arena-User | 401 → /arena), Cookie fuera, upgrade WS
        └─► arena-gateway (nginx; gateway/default.conf) ─► servicio web del módulo
```
- Red `arena` del compose: **interna** (sin salida a internet). Están en ella `web` (nginx de UNIT3D), `app` (habla con la API de control) y los servicios de la Arena.
- **El tracker no depende de la Arena:** nginx resuelve `arena-gateway` por variable, así que si la Arena está caída solo `/arena-app/` da 502.

## Warzone 2100
| Servicio | Imagen | Qué hace |
|---|---|---|
| `arena-wz2100-web` | `rawsmoke/nobs-arena-wz2100-web:<versión>` | cliente wasm, sala de espera, `/salas.json`, `/yo.json`, `/ws` → puente |
| `arena-wz2100-salas` | `rawsmoke/nobs-arena-wz2100-salas:<versión>` | agente de salas: lanza `warzone2100 --headless --autohost` (una sala = un proceso, puertos 2101…), el relé de sesiones reanudables (`?token=salaN` → su puerto; si el navegador se va, guarda la sesión hasta `ARENA_PAUSA_MAX_SEG`) y la API de control `:8081` |

- **El agente gestiona procesos, no contenedores: no se usa el socket de Docker.**
- **Al acabar una partida el host sale y el agente rearranca la sala.**
- **API de control** (cabecera `X-Arena-Token` = `ARENA_CONTROL_TOKEN`; `X-Arena-Actor` para la auditoría):
  - `GET /estado`, `GET /presets`, `GET /catalogo` (mapas e IA, leídos de `mp.wz`), `GET /salas/{id}/log?lineas=N`
  - `POST /salas {preset, nombre?}` o `{plan, guardar_como?}` (sala a medida: mapa, huecos humano/IA/cerrado con IA, dificultad, facción y equipo; alianzas, energía, bases, tecnología, chatarreros, espectadores, contraseña, bloqueo)
  - `POST /salas/{id}/nombre {nombre}` (si está vacía en el lobby se reinicia ya; si no, al acabar la partida), `POST /salas/{id}/reiniciar`, `POST /salas/{id}/aviso {mensaje}`, `DELETE /salas/{id}`, `POST /apagar-todo`
  - `POST /presets {slug, plan}`, `DELETE /presets/{slug}`
  - Cada sala guarda su configuración completa en `control/estado.json`; los presets son plantillas.
  - Sin token: `GET /publico` (lo que ve el lobby) y `GET /salud`.

### Persistencia (`arena-data/wz2100`, dueño 2100)
- `presets/`: se siembra desde la imagen si falta; lo del disco manda.
- `salas/<id>/`: configdir de cada sala (identidad del host, gamelogs, replays). El agente poda las replays de más de 14 días.
- `control/estado.json`: salas definidas.
- `control/acciones.jsonl`: quién hizo qué desde el panel.

### Variables (`.env`)
`ARENA_WZ2100_VERSION` (tag fijo, **nunca latest**), `ARENA_CONTROL_TOKEN` (lo genera `bin/arena-preparar.sh` si falta), `ARENA_MAX_SALAS`, `ARENA_SALAS_INICIALES`, `ARENA_PAUSA_MAX_SEG` (7200).

## Operación
- **Interruptor general:** Staff → Configuración → Arena (`arena.is_enabled`, nace apagado). Apagado, `/arena` dice «cerrada» y el `whoami` corta también a quien ya tenía el pase. Owners y admins entran igual para probar.
- **Salas:** Staff → Arena (`/dashboard/arena`).
  - Staff: ver, reiniciar una sala colgada, mandar un aviso al chat de la sala.
  - Admin: crear desde un preset, cerrar salas, apagar todo.
- **Despliegue:** `make install` / `make up` llaman a `bin/arena-preparar.sh` y levantan la Arena con las imágenes de Docker Hub. En el host no se compila nada.
- **Persistencia (sesiones reanudables):** si el navegador de un jugador se va (cambia de pestaña o de app, se le va la red), el relé mantiene su conexión con el host hasta `ARENA_PAUSA_MAX_SEG` (2 h). El cliente (`ArenaSocket`, en el `prejs.js` del parche) se reengancha solo al volver y recibe lo que se perdió. Mientras tanto la partida se queda en **pausa para todos** (lockstep de WZ), y el host avisa en el chat a los 15 s, luego cada 5 min y cada 15 s en el último minuto. La sesión va atada al socio (`X-Arena-User`). **En el lobby** (sala sin empezar) no hay nada que pausar: cerrar o recargar la pestaña libera el hueco al momento (el navegador avisa al relé en `pagehide`), y si alguien se cae, el relé lo suelta a los 60 s (`ARENA_REANUDAR_LOBBY_SEG`; el agente le pasa la fase de cada sala en `/tmp/arena/fases`). Así un enlace o una recarga sirven para volver a entrar mientras la partida no haya empezado.
- **Límites conocidos** (el lobby los avisa):
  - Cerrar o recargar la pestaña, o que el móvil la cierre por memoria, se pierde: el estado del juego vive en el navegador.
  - WZ 4.7.0 no deja volver a entrar en una partida empezada.
  - En pantallas pequeñas (móvil en horizontal) la barra inferior no cabe: WZ pide al menos 1024×768.
- **Origen propio (24-10):** los juegos se sirven desde `ARENA_ORIGEN` (staging `https://arena-nabs.rawsmoke.net`, prod `https://arena.rawsmoke.net`), nunca desde el origen del tracker (`/arena-app/` en NOBS da 404). Su código no puede tocar la página ni la sesión de NOBS: el navegador lo impide (`SecurityError`).
  - **Identidad:** `/arena/<slug>` firma un pase de 5 min (HMAC, `ARENA_PASE_SECRETO`, `App\Services\ArenaPase`) en la URL del iframe. La puerta de nginx (`auth_request` → `/__arena/pase`, ruta SIN sesión en `routes/arena.php`, para no crear una por fichero) lo cambia por la cookie `arena_pase` del origen de la Arena (`HttpOnly`, `Secure`, `SameSite=Lax`, dura `pase_horas`). Baneo y Arena cerrada se comprueban en cada petición (caché de 30 s).
  - **nginx de UNIT3D:** bloque `server` propio para el nombre de la Arena (el del tracker lleva `default_server`; si no, deja de responder a su propio nombre). En `auth_request`, `$arg_*` son los de la subpetición: el pase sale de `$request_uri`.
  - **Página padre:** la CSP efectiva es la de SecureHeaders (pisa la de gaming.isolation). `config/secure-headers.php` añade `ARENA_ORIGEN` a `child-src` y delega `cross-origin-isolated`, `fullscreen` y `autoplay`.
  - **Módulo:** `web.conf` es una plantilla (`ARENA_PADRE` = `APP_URL`, para `frame-ancestors`; conf.d en tmpfs porque la raíz es de solo lectura) y `Cross-Origin-Resource-Policy: same-site`.
- **Módulos:** `wz2100` (Warzone 2100, TCP con sesiones reanudables), `q3` (Quake 3 · OpenArena), `cs16` (Counter-Strike 1.6) y `stk` (SuperTuxKart), los tres últimos UDP por `/dg`; ver el README de cada uno. Cada uno trae imagen de salas + imagen web, su ruta en el gateway y su entrada en `config/arena.php`.
- **Relé común (`arena/comun/relay.py`)**, que copia en su imagen de salas cada módulo:
  - `/ws`: TCP con sesiones reanudables (WZ).
  - `/dg`: datagramas para juegos UDP (Quake 3, CS 1.6). Un mensaje WS = un datagrama, con un socket UDP por jugador desde su IP de loopback. Sin reanudación. Descarta la cabecera de puerto de Emscripten. El motivo de un cierre va en el código WS 4001 + texto. Tope de `ARENA_DG_MAX_POR_SEG` (500) mensajes por segundo por sesión.
  - Identidad, vetos y límites, iguales en los dos modos.
- **Identidad dentro de la sala:** el relé conecta a cada socio con el host desde su propia IP de loopback (`127.10+…`, sacada de su id) y deja el mapa en `/tmp/arena/socios.json`. El agente sabe qué socio es cada jugador (aunque se cambie el nombre en el lobby), lo anuncia en el chat de la sala («"X" es el socio Y de NOBS») y lo apunta en el log.
- **Moderación (Staff → Arena):** silenciar (solo chat rápido), expulsar (fuera ya con `ban ip` + veto en esa sala hasta readmitir: el relé rechaza al socio) y readmitir. El lobby marca «Vetado en esta sala».
- **Salas de socio:** cualquier socio crea una sala desde el lobby (preset + nombre + contraseña opcional). Una por socio, la cierra él o se cierra sola si nadie entra en 15 min (`ARENA_SALA_SOCIO_CADUCA_SEG`). Rutas públicas del agente (`/publico/presets`, `/publico/salas`) solo a través del web del módulo; exigen `X-Arena-User` (la pone el nginx de UNIT3D) y `X-Requested-With: arena` para escribir (corta CSRF).
- **Shell propio del cliente (sin recompilar):** `imagen-web/shell/rehacer_shell.py` rehace al armar la imagen el `index.html` del build.
  - Lo que hace: scripts inline → `arena-shell-N.js`; `on*=""` → `arena-eventos.js`; Bootstrap 5.3.2 servido desde `vendor/` (mismo SRI que cdnjs); textos en castellano; enlace GPL al parche; aviso «Fuera de la sala» con el motivo de los FIN del relé (`window.arenaFin`).
  - Sin PWA ni service worker: `service-worker.js` es un SW que se da de baja (para socios con el oficial registrado).
  - Regenera los `.gz`, que `gzip_static` serviría viejos.
- **CSP del módulo** (`web.conf`):
  - `script-src 'self' 'wasm-unsafe-eval'`;
  - `connect-src` SOLO a `https://$host/arena-app/wz2100/` y a su `/ws`: el JS del iframe no llega a `/api` de NOBS ni a terceros;
  - `style-src-attr 'unsafe-inline'` para los `style=""` del shell.
  - Riesgo residual: el iframe es del mismo origen y puede tocar `window.parent`.
- **Seguridad:** revisión en el vault (`Infrastructure/Arena/revision-seguridad-2026-10-23.md`).
  - Contenedores de la Arena con `cap_drop: ALL` (nginx solo recupera lo que necesita su master), `no-new-privileges` y raíz de solo lectura.
  - Tramas del relé de 256 KB como máximo.
  - `limit_req` de 10 r/s (ráfaga de 40) por IP en `/arena-app/`.
  - Límites de conexiones (operador, 24-10; generosos porque los socios recargarán y empezarán de cero):
    - relé: 6 sesiones a la vez por socio (`ARENA_MAX_SESIONES_SOCIO`), 10 nuevas por minuto (`ARENA_NUEVAS_POR_MINUTO`) y 80 en total entre todos los socios (`ARENA_MAX_SESIONES_TOTAL`; 8 salas × 10 jugadores).
    - Si un socio vuelve a entrar en una sala donde tiene una sesión huérfana (recargó la pestaña), la vieja se cierra: no ocupa hueco ni cupo.
    - nginx: 12 WebSockets abiertos a la vez por IP (`limit_conn`, solo cuentan las peticiones con Upgrade): 6 por socio × 2 socios en la misma casa.
    - El socio rechazado hoy ve el «Unable to join» genérico (el motivo solo sale en la consola); se arreglará con el shell propio.
- **Partida sin humanos:** si en plena partida no queda ningún humano conectado durante `ARENA_VACIA_REINICIO_SEG` (20 s), la sala se reinicia sola (si no, las IA seguirían jugando solas y nadie podría entrar). Quien está fuera con la sesión reanudable sigue contando como conectado.
- **Al acabar una partida** (también en un duelo en el que el rival abandona), el host espera `WZ_ARENA_QUIT_DELAY` segundos (20 por defecto; parche en `src/wzapi.cpp`) antes de salir. Así los clientes ven su resultado en vez de «host has left the game». Luego el agente rearranca la sala.

## Publicar una versión nueva del módulo
```
arena/modulos/wz2100/build/preparar_fuente.sh     # tag de WZ + parche
arena/modulos/wz2100/build/build_web.sh           # wasm (emsdk 4.0.10)
arena/modulos/wz2100/build/build_native.sh        # host nativo, mismo árbol
arena/bin/publicar-imagenes.sh <versión>          # contexto mínimo, escaneo de secretos, smoke test
arena/bin/publicar-imagenes.sh <versión> --subir  # sube SOLO ese tag a Docker Hub
```
Después: subir `ARENA_WZ2100_VERSION` en el `.env` y hacer `docker compose up -d arena-wz2100-web arena-wz2100-salas`.

## Licencia
Warzone 2100 es GPLv2. El parche de la Arena (`modulos/wz2100/parche/`) está publicado en este repo junto con la receta de build.
