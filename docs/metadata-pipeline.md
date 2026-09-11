# Metadatos y arte en UNIT3D — cómo se procesa y se reparte

Levantado el 2026-08-15 leyendo el código y midiendo en producción, después de
diagnosticar mal el sistema dos veces seguidas. Si vuelves aquí con la sensación
de "va lento", **lee primero la sección de trampas**: casi todas las cifras
evidentes mienten.

---

## Los tres carriles (son independientes)

No hay UN pipeline de metadatos. Hay tres, con distinto disparador, distinto
almacén y distinta velocidad. Confundirlos es lo que lleva a mirar el sitio
equivocado.

### Carril 1 · Resolución de IDs — `meta:sync`
**Qué:** decide QUÉ película/serie es cada torrent.
**Cómo:** `ConsensusResolver` pregunta a varios proveedores y los hace votar —
TMDB, OMDb/IMDB y TVmaze siempre; MAL/AniList sólo si la release parece anime.
Con dos proveedores de acuerdo → `high`; uno solo → `low`; ninguno → `none`.
**Dónde escribe:** tabla `metadata_resolutions` (una fila por torrent, con
`confidence`) y `metadata_artwork`.
**Programado:** `everyFiveMinutes()->withoutOverlapping(10)` en `Kernel.php:123`.
**SÍNCRONO — no usa colas.** Cero `dispatch` en `MetaSync.php`. El trabajo ocurre
dentro del proceso del scheduler.

```
$schedule->command('meta:sync', ['--limit' => 100])->everyFiveMinutes()->withoutOverlapping(10);
```

Medido en producción: un tick tarda **0,3-2 s**. El `--limit` no es un
estrangulador de rendimiento; es un tope de seguridad con muchísimo margen.

### Carril 2 · Hidratación de datos de TMDB — jobs en cola
**Qué:** una vez sabido el id, baja fichas, créditos, géneros, productoras.
**Quién despacha:**
| origen | cuándo |
|---|---|
| `TMDBScraper.php:47` | al subir un torrent |
| `DispatchMetaRefresh` (`meta:refresh-dispatch`) | **NO está programado** — sólo a mano o desde el panel de staff |
| `FetchMeta.php:73` | `dispatchSync`, backfill manual |

**Jobs:** `ProcessMovieJob`, `ProcessTvJob`, `ProcessMalJob`, `ProcessIgdbGameJob`.
**Dónde escribe:** `tmdb_movies`, `tmdb_tv`, `tmdb_credits`, `tmdb_people`,
`tmdb_genres`, `tmdb_companies`, `tmdb_collections`.
**Coste real:** ~114 filas de créditos por película. El gasto es tu MySQL, no la
API.

### Carril 3 · Arte (carátulas y fondos) — proxy con caché en disco
**Las carátulas NO se sirven desde TMDB.** Van por un proxy propio que
normaliza, redimensiona y cachea, y las reemite same-origin.

| ruta | para qué |
|---|---|
| `/art/{size}` (`ArtImageProxyController`) | posters/backdrops de **todos** los proveedores (TMDB, Amazon, TVmaze, MAL, AniList). Va **firmada**, el origen viaja en el query `u` |
| `/tmdb-proxy/{size}/{file}` | específico de TMDB |
| `/torrent-covers/{id}` | portadas subidas a mano |

**Caché:** `storage/app/art-proxy/{poster_big,poster_mid,poster_small,back_big,back_small}/`
— 2,5 GB y ~55.000 ficheros a fecha de hoy.

**Por eso las carátulas aparecen "de cuatro en cuatro":** cada póster nuevo se
descarga, se redimensiona y se guarda **la primera vez que alguien lo pide**. No
es la cola de metadatos yendo lenta; es la caché llenándose, y se autolimita.

`meta:rotate-covers` corre `daily()` y rota las portadas destacadas.

---

## Las dos colas

| contenedor | cola | proceso |
|---|---|---|
| `unit3d-worker` | `default` | `queue:work --queue=default --sleep=3 --tries=1 --timeout=300` |
| `unit3d-meta-worker` | `meta-refresh` | igual, con `--queue=meta-refresh` |

Ambas salen de `.docker/php/entrypoint-worker.sh`, parametrizado con
`QUEUE_WORK_*` desde el compose.

**El meta-worker está casi siempre ocioso (0% CPU) y eso es NORMAL**: su cola
sólo recibe trabajo cuando alguien lanza `meta:refresh-dispatch`, que no está
programado. No es una avería.

---

## TRAMPAS (aquí es donde se pierde la tarde)

### 1. `--tries` del worker NO manda sobre estos jobs
El proceso muestra `--tries=1`, pero los jobs declaran lo suyo y **la propiedad
del job gana**:
```php
ProcessMovieJob.php:55   public int $tries = 3;
ProcessMovieJob.php:57   public int $backoff = 300;   // 5 min entre intentos
```
No hace falta tocar el compose para darles reintentos: ya los tienen. Lo
confirma la evidencia: los fallos históricos son `MaxAttemptsExceededException`,
o sea que agotaron los tres intentos.

### 2. `Torrent` usa SoftDeletes → el SQL a pelo miente
```
pool contado con SQL crudo      : 7606
pool que ve Eloquent            : 6472
```
Los ~1130 de diferencia son borrados. Una consulta de "pendientes" sin
`deleted_at IS NULL` inventa un atasco que no existe: yo conté **672
pendientes** cuando los reales eran **11**.

**Siempre `AND t.deleted_at IS NULL`.**

### 3. El progreso pasa del 100% y es correcto
```
meta:sync starting {"resolved_so_far":6933,"pool_total":6462,"progress_pct":107.3}
```
`metadata_resolutions` conserva filas de torrents ya borrados. Más del 100% no
es un error: significa que está al día y arrastra historia.

### 4. `failed_jobs` es un cementerio, no una cola
**Nada la lee ni reintenta desde ahí.** En agosto de 2026 tenía 3950 filas:
- 3663 `MaxAttemptsExceeded` de **mayo**, concentrados (526 el día 13)
- 0 en las últimas 24 h

Y esos 3291 fallos de `ProcessMovieJob` correspondían a **sólo 110 ids de TMDB
distintos** reintentándose. De esos 110, **108 se resolvieron después solos**
—porque otra subida o un refresh los volvió a despachar— y los 2 restantes ya no
tienen ningún torrent que los use. Vaciar la tabla es cosmético.

### 5. `--limit` sin `--force` no ordena nada
El `orderBy` por resolución más rancia **sólo se aplica con `--force`**
(`MetaSync.php:72`). Sin force, `--limit` es un simple tope.

### 6. TMDB no suele ser el problema
Medido desde el propio contenedor: **50-210 ms** y HTTP 200, sin cabeceras de
límite. Antes de culpar a la API, mídela:
```bash
K=$(grep -oP '(?<=^TMDB_API_KEY=).*' .env)
curl -s -D- -o /dev/null "https://api.themoviedb.org/3/configuration?api_key=$K" | grep -iE "^HTTP|ratelimit"
```

### 7. Hay DOS claves de TMDB y sólo una es tuya
| dónde | clave | qué es |
|---|---|---|
| `.env` `TMDB_API_KEY` | `b087bd…` | **la tuya**, servidor. Nunca publicada |
| `resources/js/unit3d/tmdb.js:4` | `aa8b43b8…` | de la librería `themoviedb-javascript-library` **vendorizada por upstream** |

La del JS es pública por diseño (es JavaScript de cliente) y la usa el
autocompletado de `torrent/create.blade.php:707`. **Sustituirla por la tuya sería
peor**: pasarías de exponer la de upstream a exponer la tuya.

---

## Diagnóstico rápido: por dónde empezar

```bash
# 1. ¿Qué está haciendo de verdad meta:sync? (la fuente de verdad)
grep "meta:sync" storage/logs/laravel-$(date +%F).log | tail -4

# 2. Pendientes REALES (ojo al deleted_at)
SELECT COUNT(*) FROM torrents t JOIN categories c ON c.id=t.category_id
WHERE (c.movie_meta=1 OR c.tv_meta=1) AND (t.tmdb_movie_id>0 OR t.tmdb_tv_id>0)
  AND t.deleted_at IS NULL
  AND t.id NOT IN (SELECT torrent_id FROM metadata_resolutions);

# 3. ¿Hay algo encolado?
docker exec unit3d-redis redis-cli -a "$REDIS_PASSWORD" --scan --pattern "*queues*"

# 4. ¿La caché de arte crece? (si sí, las carátulas están llegando)
find storage/app/art-proxy -type f -mmin -60 | wc -l
```

Si (1) dice `processed` bajo y `progress_pct` ≈100, y (4) crece: **el sistema va
bien** y lo que ves es la caché de arte llenándose bajo demanda.

---

## Estado a 2026-08-15
```
metadata_resolutions : 6933   (high 4912 · none 1109 · low 912)
pendientes reales    :   11
caché de arte        : 2,5 GB · ~55.000 ficheros · 195 nuevos en 1 h
failed_jobs          :    0   (vaciada, copia en ~/Documentos/)
meta:sync            : 0,3-2 s por tick, --limit 100
```
El `--limit` se subió de 25 a 100 ese día. **No arregló nada** —no había atasco—
pero deja margen para futuras tiradas con `--force`, que sí recorren el catálogo
entero.
