@extends('layout.with-main')

@section('title')
    <title>Comandos - {{ __('staff.staff-dashboard') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Comandos - {{ __('staff.staff-dashboard') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('staff.dashboard.index') }}" class="breadcrumb__link">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumb--active">Comandos</li>
@endsection

@section('page', 'page__staff-command--index')

@section('main')

    {{-- Command output --}}
    @if (session('info'))
        <div class="cmd-output">
            <span class="cmd-output__icon">
                <i class="{{ config('other.font-awesome') }} fa-terminal"></i>
            </span>
            <pre class="cmd-output__text">{{ session('info') }}</pre>
        </div>
    @endif

    {{-- Emergency banner --}}
    <div class="cmd-emergency">
        <div class="cmd-emergency__main">
            <i class="{{ config('other.font-awesome') }} fa-exclamation-triangle"></i>
            <strong>SALIDA DE EMERGENCIA</strong>
            <span>— Si te quedas atrapado en modo mantenimiento, visita:</span>
            <code class="cmd-emergency__url">/dashboard/commands/emergency-disable-maintenance</code>
        </div>
        <small class="cmd-emergency__note">Este endpoint SIEMPRE está accesible y desactiva el modo mantenimiento a la fuerza.</small>
    </div>

    {{-- Command grid --}}
    <div class="cmd-grid">

        {{-- 1. Mantenimiento y Control del Sitio --}}
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-shield-alt"></i>
                Mantenimiento y Control del Sitio
            </h2>
            <div class="panel__body">
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/maintenance-enable',
                    'label'  => 'Activar mantenimiento',
                    'icon'   => 'fa-lock',
                    'level'  => 'warning',
                    'tip'    => 'Pone el sitio en modo mantenimiento (solo accesible desde tu IP)',
                ])
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/maintenance-disable',
                    'label'  => 'Desactivar mantenimiento',
                    'icon'   => 'fa-lock-open',
                    'level'  => 'safe',
                    'tip'    => 'Reabre el sitio al público',
                ])
            </div>
        </section>

        {{-- 2. Caché y Rendimiento --}}
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-bolt"></i>
                Caché y Rendimiento
            </h2>
            <div class="panel__body">
                @include('Staff.command._btn', ['action' => '/dashboard/commands/clear-cache',        'label' => 'Limpiar caché',          'icon' => 'fa-broom',       'level' => 'safe',    'tip' => 'Limpiar caché de la aplicación'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/clear-view-cache',   'label' => 'Limpiar vistas',         'icon' => 'fa-eye-slash',   'level' => 'safe',    'tip' => 'Limpiar caché de vistas compiladas'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/clear-route-cache',  'label' => 'Limpiar rutas',          'icon' => 'fa-route',       'level' => 'safe',    'tip' => 'Limpiar caché de rutas compiladas'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/clear-all-cache',    'label' => 'Limpiar vistas, rutas y config', 'icon' => 'fa-trash-alt', 'level' => 'warning', 'tip' => 'Limpia vistas y rutas compiladas, reconstruye la config desde config/ y .env y reinicia los workers. No toca la caché de Redis: para eso, «Limpiar caché» o «Limpiar optimización»'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/set-all-cache',      'label' => 'Fijar toda la caché',    'icon' => 'fa-database',    'level' => 'safe',    'tip' => 'Precompila las vistas, reconstruye la config desde config/ y .env y reinicia los workers. La caché de rutas solo se renueva donde ya existe: en prod no hay, a propósito, y este botón no la crea'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/optimize-clear',     'label' => 'Limpiar optimización',   'icon' => 'fa-tools',       'level' => 'warning', 'tip' => 'Lo limpia todo: caché de Redis, vistas, rutas, eventos y compilados (optimize:clear); reconstruye la config desde config/ y .env y reinicia los workers'])
                @include('Staff.command._btn', [
                    'action'  => '/dashboard/commands/apply-env',
                    'label'   => 'Aplicar cambios del .env',
                    'icon'    => 'fa-key',
                    'level'   => 'warning',
                    'tip'     => 'Tras cambiar un token, una clave o un host en el .env: reconstruye la config desde el .env y reinicia los workers para que los jobs la carguen. Si el .env no cuadra, la config en uso no se toca. Los jobs fallidos se ven en Dashboard → Colas',
                ])
            </div>
        </section>

        {{-- 3. Operaciones de Datos Críticas --}}
        <section class="panelV2">
            <h2 class="panel__heading panel__heading--danger">
                <i class="{{ config('other.font-awesome') }} fa-radiation"></i>
                Operaciones de Datos Críticas
            </h2>
            <div class="panel__body">
                @include('Staff.command._btn', ['action' => '/dashboard/commands/update-email-blacklist', 'label' => 'Actualizar lista negra emails', 'icon' => 'fa-envelope-open-text', 'level' => 'danger',  'tip' => 'Actualizar lista negra de emails desde fuente remota'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/telegram-webhook',       'label' => 'Registrar Telegram',           'icon' => 'fa-paper-plane',        'level' => 'info',    'tip' => 'Registrar webhook del bot Telegram con la API'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/scout-reindex',          'label' => 'Reindexar Meilisearch',        'icon' => 'fa-sync',               'level' => 'warning', 'tip' => 'Vuelve a mandar TODOS los torrents al índice sin vaciarlo (el buscador sigue funcionando) y quita los que ya no existen. ~15 s en prod. Para cuando el buscador da resultados viejos o le faltan torrents'])
                @include('Staff.command._btn', [
                    'action'  => '/dashboard/commands/meilisearch-fix',
                    'label'   => 'Vaciar Meilisearch',
                    'icon'    => 'fa-search-minus',
                    'level'   => 'danger',
                    'tip'     => 'Vacía el índice de torrents y lo rehace entero en el mismo paso. Mientras dura (~15 s en prod) la búsqueda de torrents sale vacía. No toca el índice de personas',
                    'confirm' => '⚠️ Vacía el índice de torrents y lo rehace entero.\nMientras dura (unos 15 s) la búsqueda de torrents sale vacía.\nSi solo hace falta refrescarlo, mejor «Reindexar Meilisearch».\n¿Continuar?',
                ])
                @include('Staff.command._btn', [
                    'action'  => '/dashboard/commands/meilisearch-full-repair',
                    'label'   => 'Reparación completa Meilisearch',
                    'icon'    => 'fa-wrench',
                    'level'   => 'danger',
                    'tip'     => 'Salud + crea los índices que falten + filtros y orden desde config/scout.php + vacía y rehace torrents + vuelve a mandar todas las personas + valida. 20-30 s en prod',
                    'confirm' => "⚠️ REPARACIÓN COMPLETA DE MEILISEARCH\n\nEsto va a:\n1. Verificar salud\n2. Crear índices si faltan\n3. Sincronizar filtros y orden (config/scout.php)\n4. VACIAR y rehacer el índice de torrents (búsqueda vacía mientras dura)\n5. Volver a mandar todas las personas (se actualizan, no se borran)\n6. Validar\n\n⏱️ Unos 20-30 s en prod. No cierres la página: la salida sale aquí.\n\n¿Continuar?",
                ])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/clean-failed-logins', 'label' => 'Limpiar logins fallidos', 'icon' => 'fa-user-slash', 'level' => 'muted', 'tip' => 'Eliminar todos los intentos de login fallidos (solo BD)'])
            </div>
        </section>

        {{-- 4. TMDB --}}
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-film"></i>
                TMDB
            </h2>
            <div class="panel__body">
                @include('Staff.command._btn', ['action' => '/dashboard/commands/sync-missing-trailers',       'label' => 'Sync trailers faltantes', 'icon' => 'fa-play-circle', 'level' => 'safe',    'tip' => 'Backfill trailers de YouTube desde TMDB (películas + TV sin trailer)'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/sync-missing-trailers-force', 'label' => 'Sync trailers (forzado)', 'icon' => 'fa-sync',        'level' => 'warning', 'tip' => 'Re-fetch todos los trailers desde TMDB, incluso los ya configurados', 'confirm' => "⚠️ SYNC FORZADO DE TRAILERS\n\nEsto va a re-fetchear TODOS los trailers desde TMDB, incluso los que ya tienen uno.\n\n⏱️ Puede tardar varios minutos según el catálogo.\n\n¿Continuar?"])
            </div>
        </section>

        {{-- 5. Rust Tracker --}}
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-broadcast-tower"></i>
                Rust Tracker — Sincronización
            </h2>
            <div class="panel__body">
                @include('Staff.command._btn', ['action' => '/dashboard/commands/tracker-sync-torrents', 'label' => 'Sincronizar torrents',  'icon' => 'fa-film',       'level' => 'warning', 'tip' => 'Fuerza reenvío de todos los torrents al tracker Rust. Usar cuando torrents queden en estado de error por desync.', 'confirm' => "⚠️ SYNC TORRENTS → TRACKER\n\nReenviará todos los torrents al tracker Rust.\nPuede tardar unos segundos.\n\n¿Continuar?"])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/tracker-sync-users',    'label' => 'Sincronizar usuarios',  'icon' => 'fa-users',      'level' => 'warning', 'tip' => 'Fuerza reenvío de todos los usuarios al tracker Rust. Usar cuando passkeys o permisos no se reflejen.', 'confirm' => "⚠️ SYNC USUARIOS → TRACKER\n\nReenviará todos los usuarios al tracker Rust.\n\n¿Continuar?"])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/tracker-sync-groups',   'label' => 'Sincronizar grupos',    'icon' => 'fa-layer-group', 'level' => 'safe',    'tip' => 'Reenvía todos los grupos al tracker Rust. Rápido — usar tras cambios en permisos de grupo.'])
            </div>
        </section>

        {{-- 6. Gestión de Peers y Torrents --}}
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-seedling"></i>
                Gestión de Peers y Torrents
            </h2>
            <div class="panel__body">
                @include('Staff.command._btn', ['action' => '/dashboard/commands/flush-old-peers',      'label' => 'Limpiar peers viejos',       'icon' => 'fa-wifi',         'level' => 'safe',    'tip' => 'Peers sin anunciar > 2 horas: los marca inactivos en la web y en la memoria del announce (libera el límite de 3 por torrent). No vacía la lista de peers, solo actualiza su estado; las filas inactivas se borran solas a los 2 días'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/reset-user-flushes',   'label' => 'Resetear flushes usuarios',  'icon' => 'fa-redo',         'level' => 'warning', 'tip' => 'Resetear cuota diaria de flush de peers para todos los usuarios'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/sync-peers',           'label' => 'Sincronizar peers (DB)',      'icon' => 'fa-exchange-alt', 'level' => 'warning', 'tip' => 'Recalcula seeders/leechers de la tabla torrents desde la tabla peers. Con el announce Rust quien manda es su memoria: mejor «Comprobar contadores» y, si hace falta, «Reset pares (announce)»'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/tracker-counters-check', 'label' => 'Comprobar contadores', 'icon' => 'fa-stethoscope', 'level' => 'safe', 'tip' => 'Solo lee. Compara seeders/leechers (columna de torrents, tabla peers y memoria del announce) y balance (columna frente a history), y lista los que no cuadran'])
                @include('Staff.command._btn', [
                    'action'  => '/dashboard/commands/tracker-counters-fix-peers',
                    'label'   => 'Reset pares (announce)',
                    'icon'    => 'fa-satellite-dish',
                    'level'   => 'warning',
                    'tip'     => 'Reescribe seeders/leechers de los torrents descuadrados desde la memoria del announce. Solo toca los que dan el mismo desfase en dos lecturas separadas 5 s y guarda copia previa en storage/app/tracker-counters/',
                    'confirm' => '⚠️ Va a reescribir seeders/leechers de los torrents descuadrados con lo que dice el announce. Guarda copia previa.\n¿Continuar?',
                ])
                @include('Staff.command._btn', [
                    'action'  => '/dashboard/commands/tracker-counters-fix-balance',
                    'label'   => 'Reset balance (history)',
                    'icon'    => 'fa-balance-scale',
                    'level'   => 'warning',
                    'tip'     => 'Reescribe torrents.balance de los torrents descuadrados con el tráfico de history (incluidas las filas borradas). Lo usa «Torrents tramposos». Guarda copia previa en storage/app/tracker-counters/',
                    'confirm' => '⚠️ Va a reescribir el balance de los torrents descuadrados con lo que dice history. Cambia lo que muestra «Torrents tramposos». Guarda copia previa.\n¿Continuar?',
                ])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/sync-torrents-meilisearch', 'label' => 'Sincronizar últimos 15 min', 'icon' => 'fa-hdd',       'level' => 'safe',    'tip' => 'Manda a Meilisearch solo los torrents cambiados en el último cuarto de hora. Es lo mismo que hace el scheduler cada 15 min, por si hace falta darle a mano'])
                {{-- No es un POST: abre la lista, y la peticion sale solo al confirmar alli. --}}
                <a
                    href="{{ route('staff.commands.dead_torrents.index') }}"
                    class="cmd-btn cmd-btn--warning"
                    title="Lista los torrents sin semillas desde hace {{ App\Services\DeadTorrents::hours() }} h, pide la resiembra al confirmar y los borra solos si nadie vuelve a sembrar"
                >
                    <i class="{{ config('other.font-awesome') }} fa-skull-crossbones"></i>
                    Torrents muertos
                </a>
                <a
                    href="{{ route('staff.commands.hitrun_contacts.index') }}"
                    class="cmd-btn cmd-btn--warning"
                    title="Mensaje a cada socio con hit and run con su lista de torrents y horas que faltan; queda anotado en el registro de contactos"
                >
                    <i class="{{ config('other.font-awesome') }} fa-envelope-open-text"></i>
                    Mensajes de H&amp;R
                </a>
                <a
                    href="{{ route('staff.commands.hitrun_bans.index') }}"
                    class="cmd-btn cmd-btn--danger"
                    title="Banea a quien sigue sin hacer caso tras los mensajes de H&R (lista y confirmación), le invita al grupo de baneados para responder y prunea después a quien no respondió"
                >
                    <i class="{{ config('other.font-awesome') }} fa-user-slash"></i>
                    Baneo por H&amp;R abandonado
                </a>
            </div>
        </section>

        {{-- 5. Usuarios y Limpieza --}}
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-users"></i>
                Usuarios y Limpieza
            </h2>
            <div class="panel__body">
                @include('Staff.command._btn', [
                    'action'  => '/dashboard/commands/ban-disposable-users',
                    'label'   => 'Banear usuarios desechables',
                    'icon'    => 'fa-ban',
                    'level'   => 'danger',
                    'tip'     => 'Banear usuarios con emails desechables',
                    'confirm' => '⚠️ Esto baneará automáticamente a todos los usuarios con emails desechables.\n¿Continuar?',
                ])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/deactivate-warnings',        'label' => 'Desactivar avisos expirados', 'icon' => 'fa-bell-slash', 'level' => 'safe',    'tip' => 'Desactivar avisos de usuario expirados'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/generate-telegram-tokens',   'label' => 'Generar tokens Telegram',     'icon' => 'fa-key',        'level' => 'info',    'tip' => 'Generar tokens Telegram solo para usuarios no vinculados que aún no tienen token'])
            </div>
        </section>

        {{-- 6. Pruebas y Utilidades --}}
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-flask"></i>
                Pruebas y Utilidades
            </h2>
            <div class="panel__body">
                @include('Staff.command._btn', ['action' => '/dashboard/commands/test-email',    'label' => 'Email de prueba',   'icon' => 'fa-envelope', 'level' => 'info', 'tip' => 'Enviar email de prueba para verificar configuración SMTP'])
                @include('Staff.command._btn', ['action' => '/dashboard/commands/storage-link',  'label' => 'Enlace storage',    'icon' => 'fa-link',     'level' => 'safe', 'tip' => 'Crear enlace simbólico de almacenamiento público'])
            </div>
        </section>

        {{-- Metadata — Identificación --}}
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-fingerprint"></i>
                Metadata — Identificación
            </h2>
            <div class="panel__body">
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/meta-sync',
                    'label'  => 'Resolver ids faltantes',
                    'icon'   => 'fa-fingerprint',
                    'level'  => 'safe',
                    'tip'    => 'Resuelve ids (tmdb/imdb/tvdb/mal) por consenso multi-proveedor para un lote de torrents sin resolver. El catálogo completo se drena solo cada pocos minutos.',
                ])
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/meta-sync-force',
                    'label'  => 'Re-resolver lote antiguo',
                    'icon'   => 'fa-sync',
                    'level'  => 'safe',
                    'tip'    => 'Re-resuelve un lote de los torrents con la resolución más antigua. Púlsalo varias veces para ir recorriendo el catálogo.',
                ])
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/meta-rotate-covers',
                    'label'  => 'Rotar carátulas',
                    'icon'   => 'fa-images',
                    'level'  => 'safe',
                    'tip'    => 'Rota la carátula activa de cada título desde el pool multi-fuente (TMDB/OMDb/MAL/...). También se ejecuta a diario de forma automática.',
                ])
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/books-sync',
                    'label'  => 'Metadata de libros pendiente',
                    'icon'   => 'fa-book',
                    'level'  => 'safe',
                    'tip'    => 'Rellena la ficha de los torrents de ebook y audiolibro que ya llevan ISBN o ASIN pero aún no tienen metadatos. Sólo procesa lo que falta; también corre solo cada 15 minutos.',
                ])
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/books-sync-force',
                    'label'  => 'Refrescar fichas de libros',
                    'icon'   => 'fa-rotate',
                    'level'  => 'safe',
                    'tip'    => 'Vuelve a pedir a Google Books y Audnexus la ficha de un lote de libros que ya la tienen, por si el proveedor la ha corregido. Ojo: Google Books sólo da 1000 peticiones al día.',
                ])
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/books-sync-authors',
                    'label'  => 'Fichas de autor pendientes',
                    'icon'   => 'fa-user-pen',
                    'level'  => 'safe',
                    'tip'    => 'Libros: cachea foto, biografía y fechas de los autores desde OpenLibrary (también corre solo cada hora). Pelis y series: vuelve a encolar las que tienen torrent y se quedaron sin ficha, sin reparto o sin sinopsis en castellano (la sacan de es-MX o la traducen del inglés), y pide a TMDB las personas que falten. Si no falta nada, no hace nada: se puede pulsar cuando quieras.',
                ])
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/games-sync',
                    'label'  => 'Metadata de juegos pendiente',
                    'icon'   => 'fa-gamepad',
                    'level'  => 'safe',
                    'tip'    => 'Rellena la ficha de IGDB de los torrents de juego que ya llevan id pero aún no tienen metadatos. Sólo procesa lo que falta; también corre solo cada 15 minutos.',
                ])
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/games-sync-force',
                    'label'  => 'Refrescar fichas de juegos',
                    'icon'   => 'fa-rotate',
                    'level'  => 'safe',
                    'tip'    => 'Vuelve a pedir a IGDB la ficha de un lote de juegos que ya la tienen, por si el proveedor la ha corregido.',
                ])
                @include('Staff.command._btn', [
                    'action' => '/dashboard/commands/warm-art-cache',
                    'label'  => 'Precalentar caché de arte',
                    'icon'   => 'fa-fire',
                    'level'  => 'safe',
                    'tip'    => 'Descarga + redimensiona + cachea todas las carátulas/fondos en segundo plano (proceso desacoplado, ~minutos). Idempotente: salta lo ya cacheado. El precalentado incremental ya corre solo cada 30 min.',
                ])
            </div>
        </section>

    </div>

    <style>
        /* ── Grid ──────────────────────────────────────────────────── */
        .cmd-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
        }

        @media (max-width: 1024px) { .cmd-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px)  { .cmd-grid { grid-template-columns: 1fr; } }

        /* ── Emergency banner ───────────────────────────────────────── */
        .cmd-emergency {
            background: linear-gradient(135deg, #7f1d1d, #991b1b);
            border: 1px solid #ef4444;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .cmd-emergency__main {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            color: #fecaca;
            font-size: 1.3125rem;
        }

        .cmd-emergency__url {
            background: rgba(0,0,0,0.4);
            color: #fca5a5;
            padding: 0.15rem 0.6rem;
            border-radius: 0.25rem;
            font-family: monospace;
            font-size: 1.25rem;
            white-space: nowrap;
            border: 1px solid rgba(239,68,68,0.4);
        }

        .cmd-emergency__note {
            color: #fca5a5;
            opacity: 0.75;
            font-size: 1.125rem;
            margin-left: 1.25rem;
        }

        /* ── Command output box ─────────────────────────────────────── */
        .cmd-output {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            background: #0d1117;
            border: 1px solid #30363d;
            border-left: 3px solid #3fb950;
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            margin-bottom: 1.5rem;
        }

        .cmd-output__icon {
            color: #3fb950;
            font-size: 0.875rem;
            margin-top: 0.1rem;
            flex-shrink: 0;
        }

        .cmd-output__text {
            color: #c9d1d9;
            font-family: monospace;
            font-size: 0.8125rem;
            line-height: 1.6;
            margin: 0;
            white-space: pre-wrap;
            overflow-x: auto;
        }

        /* ── Panel heading variants ─────────────────────────────────── */
        .panel__heading--danger {
            background: linear-gradient(135deg, #7f1d1d 0%, #b91c1c 100%) !important;
        }

        /* ── Buttons ────────────────────────────────────────────────── */
        .cmd-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.35rem;
            font-size: 1.25rem;
            font-weight: 500;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-left: 3px solid transparent;
            border-radius: 0.375rem;
            color: #94a3b8;
            cursor: pointer;
            text-align: left;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.1s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cmd-btn:last-child { margin-bottom: 0; }

        .cmd-btn:hover {
            background: rgba(255,255,255,0.09);
            border-color: rgba(255,255,255,0.15);
            color: #e2e8f0;
            transform: translateX(3px);
        }

        .cmd-btn:active { transform: translateX(1px); }
        a.cmd-btn { text-decoration: none; }

        .cmd-btn i { flex-shrink: 0; width: 1.5rem; text-align: center; }

        /* Risk levels */
        .cmd-btn--safe    { border-left-color: #4ade80; }
        .cmd-btn--safe:hover { color: #86efac; }

        .cmd-btn--info    { border-left-color: #60a5fa; }
        .cmd-btn--info:hover { color: #93c5fd; }

        .cmd-btn--warning { border-left-color: #fbbf24; }
        .cmd-btn--warning:hover { color: #fcd34d; }

        .cmd-btn--danger  { border-left-color: #f87171; }
        .cmd-btn--danger:hover { color: #fca5a5; background: rgba(248,113,113,0.08); }

        .cmd-btn--muted   { border-left-color: #64748b; }
        .cmd-btn--muted:hover { color: #94a3b8; }
    </style>

@endsection

@section('scripts')
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}" src="{{ asset('js/staff-command-confirm.js') }}"></script>
@endsection
