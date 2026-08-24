{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
@extends('layout.with-main')

@section('title')
    <title>{{ $rom['title'] }} — {{ $meta['label'] }} — {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li class="breadcrumb">
        <a href="{{ route('retroarch.index') }}" class="breadcrumb__link">RetroArch</a>
    </li>
    <li class="breadcrumb">
        <a href="{{ route('retroarch.system', ['system' => $system]) }}" class="breadcrumb__link">{{ $meta['label'] }}</a>
    </li>
    <li class="breadcrumb--active">{{ $rom['title'] }}</li>
@endsection

@section('page', 'page__retroarch_show')

@php
    // ?debug en la URL del catálogo activa el panel de debug, replicando el
    // mismo gesto que /gaming/<id>?debug del lanzador ScummVM.  No requiere
    // sesión de admin: el contenido revelado es metadata pública para el
    // usuario autenticado que ya puede acceder a la página.
    $isDebug = request()->has('debug');

    // contentPath va sin codificar al pasar por http_build_query (Laravel/PHP
    // se encarga). El iframe lo recibirá ya URL-encoded.
    $iframeQuery = http_build_query([
        'core'      => $core,
        'content'   => $contentPath,
        'autoStart' => '1',
    ] + ($isDebug ? ['debug' => '1'] : []));
    $iframeSrc = '/retroarch/index.html?'.$iframeQuery;
    $isArcade  = $system === 'arcade';

    // Recursos inspeccionables — todos protegidos por auth_request, así que un
    // usuario anónimo viendo este panel sólo ve estados 302→login.
    $diagAssets = [
        'rom'   => '/retroarch/assets/cores/roms/'.rawurlencode($system).'/'.rawurlencode($rom['filename']),
        'cover' => $rom['cover'] ?? null,
        'core'  => '/retroarch/'.$core.'_libretro.wasm',
        'index' => '/retroarch/assets/cores/.index-xhr',
    ];
@endphp

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-play"></i>
            {{ $rom['title'] }}
            <span class="ra-badge">{{ $meta['label'] }}</span>
            <span class="ra-badge ra-badge--core">{{ $core }}</span>
        </h2>
        <div class="panel__body">
            <div class="ra-show__frame-wrap">
                <iframe
                    id="ra-frame"
                    src="{{ $iframeSrc }}"
                    class="ra-show__frame"
                    title="RetroArch — {{ $rom['title'] }}"
                    allow="autoplay; fullscreen; gamepad"
                    allowfullscreen
                ></iframe>
            </div>

            {{-- Debug panel — visible sólo con ?debug en la URL --}}
            @if ($isDebug)
                <section class="ra-debug" id="ra-debug">
                    <header class="ra-debug__head">
                        <i class="{{ config('other.font-awesome') }} fa-terminal"></i>
                        <span class="ra-debug__title">Debug RetroArch</span>
                        <span class="ra-debug__badge" id="ra-debug-badge">init</span>
                        <button type="button" class="ra-debug__btn" id="ra-debug-probe">Probar URLs</button>
                        <button type="button" class="ra-debug__btn" id="ra-debug-clear">Limpiar</button>
                    </header>
                    <div class="ra-debug__cols">
                        <div class="ra-debug__col">
                            <h4>Estado</h4>
                            <pre id="ra-debug-state">{{ json_encode([
                                'system'       => $system,
                                'core'         => $core,
                                'slug'         => $rom['slug'],
                                'title'        => $rom['title'],
                                'filename'     => $rom['filename'],
                                'size_bytes'   => $rom['size'],
                                'cover'        => $rom['cover'],
                                'content_path' => $contentPath,
                                'iframe_src'   => $iframeSrc,
                                'assets'       => $diagAssets,
                            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                        <div class="ra-debug__col">
                            <h4>Logs (mirror del iframe)</h4>
                            <pre id="ra-debug-log"></pre>
                        </div>
                    </div>
                </section>
            @endif

            @if ($isArcade)
                {{-- Para arcade el "free play" real es un DIP-switch por juego, no un --}}
                {{-- ajuste global. El compromiso pragmático: botones explícitos para --}}
                {{-- COIN y START (los emiten como teclas sintéticas al iframe), más un --}}
                {{-- intento automático de insertar 3 monedas tras 6s de carga. --}}
                <div class="ra-show__arcade-controls">
                    <button type="button" class="btn btn--filled" data-ra-action="coin">
                        <i class="{{ config('other.font-awesome') }} fa-coins"></i> Insertar moneda
                    </button>
                    <button type="button" class="btn btn--filled" data-ra-action="start">
                        <i class="{{ config('other.font-awesome') }} fa-play"></i> Start
                    </button>
                    <span class="ra-show__hint-inline">
                        Si el botón no responde, haz clic primero sobre la pantalla del juego para enfocarla.
                    </span>
                </div>
            @endif

            <div class="ra-show__controls-table">
                <h3 class="ra-show__controls-title">Controles del teclado</h3>
                <table class="ra-show__keys">
                    <tbody>
                        @if ($isArcade)
                            <tr><td><kbd>5</kbd> · <kbd>Shift→</kbd> · <kbd>Tab</kbd></td><td>Insertar moneda (SELECT en gamepad)</td></tr>
                            <tr><td><kbd>1</kbd> · <kbd>Enter</kbd></td><td>Empezar partida (START)</td></tr>
                        @else
                            <tr><td><kbd>Shift→</kbd></td><td>SELECT</td></tr>
                            <tr><td><kbd>Enter</kbd></td><td>START</td></tr>
                        @endif
                        <tr><td><kbd>↑</kbd> <kbd>↓</kbd> <kbd>←</kbd> <kbd>→</kbd></td><td>Cruceta / D-pad</td></tr>
                        <tr><td><kbd>Z</kbd> · <kbd>X</kbd></td><td>Botones B · A</td></tr>
                        <tr><td><kbd>A</kbd> · <kbd>S</kbd></td><td>Botones Y · X</td></tr>
                        <tr><td><kbd>Q</kbd> · <kbd>W</kbd></td><td>L · R (gatillos)</td></tr>
                        <tr><td><kbd>F1</kbd></td><td>Menú interno de RetroArch (configurar mandos, DIPs, save state…)</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="ra-show__hints">
                <p>Si el juego falla al cargar, abre el menú con <kbd>F1</kbd> y prueba <em>Limpieza de almacenamiento</em>, luego recarga la página.</p>
                <p>Archivo: <code>{{ $rom['filename'] }}</code> · Tamaño: {{ number_format($rom['size'] / 1024, 0) }} KB</p>
            </div>

            <div class="ra-show__actions">
                <a href="{{ route('retroarch.system', ['system' => $system]) }}" class="btn">
                    <i class="{{ config('other.font-awesome') }} fa-arrow-left"></i> Volver al catálogo
                </a>
            </div>
        </div>
    </section>

    {{-- Loader externo: la CSP de gaming.isolation no permite inline --}}
    {{-- 'unsafe-inline' en script-src-elem; cualquier <script>...</script> --}}
    {{-- inline se bloquea silenciosamente. Por eso movemos toda la lógica --}}
    {{-- a /js/retroarch-launcher.js y le pasamos config por JSON. --}}
    <script id="ra-config" type="application/json">@json([
        'isArcade' => $isArcade,
        'isDebug'  => $isDebug,
    ])</script>
    <script src="/js/retroarch-launcher.js?v={{ filemtime(public_path('js/retroarch-launcher.js')) }}"></script>

    <style>
        .ra-show__frame-wrap { position: relative; width: 100%; aspect-ratio: 16 / 9; background: black; border-radius: 6px; overflow: hidden; margin-bottom: 16px; }
        .ra-show__frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .ra-show__arcade-controls { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px; background: var(--panel_inner_background); border: 1px solid var(--panel_border); border-radius: 6px; margin-bottom: 16px; }
        .ra-show__hint-inline { font-size: 12px; opacity: .7; }
        .ra-show__controls-table { padding: 12px 16px; background: var(--panel_inner_background); border: 1px solid var(--panel_border); border-radius: 6px; margin-bottom: 16px; }
        .ra-show__controls-title { margin: 0 0 8px 0; font-size: 14px; }
        .ra-show__keys { width: 100%; border-collapse: collapse; font-size: 13px; }
        .ra-show__keys td { padding: 4px 8px; border-bottom: 1px dashed var(--panel_border); }
        .ra-show__keys td:first-child { width: 30%; white-space: nowrap; }
        .ra-show__keys tr:last-child td { border-bottom: 0; }
        .ra-show__keys kbd { display: inline-block; padding: 1px 6px; border: 1px solid var(--panel_border); border-bottom-width: 2px; border-radius: 3px; background: var(--panel_background); font-family: monospace; font-size: 11px; min-width: 18px; text-align: center; }
        .ra-show__hints { font-size: 13px; opacity: .85; line-height: 1.6; margin-bottom: 16px; }
        .ra-show__hints code { background: var(--panel_background); padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .ra-show__hints kbd { display: inline-block; padding: 1px 6px; border: 1px solid var(--panel_border); border-bottom-width: 2px; border-radius: 3px; background: var(--panel_background); font-family: monospace; font-size: 11px; }
        .ra-show__actions { display: flex; gap: 8px; }
        .ra-badge { background: var(--panel_background); border: 1px solid var(--panel_border); border-radius: 3px; padding: 2px 8px; font-size: 12px; }
        .ra-badge--core { color: var(--primary); border-color: var(--primary); font-family: monospace; }
        .ra-debug { margin-bottom: 16px; border: 1px solid var(--panel_border); border-radius: 6px; background: var(--panel_inner_background); }
        .ra-debug__head { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--panel_border); flex-wrap: wrap; }
        .ra-debug__title { font-weight: 700; font-size: 13px; flex-shrink: 0; }
        .ra-debug__badge { font-size: 11px; background: var(--panel_background); border: 1px solid var(--panel_border); border-radius: 3px; padding: 1px 6px; margin-right: auto; }
        .ra-debug__btn { font-size: 11px; padding: 3px 10px; background: var(--panel_background); border: 1px solid var(--panel_border); border-radius: 3px; cursor: pointer; color: var(--body_text); }
        .ra-debug__btn:hover { border-color: var(--primary); }
        .ra-debug__cols { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 8px; }
        @media (max-width: 760px) { .ra-debug__cols { grid-template-columns: 1fr; } }
        .ra-debug__col h4 { margin: 0 0 4px 0; font-size: 11px; opacity: .7; text-transform: uppercase; letter-spacing: .5px; }
        .ra-debug__col pre { background: #0a0a0a; color: #c8ffa0; font-family: monospace; font-size: 11px; line-height: 1.45; margin: 0; padding: 8px 10px; border-radius: 4px; min-height: 220px; max-height: 320px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
        #ra-debug-log:empty::before { content: '(esperando logs…)'; opacity: .4; }
    </style>
@endsection
