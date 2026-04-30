@extends('layout.with-main')

@section('title')
    <title>{{ $juego['titulo'] }} — Arcade — {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Juega a {{ $juego['titulo'] }} en el navegador." />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a class="breadcrumb__link" href="{{ route('gaming.index') }}">Arcade</a>
    </li>
    <li class="breadcrumb--active">
        {{ $juego['titulo'] }}
    </li>
@endsection

@section('page', 'page__gaming-player')

@section('main')
    {{-- Metadatos del juego y partidas inyectados directamente en Blade --}}
    {{-- El launcher los lee en arranque: cero round-trips HTTP adicionales --}}
    {{-- NOTA: usa json_encode puro, no Js::from() que produce JSON.parse("...") --}}
    @php $gamingConfig = json_encode(['gameId' => $juego['id'], 'scummId' => $juego['scummvm_id'], 'engineId' => $juego['engine_id'], 'files' => $juego['files'], 'syncUrl' => route('gaming.saves.sync'), 'saves' => $saveManifest, 'csrfToken' => csrf_token(), 'scummIni' => $scummIni], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); @endphp
    <script id="gaming-config" type="application/json">{!! $gamingConfig !!}</script>

    <section class="panelV2 gaming-player-panel">
        <div class="gaming-player-header">
            <a href="{{ route('gaming.index') }}" class="gaming-back-btn">
                <i class="{{ config('other.font-awesome') }} fa-arrow-left"></i>
                Volver al Arcade
            </a>
            <h2 class="gaming-player-title">
                <i class="{{ config('other.font-awesome') }} fa-gamepad"></i>
                {{ $juego['titulo'] }}
                <span class="gaming-player-version">{{ $juego['version'] }}</span>
            </h2>
            <div class="gaming-status" id="gaming-status">
                <span id="gaming-status-text">Cargando motor…</span>
            </div>
        </div>

        <div class="gaming-canvas-wrapper">
            {{-- Overlay "▶ Jugar" — visible por defecto. El click crea el AudioContext
                 dentro de un gesto de usuario (requerido por Chrome). El engine NO se
                 carga hasta que el usuario pulse este botón. --}}
            <div id="gaming-play-overlay">
                <div class="gaming-play-overlay__bg" style="background-image:url('{{ $juego['cover'] }}')"></div>
                <div class="gaming-play-overlay__content">
                    <img src="{{ $juego['cover'] }}" class="gaming-play-overlay__cover" alt="{{ $juego['titulo'] }}">
                    <h3 class="gaming-play-overlay__title">{{ $juego['titulo'] }}</h3>
                    <span class="gaming-play-overlay__version">{{ $juego['version'] }} · {{ $juego['año'] }}</span>
                    <button id="gaming-play-btn" class="gaming-play-overlay__btn">
                        <i class="{{ config('other.font-awesome') }} fa-play"></i>
                        Jugar
                    </button>
                    {{-- Escotilla de emergencia: borra IndexedDB + Cache Storage + SW
                         para esta web. Útil cuando el motor queda en estado inválido
                         y reinstalar la pestaña no basta (caso "nuke browser data"). --}}
                    <button id="gaming-reset-btn"
                            class="gaming-play-overlay__reset"
                            type="button"
                            title="Borra caché del navegador para este sitio (no afecta partidas en la nube)">
                        Resetear datos del juego
                    </button>
                </div>
            </div>

            {{-- Pantalla de carga — oculta hasta que el usuario pulsa Jugar --}}
            <div class="gaming-loading" id="gaming-loading" style="display:none">
                <div class="gaming-loading__icon">
                    <i class="{{ config('other.font-awesome') }} fa-floppy-disk fa-spin"></i>
                </div>
                <p class="gaming-loading__text" id="gaming-loading-text">
                    Iniciando ScummVM…
                </p>
                {{-- Stubs requeridos por el engine de ScummVM para mostrar progreso de descarga --}}
                <div id="download-modal" style="display:none;width:100%;max-width:420px;padding:8px 0">
                    <p id="download-modal-title" style="font-size:.75rem;opacity:.7;margin:0 0 6px;text-align:center"><span>Descargando motor…</span></p>
                    <div style="background:rgba(255,255,255,.15);border-radius:4px;overflow:hidden;height:6px">
                        <div id="download-modal-progress-fill" style="height:100%;width:0%;background:var(--primary, #4caf50)"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:.65rem;opacity:.55;margin-top:4px">
                        <span id="download-modal-progress-text"></span>
                        <span id="download-modal-speed-text"></span>
                    </div>
                </div>

                <p class="gaming-loading__sub">
                    El motor puede tardar unos segundos la primera vez.
                </p>
            </div>

            {{-- Canvas principal donde ScummVM renderiza el juego --}}
            {{-- id="canvas" es requerido por el engine de Emscripten --}}
            <canvas
                id="canvas"
                class="gaming-canvas"
                tabindex="0"
            ></canvas>
        </div>

        <div class="gaming-player-footer">
            <div class="gaming-controls-hint">
                <i class="{{ config('other.font-awesome') }} fa-keyboard"></i>
                <strong>F5</strong> Guardar/Cargar &nbsp;|&nbsp;
                <strong>Ctrl+S</strong> Guardar rápido &nbsp;|&nbsp;
                <strong>Esc</strong> Saltar cinemática (solo en ventana)
            </div>
            <div class="gaming-save-status" id="gaming-save-status"></div>
            <button id="gaming-fullscreen-btn" class="gaming-fullscreen-btn" title="Pantalla completa" disabled>
                <i class="{{ config('other.font-awesome') }} fa-expand"></i>
                Pantalla completa
            </button>
        </div>
    </section>

    {{-- Panel de debug — siempre disponible, se activa con ?debug en la URL --}}
    <section class="panelV2" id="gaming-debug-panel" style="display:none;margin-top:8px">
        <header class="panelV2__header" style="display:flex;align-items:center;gap:8px;padding:6px 12px">
            <i class="{{ config('other.font-awesome') }} fa-terminal"></i>
            <span style="font-weight:700;font-size:.8rem">Debug ScummVM</span>
            <span id="debug-status-badge" style="font-size:.65rem;background:var(--panel_inner_background);border:1px solid var(--panel_border);border-radius:3px;padding:1px 6px;margin-left:auto">—</span>
            <button id="debug-clear-btn" style="font-size:.65rem;padding:2px 8px;background:var(--panel_inner_background);border:1px solid var(--panel_border);border-radius:3px;cursor:pointer">Limpiar</button>
        </header>
        <pre id="debug-log" style="font-size:.7rem;font-family:monospace;background:#0a0a0a;color:#c8ffa0;margin:0;padding:8px 12px;max-height:260px;overflow-y:auto;white-space:pre-wrap;word-break:break-all"></pre>
    </section>

    {{-- Debug panel activation + event listeners are handled by scummvm-launcher.js --}}

    <style>
        .gaming-player-panel {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .gaming-player-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: var(--panel_background);
            border-bottom: 1px solid var(--panel_border);
            flex-wrap: wrap;
        }

        .gaming-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--body_text);
            opacity: .7;
            text-decoration: none;
            padding: 4px 10px;
            border: 1px solid var(--panel_border);
            border-radius: 4px;
            transition: opacity .15s;
            flex-shrink: 0;
        }

        .gaming-back-btn:hover { opacity: 1; }

        .gaming-player-title {
            font-size: 17px;
            font-weight: 700;
            margin: 0;
            flex: 1;
        }

        .gaming-player-version {
            font-size: 12px;
            font-weight: 400;
            background: var(--panel_inner_background);
            border: 1px solid var(--panel_border);
            border-radius: 3px;
            padding: 2px 6px;
            margin-left: 8px;
            vertical-align: middle;
        }

        .gaming-status {
            font-size: 13px;
            color: var(--primary);
            flex-shrink: 0;
        }

        .gaming-canvas-wrapper {
            position: relative;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
        }

        .gaming-loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #000;
            color: #fff;
            z-index: 10;
            gap: 12px;
            padding: 32px;
        }

        .gaming-loading__icon {
            font-size: 2.5rem;
            color: var(--primary);
        }

        .gaming-loading__text {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .gaming-loading__sub {
            font-size: 13px;
            opacity: .7;
            margin: 0;
        }

        .gaming-canvas {
            display: block;
            max-width: 100%;
            cursor: default;
            outline: none;
        }

        .gaming-player-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px;
            background: var(--panel_background);
            border-top: 1px solid var(--panel_border);
            flex-wrap: wrap;
            gap: 8px;
        }

        .gaming-controls-hint {
            font-size: 13px;
            color: var(--body_text);
            opacity: .85;
        }

        .gaming-save-status {
            font-size: 13px;
            color: var(--primary);
            min-height: 1em;
            font-weight: 600;
        }

        .gaming-fullscreen-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            font-size: 13px;
            background: var(--panel_inner_background);
            border: 1px solid var(--panel_border);
            border-radius: 4px;
            cursor: pointer;
            color: var(--body_text);
            opacity: .4;
            transition: opacity .15s;
            flex-shrink: 0;
        }

        .gaming-fullscreen-btn:not([disabled]) { opacity: 1; cursor: pointer; }
        .gaming-fullscreen-btn:not([disabled]):hover { opacity: .75; }
        .gaming-fullscreen-btn[disabled] { cursor: not-allowed; }

        /* ── Play overlay ──────────────────────────────────────────────────── */
        #gaming-play-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20;
            overflow: hidden;
        }

        .gaming-play-overlay__bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            filter: blur(6px) brightness(.4);
            transform: scale(1.05); /* evita bordes blancos del blur */
        }

        .gaming-play-overlay__content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            padding: 24px;
            text-align: center;
        }

        .gaming-play-overlay__cover {
            height: 160px;
            border-radius: 8px;
            box-shadow: 0 6px 24px rgba(0,0,0,.7);
        }

        .gaming-play-overlay__title {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 6px rgba(0,0,0,.9);
        }

        .gaming-play-overlay__version {
            color: rgba(255,255,255,.6);
            font-size: 12px;
        }

        .gaming-play-overlay__btn {
            margin-top: 6px;
            padding: 12px 32px;
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #3b006e, #6b00c9);
            color: #e0b0ff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 0 16px #a020f0aa, 0 0 4px #c060ffaa;
            transition: transform .1s, box-shadow .1s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .gaming-play-overlay__btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 24px #a020f0cc, 0 0 8px #c060ffcc;
        }

        .gaming-play-overlay__btn:active { transform: scale(.97); }

        .gaming-play-overlay__reset {
            margin-top: 10px;
            padding: 4px 10px;
            background: transparent;
            color: rgba(255,255,255,.45);
            border: none;
            font-size: 11px;
            cursor: pointer;
            text-decoration: underline;
            text-decoration-style: dotted;
            transition: color .15s;
        }

        .gaming-play-overlay__reset:hover { color: rgba(255,255,255,.85); }
    </style>

    {{-- El launcher se carga DESPUÉS del DOM — nunca pasa por Vite --}}
    {{-- Cache-buster con filemtime para burlar caché de CDN (Cloudflare) y navegador --}}
    <script src="/js/scummvm-launcher.js?v={{ filemtime(public_path('js/scummvm-launcher.js')) }}"></script>
@endsection
