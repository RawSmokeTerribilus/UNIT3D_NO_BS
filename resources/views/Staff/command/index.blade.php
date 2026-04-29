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
    @if (session('info'))
        <div
            style="
                background: linear-gradient(135deg, #2d3436 0%, #636e72 100%);
                border-left: 4px solid #00b894;
                padding: 1rem;
                margin-bottom: 2rem;
                border-radius: 0.5rem;
                color: #fff;
                font-family: monospace;
                font-size: 0.875rem;
                white-space: pre-wrap;
                overflow-x: auto;
            "
        >
            {{ session('info') }}
        </div>
    @endif

    {{-- EMERGENCY SECTION --}}
    <div
        style="
            background: #e74c3c;
            color: white;
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
            border-left: 4px solid #c0392b;
        "
    >
        <strong>🚨 SALIDA DE EMERGENCIA:</strong> Si te quedas atrapado en modo mantenimiento, visita:
        <br />
        <code style="background: rgba(0,0,0,0.3); padding: 0.25rem 0.5rem; border-radius: 0.25rem;">
            /dashboard/commands/emergency-disable-maintenance
        </code>
        <br />
        <small>Este endpoint SIEMPRE está accesible y desactiva el modo mantenimiento a la fuerza.</small>
    </div>

    <div
        style="
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        "
    >
        {{-- Maintenance & Site Control Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">🛡️ Mantenimiento y Control del Sitio</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/maintenance-enable') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Activar modo mantenimiento (sitio accesible solo con tu IP)"
                        >
                            Activar mantenimiento
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/maintenance-disable') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Desactivar modo mantenimiento y abrir acceso público"
                        >
                            Desactivar mantenimiento
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- Caching & Performance Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">⚡ Caché y Rendimiento</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Limpiar caché de la aplicación">
                            Limpiar caché
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-view-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Limpiar caché de vistas compiladas">
                            Limpiar vistas
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-route-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Limpiar caché de rutas compiladas">
                            Limpiar rutas
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-config-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Limpiar caché de configuración">
                            Limpiar config
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clear-all-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Limpiar TODA la caché de golpe">
                            Limpiar toda la caché
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/set-all-cache') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Reconstruir y fijar toda la caché">
                            Fijar toda la caché
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/flush-queue') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Vaciar cola Redis (CRÍTICO tras cambios de token)"
                            style="background-color: #e74c3c; color: white;"
                        >
                            🔴 Vaciar cola Redis
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/optimize-clear') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Limpiar caché de optimización">
                            Limpiar optimización
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- Critical Data Operations Panel --}}
        <section class="panelV2" style="grid-column: span 1">
            <h2 class="panel__heading" style="background: #e74c3c; color: white; padding: 0.5rem;">
                🔴 Operaciones de Datos CRÍTICAS
            </h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/update-email-blacklist') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Actualizar lista negra de emails desde fuente remota"
                            style="background-color: #e74c3c; color: white;"
                        >
                            Actualizar lista negra emails
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/telegram-webhook') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Registrar webhook del bot Telegram con la API"
                            style="background-color: #3498db; color: white;"
                        >
                            Registrar Telegram
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/meilisearch-fix') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Vaciar y reparar índices de Meilisearch"
                            style="background-color: #f39c12; color: white;"
                        >
                            Vaciar Meilisearch
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/scout-reindex') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Reindexar todos los torrents en Meilisearch"
                            style="background-color: #f39c12; color: white;"
                        >
                            Reindexar Meilisearch
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form
                        method="POST"
                        action="{{ url('/dashboard/commands/meilisearch-full-repair') }}"
                        onsubmit="return confirm('⚠️ REPARACIÓN COMPLETA DE MEILISEARCH\n\nEsto va a:\n1. Verificar salud de Meilisearch\n2. Crear índices si faltan\n3. Sincronizar filtros/ordenación\n4. BORRAR + reindexar TODOS los torrents\n5. BORRAR + reindexar TODAS las personas\n6. Validar configuración\n\n⏱️ Puede tardar varios minutos.\n🔄 Puede que necesites reiniciar Meilisearch desde Portainer después.\n\n¿Continuar?')"
                    >
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Reparación completa: salud + crear índices + sincronizar config + reindexar torrents y personas + validar (equiv. a NO_BS_meilisearch.sh)"
                            style="background-color: #e74c3c; color: white; font-weight: bold;"
                        >
                            🔧 Reparación completa Meilisearch
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/clean-failed-logins') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Eliminar TODOS los intentos de login fallidos (solo BD, logs se mantienen)"
                            style="background-color: #95a5a6;"
                        >
                            Limpiar logins fallidos
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- Peer & Torrent Management Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">🌱 Gestión de Peers y Torrents</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/flush-old-peers') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Auto-limpiar peers inactivos > 2 horas">
                            Limpiar peers viejos
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/reset-user-flushes') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Resetear cuota diaria de flush de peers para todos los usuarios"
                        >
                            Resetear flushes de usuarios
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/sync-peers') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Sincronizar datos de peers y consistencia">
                            Sincronizar peers
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/sync-torrents-meilisearch') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Sincronizar torrents en Meilisearch">
                            Sincronizar torrents
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- User & Cleanup Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">👥 Usuarios y Limpieza</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/ban-disposable-users') }}">
                        @csrf
                        <button
                            class="form__button form__button--text"
                            title="Banear usuarios con emails desechables"
                        >
                            Banear usuarios desechables
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/deactivate-warnings') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Desactivar avisos de usuario expirados">
                            Desactivar avisos
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/generate-telegram-tokens') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Generar tokens de verificación Telegram">
                            Generar tokens Telegram
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- Testing & Utilities Panel --}}
        <section class="panelV2">
            <h2 class="panel__heading">🔧 Pruebas y Utilidades</h2>
            <div class="panel__body">
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/test-email') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Enviar email de prueba">
                            Email de prueba
                        </button>
                    </form>
                </div>
                <div class="form__group form__group--horizontal">
                    <form method="POST" action="{{ url('/dashboard/commands/storage-link') }}">
                        @csrf
                        <button class="form__button form__button--text" title="Crear enlace simbólico de almacenamiento público">
                            Enlace storage
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <style>
        .panel__heading {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
            color: white;
            padding: 0.75rem;
            border-radius: 0.25rem 0.25rem 0 0;
        }

        .form__button {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            border: 1px solid #bdc3c7;
            background-color: #ecf0f1;
            color: #2c3e50;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .form__button:hover {
            background-color: #d5dbdb;
            border-color: #34495e;
            transform: translateY(-1px);
        }

        .form__button:active {
            transform: translateY(0);
        }
    </style>
@endsection
