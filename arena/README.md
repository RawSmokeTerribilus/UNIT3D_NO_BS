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
  - `GET /estado`, `GET /presets`, `GET /salas/{id}/log?lineas=N`
  - `POST /salas {preset}`, `POST /salas/{id}/reiniciar`, `POST /salas/{id}/aviso {mensaje}`, `DELETE /salas/{id}`, `POST /apagar-todo`
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
- **Persistencia (sesiones reanudables):** si el navegador de un jugador se va (cambia de pestaña o de app, se le va la red), el relé mantiene su conexión con el host hasta `ARENA_PAUSA_MAX_SEG` (2 h). El cliente (`ArenaSocket`, en el `prejs.js` del parche) se reengancha solo al volver y recibe lo que se perdió. Mientras tanto la partida se queda en **pausa para todos** (lockstep de WZ), y el host avisa en el chat a los 15 s, luego cada 5 min y cada 15 s en el último minuto. La sesión va atada al socio (`X-Arena-User`).
- **Límites conocidos** (el lobby los avisa):
  - Cerrar o recargar la pestaña, o que el móvil la cierre por memoria, se pierde: el estado del juego vive en el navegador.
  - WZ 4.7.0 no deja volver a entrar en una partida empezada.
  - En pantallas pequeñas (móvil en horizontal) la barra inferior no cabe: WZ pide al menos 1024×768.
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
