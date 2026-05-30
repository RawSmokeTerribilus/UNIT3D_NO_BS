# Banco Forense y de Recuperación de UNIT3D

Un laboratorio de recuperación permanente, apagado por defecto, que vive junto a
un stack de producción de UNIT3D dockerizado. Existe para que el próximo momento
de "la base de datos parece vacía" sea un flujo repetible de 5 minutos en vez de
una improvisación a las 3 de la mañana.

Se construyó tras un incidente real en el que producción apareció prácticamente
vacía en pleno registro abierto, y la recuperación hubo que improvisarla con un
lab desechable. Este banco hace que esa recuperación sea aburrida, aislada y
rápida.

## Arranque rápido (en frío)

Desde la raíz del proyecto UNIT3D, con un layout estándar (sin configurar nada):

```sh
bin/forensics/lab-up.sh                                  # despierta el banco (la 1ª vez baja ~764MB)
bin/forensics/lab-restore.sh                             # último dump + replay de binlogs hasta HEAD
bin/forensics/lab-diff.sh users topics posts comments torrents   # ¿qué le falta a prod? (lab_only)
bin/forensics/lab-down.sh                                # duérmelo (libera RAM)
```

Notas para la primera ejecución:
- El primer `lab-up` baja la imagen del toolbox (~764MB) o la construye — no es instantáneo.
- Leer binlogs/datadir de prod usa el mismo acceso sudo/docker que el resto del stack.
- Los scripts encuentran la raíz del proyecto desde su propia ubicación — ejecútalos desde cualquier directorio.
- Si tus rutas difieren de las por defecto: `cp forensics/forensics.env.example forensics/forensics.env` y edita.

## Qué hace

- **Recuperación híbrida a un punto en el tiempo (PITR):** restaura el dump SQL más
  reciente en un MySQL de laboratorio aislado, y luego reproduce los binlogs hacia
  delante para rellenar el hueco entre el dump y cualquier instante objetivo — sin
  tocar producción.
- **Diffing autoritativo:** compara cualquier tabla recuperada en el lab contra la
  producción actual, por clave primaria, para encontrar exactamente qué filas le
  faltan a producción.
- **Export quirúrgico:** emite `INSERT IGNORE` solo para las filas que faltan, para
  que las revises y las apliques a producción de forma deliberada. El banco nunca
  escribe en prod.
- **Validación de torrents:** comprueba que la fila de BD de un torrent, su fichero
  `.torrent` almacenado y el info_hash recalculado coinciden — el triaje típico de
  "Infohash not found".

## Modelo de seguridad

El banco está diseñado para que sea imposible confundirlo con producción:

- **Apagado por defecto.** Ambos servicios están detrás de un perfil `forensics` de
  Compose y viven en un fichero Compose aparte con un nombre de proyecto aparte. Un
  `docker compose up` normal de prod nunca los arranca — cero RAM/CPU en reposo.
- **Air-gapped.** La red del lab es `internal: true`: sin salida a internet y sin
  ruta a la BD de producción. Nada puede filtrarse ni alcanzar prod por accidente.
- **Solo lectura sobre prod.** Cada ruta de producción (binlogs, backups, ficheros
  `.torrent`, redis, meilisearch) se monta `:ro`. El banco no puede modificar datos
  de producción.
- **Datadir separado.** El MySQL del lab usa su propio datadir, nunca el de prod.
- **Sin escalada de privilegios en prod.** La coordenada PITR del binlog se captura
  desde el sistema de ficheros (el binlog más nuevo + su tamaño en bytes = la
  posición de escritura), así que no necesita ningún grant especial de MySQL. El
  replay del lab usa `--force`; `lab-diff` es la comprobación autoritativa de lo que
  realmente le falta a prod.

## Requisitos

- Un stack UNIT3D dockerizado con MySQL 8.0 y binary logging activado (el default).
- Un job de dump regular que escriba `*.sql.gz` más un sidecar `*.sql.gz.binlogpos`
  (ver `bin/db-backup-regular.sh`, que captura la coordenada sin privilegio de BD).
- Una cuenta MySQL de solo lectura para el diffing (por defecto `backupbot`).
- Docker + Docker Compose v2.

## Configuración

Toda la configuración va por variables de entorno con defaults razonables para un
layout estándar de `UNIT3D_Docker`, y cada ruta es relativa a la raíz del proyecto.

```sh
cp forensics/forensics.env.example forensics/forensics.env
# edita forensics/forensics.env si tu layout difiere (imagen, nombres, rutas, password del lab)
```

`forensics/forensics.env` está gitignored (puede contener el password local del
lab). El `.example` documenta cada parámetro.

## Uso

Todos los scripts viven en `bin/forensics/` y se ejecutan desde el host.

```sh
bin/forensics/lab-up.sh            # arranca el banco (baja/construye la imagen del toolbox si hace falta)

bin/forensics/lab-restore.sh                         # último dump + replay hasta HEAD
bin/forensics/lab-restore.sh --until "2026-05-09 23:59:00"   # PITR a un instante
bin/forensics/lab-restore.sh --dump <path> --no-replay       # solo snapshot

bin/forensics/lab-diff.sh users topics posts comments torrents   # lab vs prod, por id
bin/forensics/lab-export.sh comments                 # INSERT IGNORE de las filas que le faltan a prod

bin/forensics/torrent-validate.sh 3754               # fila BD <-> .torrent <-> info_hash
bin/forensics/torrent-validate.sh <infohash-hex-de-40-chars>

bin/forensics/lab-down.sh          # para el banco (conserva el datadir del lab)
bin/forensics/lab-reset.sh --yes   # para y borra el datadir del lab para una corrida limpia
```

### El flujo de recuperación

1. `lab-up.sh` — pon el banco en línea.
2. `lab-restore.sh [--until <cuándo>]` — restaura + replay dentro del lab.
3. `lab-diff.sh <tablas>` — mira qué filas le faltan a prod (`lab_only`).
4. `lab-export.sh <tabla>` — emite `INSERT IGNORE` para esas filas.
5. Revisa el export y aplícalo tú mismo a producción como paso deliberado.
6. `lab-down.sh` — desmonta.

## Imagen del toolbox

El toolbox se construye sobre Percona Server (un fork drop-in de MySQL 8.0) para
que `mysqlbinlog` esté en la misma versión que los binlogs de prod — las imágenes
mínimas de Oracle no traen `mysqlbinlog`, y la de MariaDB no parsea de forma fiable
los binlogs ROW de MySQL 8.0. También lleva `percona-toolkit`, `python3`, `jq`,
`ripgrep`, `sqlite3` y `redis-tools`.

Build / publish:

```sh
bin/forensics/lab-build.sh         # construye la imagen etiquetada en local (persiste entre up/down)
bin/forensics/lab-publish.sh       # build + push del tag de versión y :latest al registry
```

Fija `FORENSICS_IMAGE` en `forensics.env` a un tag de versión (no `:latest`) para
que un push posterior de `:latest` no pueda cambiar en silencio un banco en marcha.

## Panel web

El banco tiene un panel web local (despertar/dormir, restore/diff/export, timeline
de daños, topología de datos, mantenimiento de almacenamiento y caminos de
escritura a prod con DR). Local por defecto, con auth fail-closed para exposición
en la nube — ver `forensics/dashboard/README.md`.

## Limitaciones conocidas

- `lab-diff` / `lab-export` se basan en una columna primaria `id`. Las tablas de
  UNIT3D con clave compuesta (p. ej. `peers`, `history`) se saltan; las tablas de
  contenido humano (`users`, `topics`, `posts`, `comments`, `torrents`) todas
  tienen `id`.
- El replay de binlogs recupera solo hasta el binlog retenido más antiguo
  (`binlog_expire_logs_seconds`, 30 días por defecto).

## Roadmap

- `lab-redis` / `lab-meili` opcionales para replay de todo el stack.
- Auto-recuperación guiada en el panel (restore → replay → diff → propone merge;
  el merge a prod sigue siendo confirmado por humano).
