# Manual de Configuración y Auditoría de UNIT3D (Instancia B)

Este documento ha sido generado tras un análisis exhaustivo del código fuente de UNIT3D Community Edition, con el fin de diagnosticar y proponer soluciones para la instancia "Instancia B" en comparación con la instancia de referencia "Instancia A".

---

## 1. El Crash del "Dupe Check" (Error 500)

### Análisis de la Lógica de Comprobación de Duplicados
En UNIT3D, la comprobación de duplicados ocurre en dos frentes: la interfaz web y la API. 

1. **Interfaz Web (Livewire):** Se utiliza el componente `app/Http/Livewire/SimilarTorrent.php` ([archivo válido de ejemplo](./assets/manual/punto-1/SimilarTorrent.php.txt)) que realiza búsquedas dinámicas basadas en IDs de metadatos (TMDB, IGDB).
2. **Sistema de Validación (FormRequests):** El archivo principal que maneja la validación de subidas es `app/Http/Requests/StoreTorrentRequest.php` ([archivo ya configurado](./assets/manual/punto-1/StoreTorrentRequest.php.txt)).
3. **API (Controller):** El controlador `app/Http/Controllers/API/TorrentController.php` ([archivo válido de ejemplo](./assets/manual/punto-1/TorrentController.php.txt)) maneja las subidas vía API (`/api/torrents/upload`).

### Rastreo del Error 500
El Error 500 (Internal Server Error) suele ocurrir cuando una excepción no es capturada por el framework o cuando hay un fallo crítico en una dependencia. Tras analizar el código, hemos identificado los siguientes puntos críticos:

#### A. Validación del Infohash y el Controlador de API
En el método `store` de `API/TorrentController.php`, la validación se realiza de la siguiente manera:
```php
// app/Http/Controllers/API/TorrentController.php:219
'info_hash' => [
    'required',
    Rule::unique('torrents')->whereNull('deleted_at'),
],
```
Si la base de datos en Instancia B tiene problemas de integridad, o si el controlador de base de datos lanza una excepción inesperada durante esta comprobación (por ejemplo, fallos en el motor de búsqueda Meilisearch), el servidor devolverá un HTML de Error 500 en lugar de un JSON de error de validación.

#### B. Fallo en el Helper de Bencode
El proceso de "Dupe Check" requiere decodificar el archivo `.torrent` para extraer el `info_hash`. Esto se hace mediante `app/Helpers/Bencode.php` ([archivo ya configurado](./assets/manual/punto-1/Bencode.php.txt)). 
Si la extensión de PHP `theodorejb/polycast` no está correctamente instalada o si hay una incompatibilidad con PHP 8.4 en el entorno de la VM, cualquier llamada a `Bencode::get_infohash()` fallará catastróficamente.

#### C. Meilisearch como Driver de Búsqueda
Si Instancia B tiene configurado `SCOUT_DRIVER=meilisearch` pero el servicio Meilisearch no es accesible o tiene una clave de API incorrecta, la función `filter` de la API (usada frecuentemente por scripts externos para comprobar si un torrent ya existe antes de subirlo) fallará con un 500.
```php
// app/Http/Controllers/API/TorrentController.php:631
$paginator = Torrent::search(...)
```

#### D. ERROR DETECTADO: Desincronización de Parámetros en `TorrentSearchFiltersDTO`
El log de errores de Instancia B confirma un fallo crítico por un parámetro desconocido:
`Unknown named parameter $genreIds at app/Http/Controllers/API/TorrentController.php:595`

Esto sucede porque el controlador de la API intenta instanciar el DTO de filtros con parámetros que la versión del código en Instancia B no reconoce. A continuación, mostramos la configuración correcta en la instancia de referencia (Instancia A) para su comparación:

**1. Instanciación en el Controlador de API (`app/Http/Controllers/API/TorrentController.php`):**
```php
584: $filters = new TorrentSearchFiltersDTO(
585:     name: $request->filled('name') ? $request->string('name')->toString() : '',
...
595:     genreIds: $request->filled('genres') ? array_map('intval', $request->genres) : [],
...
617: );
```

**2. Definición del Constructor del DTO (`app/DTO/TorrentSearchFiltersDTO.php`)** ([archivo válido de ejemplo](./assets/manual/punto-1/TorrentSearchFiltersDTO.php.txt)):
```php
31: public function __construct(
32:     private string $name = '',
...
51:     private array $genreIds = [],
...
86: ) {
```

**Diagnóstico:** En Instancia B, es probable que se haya actualizado el `TorrentController` pero no el archivo `TorrentSearchFiltersDTO.php`, o viceversa, dejando al sistema con una firma de método incompatible. Esto rompe cualquier llamada al endpoint `/api/torrents/filter`, devolviendo el Error 500 mencionado.

### Variables y Base de Datos Implicadas
- **Tabla `torrents`:** Campos `info_hash` (único) y `name` (único).
- **Variable `.env`:** `SCOUT_DRIVER` (si es `meilisearch`, es un punto de fallo crítico).
- **Variable `.env`:** `MEILISEARCH_HOST` y `MEILISEARCH_KEY`.
- **Memoria Temporal:** El sistema guarda el archivo temporalmente en `Storage::disk('torrent-files')`. Si la carpeta `storage/app/torrents` no tiene permisos de escritura, la subida fallará antes de llegar al check de duplicados.

---

## 2. Conexiones de Pares - "Not Found" y Estadísticas a Cero

### Análisis del Endpoint de Announce y Scrape
En UNIT3D, los clientes torrent se comunican con el tracker a través de una URL estructurada:
`http://tu-dominio.com/announce/{passkey}`.

Si un cliente devuelve un error "Not Found" (404), significa que la petición ni siquiera está llegando a procesarse por la lógica interna del tracker o que el framework no encuentra una ruta que coincida.

### Diagnóstico del Error "Not Found"
Basándonos en el código de UNIT3D, estas son las causas más probables:

#### A. Error en la Ruta de Announce (`routes/announce.php`) ([archivo válido de ejemplo](./assets/manual/punto-2/announce.php.txt))
La ruta está definida de forma muy estricta. Si el cliente no envía la passkey exactamente como el tracker la espera (32 caracteres hexadecimales), o si hay un prefijo mal configurado en el servidor web (Nginx), Laravel devolverá un 404.
```php
// routes/announce.php:33
Route::get('{passkey}', [App\Http\Controllers\AnnounceController::class, 'index'])->name('announce');
```

#### B. Fallo en la Validación del Usuario o Passkey
Dentro de `AnnounceController` ([archivo válido de ejemplo](./assets/manual/punto-2/AnnounceController.php.txt)), cualquier fallo en la identificación del usuario o del torrent lanza una `TrackerException` ([archivo válido de ejemplo](./assets/manual/punto-2/TrackerException.php.txt)). Si el sistema está mal configurado, el cliente torrent puede interpretar ciertas respuestas de error de red como un "Not Found" genérico.
- **Passkey Inexistente:** Si la passkey no está en la base de datos (Error 140).
- **Torrent No Registrado:** Si el infohash que envía el cliente no existe en la tabla `torrents` (Error 150).

#### C. ERROR DETECTADO: Redis Inestable o Inaccesible
Los logs de Instancia B muestran tres estados críticos que confirman el fallo de Redis:
1. `RedisException: Connection refused`: Fallo total al intentar conectar.
2. `RedisException: Redis server 127.0.0.1:6379 went away`: La conexión se pierde durante la ejecución.
3. `Scheduled command [auto:update_user_last_actions] failed with exit code [1]`: Este comando falla específicamente porque depende de Redis para leer las acciones pendientes de los usuarios (`Redis::command('LLEN', ...)`).

**Impacto Directo:**
*   **Fallo de Announce:** El tracker bloquea conexiones al no poder validar el "throttling".
*   **Scheduler Paralizado:** Al fallar los comandos (exit code 1) por culpa de Redis, el sistema no puede realizar mantenimiento básico.
*   **Estadísticas a Cero:** Al no ejecutarse el Scheduler ni los comandos de volcado (`AutoUpsertPeers`), la base de datos MySQL nunca recibe la información de los pares activos.

**Diagnóstico Final de Infraestructura:** El servicio Redis en Instancia B no es confiable. Al ser un entorno RHEL en VM, se debe verificar si el socket de Redis está saturado, si hay un firewall local (iptables/nftables) cerrando conexiones prematuramente, o si el servicio `redis-server` se está reiniciando constantemente por falta de RAM.
Si el tracker no recibe (o no puede procesar) los paquetes de "announce" de los clientes, nunca se ejecutan los jobs que actualizan las estadísticas.
1. **Fallo de Announce:** El cliente no puede conectar -> No hay datos.
2. **Workers de Sistema:** UNIT3D depende de comandos programados para "volcar" los datos de Redis a la base de datos MySQL.
   - `AutoUpsertPeers`: Mueve los pares de la caché a la DB.
   - `AutoUpsertHistories`: Actualiza el historial de subida/bajada.
Si estos comandos no se están ejecutando (vía Scheduler/Cron), la web mostrará 0 seeds y 0 leecher aunque el tracker esté funcionando técnicamente.

---

## 3. Topología de Red y Enrutamiento (Proxmox vs Docker)

### La importancia de la IP Real
Para un tracker privado, la IP real del usuario es crítica: se usa para el sistema de baneo, para el límite de conexiones por torrent y para asegurar que el usuario no está haciendo trampas. Si el tracker recibe la IP interna del Proxy (ej. 172.18.0.1 o 10.0.0.1), fallará la lógica de conexión y de seguridad.

### Diferencias de Entorno
1. **Instancia A (Docker):** Los contenedores suelen estar tras una red bridge de Docker. UNIT3D confía en el proxy inverso (Nginx/Traefik) para pasar la IP original.
2. **Instancia B (Proxmox/VM):** Al ser una VM con IP dedicada, es probable que esté recibiendo tráfico directamente o a través de un router/firewall que puede estar enmascarando las IPs si no se configura el "Hairpin NAT" o el paso de cabeceras correctamente.

### Mapeo de Flujo (Los 15 Elementos)
Para que la IP llegue intacta al código PHP, la cadena debe estar configurada así:
1. **Usuario Final:** Inicia la petición.
2. **Firewall/Router (Proxmox Host):** Debe redirigir los puertos 80/443 a la VM.
3. **Load Balancer (Opcional):** Si existe, debe añadir `X-Forwarded-For`.
4. **Reverse Proxy (Nginx/NPM/Traefik):** Captura la IP y la pasa al backend.
5. **Cabecera `X-Real-IP`:** Nginx debe tener `proxy_set_header X-Real-IP $remote_addr;`.
6. **Cabecera `X-Forwarded-For`:** Nginx debe tener `proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;`.
7. **PHP-FPM:** Recibe las cabeceras del servidor web.
8. **Laravel (TrustProxies):** El middleware debe confiar en el Proxy para leer estas cabeceras.
9. **Redis:** Almacena temporalmente la IP del par.
10. **MariaDB/MySQL:** Guarda la IP en la tabla `peers`.
11. **Middleware `BlockIpAddress`:** Comprueba si la IP real está baneada.
12. **Middleware `TrustProxies.php`:** Configurado para aceptar proxies.
13. **AnnounceController:** Extrae la IP empaquetada con `inet_pton`.
14. **Logs de Laravel:** Deben mostrar la IP real en caso de error.
15. **Sistema de Auditoría:** Registra las acciones vinculadas a esa IP.

### Código Implicado y Configuración
En la instancia de referencia, el middleware `TrustProxies.php` está configurado para confiar en **todos** los proxies (`*`), lo cual es común en entornos con balanceadores de carga dinámicos.

**Configuración en `app/Http/Middleware/TrustProxies.php`** ([archivo ya configurado](./assets/manual/punto-3/TrustProxies.php.txt)):
```php
22: class TrustProxies extends Middleware
23: {
24:     /**
25:      * The trusted proxies for this application.
26:      *
27:      * @var array<int, string>|string|null
28:      */
29:     protected $proxies = '*';
30: 
31:     /**
32:      * The headers that should be used to detect proxies.
33:      *
34:      * @var int
35:      */
36:     protected $headers = RequestAlias::HEADER_X_FORWARDED_FOR | RequestAlias::HEADER_X_FORWARDED_HOST | RequestAlias::HEADER_X_FORWARDED_PORT | RequestAlias::HEADER_X_FORWARDED_PROTO | RequestAlias::HEADER_X_FORWARDED_AWS_ELB;
37: }
```

**Configuración de Nginx (`.docker/nginx/default.conf`)** ([archivo ya configurado](./assets/manual/punto-3/default.conf.txt)):
Es vital que el servidor web pase las variables correctamente al proceso PHP:
```nginx
22:     location ~ \.php$ {
23:         fastcgi_pass app:9000;
24:         fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
25:         fastcgi_param REMOTE_ADDR $http_x_real_ip;
26:         fastcgi_param HTTP_X_REAL_IP $http_x_real_ip;
27:         fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
28:         include fastcgi_params;
29:     }
```

---

## 4. Todas las Configuraciones Cruciales (El Iceberg Completo)

Documentamos los motores internos que mantienen el tracker vivo. Si estos servicios fallan o están mal configurados, el tracker "morirá en silencio" (la web cargará, pero nada se actualizará).

### A. Caché y Redis (El Corazón del Rendimiento)
Redis no es opcional en UNIT3D; se usa para:
1.  **Throttling:** Limitar las peticiones de los clientes torrent.
2.  **Peers Temporales:** Almacenar los anuncios de los clientes antes de volcarlos a SQL.
3.  **Cache de Configuración:** Evitar miles de consultas a la DB para leer los ajustes del sitio.

**Configuración Crítica en `config/database.php`** ([archivo válido de ejemplo](./assets/manual/punto-4/database.php.txt)):
UNIT3D utiliza múltiples bases de datos Redis (0-5) para evitar colisiones:
```php
'redis' => [
    'default'   => ['database' => env('REDIS_DB', '0')],
    'cache'     => ['database' => env('REDIS_CACHE_DB', '1')],
    'job'       => ['database' => env('REDIS_JOB_DB', '2')],
    'announce'  => ['database' => env('REDIS_ANNOUNCE_DB', '5')],
],
```
**Punto de Fallo en Instancia B:** El log confirma que Redis está devolviendo `Connection refused`. Esto es un fallo total de la infraestructura. Sin Redis, UNIT3D no puede ni siquiera arrancar las tareas programadas (`schedule:run`) ni procesar anuncios de pares. Es vital revisar si el servicio Redis está corriendo en la VM o si los datos de `REDIS_HOST`, `REDIS_PORT` y `REDIS_PASSWORD` en el `.env` son correctos.

### B. Workers y Colas (Queues)
UNIT3D delega tareas pesadas a procesos en segundo plano. 
- **Ruta de los Jobs:** `app/Jobs/`
- **Job Crítico:** `ProcessAnnounce.php`. Este job procesa los anuncios de los clientes torrent de forma asíncrona.
- **Configuración:** En producción, `QUEUE_CONNECTION` debe ser `redis`. Si está en `sync`, el tracker irá extremadamente lento.
- **Supervisor:** Se debe usar un gestor de procesos como Supervisor o systemd para asegurar que `php artisan queue:work` esté siempre corriendo.

### C. Tareas Programadas (Cron/Scheduler)
El archivo `app/Console/Kernel.php` ([archivo válido de ejemplo](./assets/manual/punto-4/Kernel.php.txt)) define qué ocurre y cada cuánto tiempo.
- **`AutoUpsertPeers` (cada 5 seg):** Vuelca los pares de Redis a MySQL. Sin esto, la web muestra 0 peers.
- **`AutoUpdateUserLastActions`** ([archivo ya configurado](./assets/manual/punto-4/AutoUpdateUserLastActions.php.txt)) **(cada 5 seg):** Actualiza la última vez que se vio a un usuario.
- **`AutoNerdStat` (cada hora):** Calcula estadísticas avanzadas.
- **`BackupCommand` (diario):** Realiza copias de seguridad automáticas.

**Configuración en `app/Console/Kernel.php`:**
```php
70: $schedule->command(AutoUpsertPeers::class)->everyFiveSeconds()->withoutOverlapping(2);
71: $schedule->command(AutoUpsertHistories::class)->everyFiveSeconds()->withoutOverlapping(2);
```

### D. WebSockets / Broadcasting
Usado para notificaciones en tiempo real y el chat (Shoutbox).
- **Driver:** UNIT3D suele usar `Pusher` (compatible con Reverb o self-hosted websockets).
- **Configuración:** `BROADCAST_DRIVER` en el `.env`.

### E. Tabla de Variables de Entorno Vitales (`.env`)

| Variable | Valor Recomendado | Descripción |
| :--- | :--- | :--- |
| `APP_ENV` | `production` | Activa optimizaciones y oculta errores detallados. |
| `APP_DEBUG` | `false` | **CRÍTICO:** Debe estar en false en Instancia B. |
| `CACHE_DRIVER` | `redis` | Usa Redis para la caché global. |
| `QUEUE_CONNECTION` | `redis` | Ejecuta tareas en segundo plano. |
| `DB_CONNECTION` | `mysql` | Conexión principal a la base de datos. |
| `SCOUT_DRIVER` | `meilisearch` | Motor para búsquedas y filtros (Punto de fallo Error 500). |
| `MEILISEARCH_HOST` | `http://meilisearch:7700` | URL del servicio Meilisearch. |
| `REDIS_HOST` | `redis` | Host de Redis. |
| `TRUST_PROXIES` | `*` | **CRÍTICO:** Define en qué IPs de proxy se confía para leer la IP real del usuario. |

## 5. Topología de Red y Real IP (Docker vs Pública)

**El Problema:** Al visualizar perfiles de usuario o logs en una instancia recién instalada de UNIT3D en Docker, es común que todas las conexiones aparezcan con la IP interna de la red de Docker (ej: `172.21.0.1`). Esto ocurre porque el servidor web Nginx, al actuar como proxy interno, reporta la IP del gateway de Docker en lugar de la IP real del cliente que llega desde el exterior (Traefik, Nginx externo o el propio host). Esto inutiliza funciones de seguridad (baneos por IP), detección de duplicados y estadísticas de red.

**Análisis Técnico:**
Cuando una petición llega desde internet, suele pasar por esta cadena:
`Usuario -> Proxy Externo (Nginx/Traefik) -> Nginx Contenedor -> PHP-FPM`

Si no se configura correctamente, cada salto "enmascara" la IP original. El Nginx interno del contenedor ve la IP del Proxy Externo, y PHP-FPM ve la IP del contenedor Nginx.

**Configuración Maestra en esta instancia:**

Para solucionar esto, en esta instancia se han aplicado cambios en dos niveles:

### A. Nginx Interno (Paso de Cabeceras a PHP)
En el archivo `nginx_default.conf` ([archivo ya configurado](./assets/manual/punto-5/nginx_default.conf.txt)) (ubicado originalmente en `.docker/nginx/default.conf`), se ha modificado el bloque que gestiona PHP para forzar la lectura de la IP real:

```nginx
22:     location ~ \.php$ {
23:         fastcgi_pass app:9000;
24:         fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
25:         fastcgi_param REMOTE_ADDR $http_x_real_ip;
26:         fastcgi_param HTTP_X_REAL_IP $http_x_real_ip;
27:         fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
28:         include fastcgi_params;
29:     }
```

*   **Línea 25 (`fastcgi_param REMOTE_ADDR $http_x_real_ip;`)**: Esta es la clave. Sobreescribe la variable `REMOTE_ADDR` de PHP con el contenido de la cabecera HTTP `X-Real-IP` (que Nginx recibe como `$http_x_real_ip`). De esta forma, cualquier función de Laravel que pida la IP del cliente recibirá la IP real.

### B. Laravel (Confianza en el Proxy)
En el archivo `TrustProxies.php` ([archivo ya configurado](./assets/manual/punto-5/TrustProxies.php.txt)) (ubicado en `app/Http/Middleware/TrustProxies.php`), se define en qué proxies se confía para leer estas cabeceras:

```php
29:     protected $proxies = '*';
```

*   **Línea 29**: Al usar `'*'`, Laravel confiará en cualquier IP que le envíe cabeceras de proxy. Esto es necesario en entornos Docker donde las IPs de los proxies pueden ser dinámicas.

---

**Guía de Implementación (Fresh Install):**

Si estás configurando una instancia de cero y ves IPs `172.x.x.x` en los perfiles:

1.  **Configurar Proxy Externo:** Asegúrate de que tu Nginx principal (el que está fuera de Docker) o Traefik esté enviando la IP real. En Nginx externo (Host) sería:
    ```nginx
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    ```
2.  **Modificar Nginx Interno:** Edita el archivo `.docker/nginx/default.conf` de tu repositorio UNIT3D y añade la línea `fastcgi_param REMOTE_ADDR $http_x_real_ip;` dentro del bloque `location ~ \.php$`.
3.  **Verificar Middleware Laravel y .env:** Abre `app/Http/Middleware/TrustProxies.php` y asegúrate de que `$proxies` tenga el valor `'*'` o que lea de `env('TRUST_PROXIES', '*')`. Añade `TRUST_PROXIES=*` a tu archivo `.env`.
4.  **Aplicar Cambios:** Reinicia el contenedor de Nginx:
    ```bash
    docker compose restart web
    ```
5.  **Verificación Final:** Entra en el panel de administración o en tu perfil de usuario y comprueba que la IP que aparece coincide con tu IP pública actual.

## 6. El "Iceberg" de los Metadatos (TMDB, IMDB, Portadas)

**El Problema:** En Instancia B, los torrents recién subidos aparecen con el mensaje **"No meta found"** y sin carátulas ni descripciones, a pesar de que el usuario proporciona los IDs de TMDB o IMDB. En la instancia de referencia (Instancia A), esto funciona de forma automática e instantánea.

**Análisis Técnico del Flujo de Metadatos:**
El sistema de metadatos en UNIT3D no es una consulta simple a una base de datos; es una tubería (pipeline) compleja que involucra múltiples capas:

### A. Iniciación (Controllers)
Cuando se sube un torrent (`TorrentController@store`) o se solicita una actualización manual (`SimilarTorrentController@update`), el sistema llama al servicio `TMDBScraper`.
*   **Archivos Implicados:** `TMDBScraper.php` ([archivo ya configurado](./assets/manual/punto-6/TMDBScraper.php.txt))

### B. Segundo Plano (Queues y Jobs)
El `TMDBScraper` no descarga los datos directamente para no bloquear la web. En su lugar, pone un "trabajo" en la cola: `ProcessMovieJob` o `ProcessTvJob` ([archivo ya configurado](./assets/manual/punto-6/ProcessTvJob.php.txt)).
*   **Archivos Implicados:** `ProcessMovieJob.php` ([archivo ya configurado](./assets/manual/punto-6/ProcessMovieJob.php.txt))
*   **Punto de Fallo Crítico (Redis):** Estos jobs utilizan un limitador de velocidad (Rate Limiter) para no ser baneados por TMDB.
    ```php
    // ProcessMovieJob.php:62
    new RateLimited(GlobalRateLimit::TMDB),
    ```
    Este limitador **depende obligatoriamente de Redis**. Como hemos diagnosticado en el Punto 2 que el Redis de Instancia B está caído o es inestable, cualquier job de metadatos morirá al intentar comprobar el límite de velocidad. **Sin Redis funcionando al 100%, no habrá metadatos.**

### C. El Cliente de API (External Fetch)
Si el worker logra ejecutar el job, este utiliza un cliente HTTP para consultar la API de TheMovieDB.
*   **Archivos Implicados:** `MovieClient.php` ([archivo válido de ejemplo](./assets/manual/punto-6/MovieClient.php.txt))
*   **Configuración Vital:** Requiere una clave válida en el `.env`.
    ```env
    TMDB_API_KEY=tu_clave_aqui
    ```

### D. Almacenamiento y Visualización
Una vez descargados, los datos se guardan en las tablas `tmdb_movies` o `tmdb_tv`. Las portadas y backdrops **no se descargan al servidor local**; UNIT3D almacena la ruta relativa y genera una URL directa al CDN de TMDB (`image.tmdb.org`).
*   **Lógica de Visualización:** `TorrentMeta.php` ([archivo ya configurado](./assets/manual/punto-6/TorrentMeta.php.txt)) (Trait usado por los modelos para inyectar los metadatos en las vistas).

---

**Diagnóstico y Pasos para Reparar Instancia B:**

Si ves "No meta found", sigue este orden de revisión:

1.  **Estado de Redis (Prioridad 1):** Como se vio en el Punto 2, Redis falla. Si Redis no funciona, el `RateLimiter` bloquea los jobs de metadatos. **Repara Redis primero.**
2.  **Workers de Cola:** Asegúrate de que los workers están procesando la cola `default`.
    ```bash
    php artisan queue:work --queue=default
    ```
3.  **Clave de API:** Verifica que `TMDB_API_KEY` en el `.env` de Instancia B sea válida y no haya excedido su cuota. Puedes probarla manualmente:
    ```bash
    curl "https://api.themoviedb.org/3/movie/550?api_key=TU_KEY"
    ```
4.  **Configuración de Categorías:** Verifica en el Panel de Staff que la categoría del torrent tiene activado "Movie Meta" o "TV Meta". Si está desactivado, el scraper ni siquiera se iniciará.
5.  **Conectividad de Red:** Al ser una VM en Proxmox, asegúrate de que tiene salida a internet para consultar `api.themoviedb.org` e `image.tmdb.org`.
6.  **Sincronización de Base de Datos:** Si las tablas `tmdb_movies` están vacías tras subir torrents, el problema está en los pasos 1 o 2.

---

*Fin del Manual de Configuración. Auditoría de Metadatos completada.*

