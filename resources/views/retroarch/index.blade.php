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
    <title>Consolas — Arcade — {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Catálogo de juegos retro jugables en el navegador con RetroArch." />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb">
        <a href="{{ route('gaming.index') }}" class="breadcrumb__link">Arcade</a>
    </li>
    <li class="breadcrumb--active">Consolas</li>
@endsection

@section('page', 'page__retroarch')

@section('main')
    @include('gaming.partials.arcade-estilos')
    @include('gaming.partials.puerta', ['puerta' => 'consolas', 'nombre' => 'Consolas clásicas', 'cabecera' => true])
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-gamepad"></i>
            Consolas
        </h2>
        <div class="panel__body">
            <p class="arcade-intro">Clásicos de consola en el navegador. Las partidas guardadas van a tu cuenta.</p>

            @if (count($systems) === 0)
                <div class="ra-empty">
                    No hay catálogo. Ejecuta <code>php artisan retroarch:scan-roms</code> primero.
                </div>
            @else
                <ul class="arcade-fichas arcade-sala-consolas">
                    @foreach ($systems as $sys)
                        @include('gaming.partials.ficha', [
                            'forma'    => 'logo',
                            'imagen'   => $sys['icon'] ?? null,
                            'titulo'   => $sys['label'],
                            'detalle'  => number_format($sys['rom_count'], 0, ',', '.').' juegos',
                            'href'     => $sys['unavailable'] ? null : route('retroarch.system', ['system' => $sys['slug']]),
                            'insignia' => $sys['unavailable'] ? 'Cerrado' : null,
                        ])
                    @endforeach
                </ul>
            @endif

            <a class="ra-freeplay" href="/retroarch/index.html">
                <i class="{{ config('other.font-awesome') }} fa-upload"></i>
                Modo libre — sube tu propio ROM (3DO, PSX, DOS, C64, Vectrex, Doom, Cave Story…)
            </a>
        </div>
    </section>

    <style>
        .ra-empty { padding: 24px; text-align: center; opacity: .8; border: 1px dashed var(--panel_border); border-radius: 6px; }
        .ra-freeplay { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: var(--panel_background); border: 1px solid var(--panel_border); border-radius: 6px; color: var(--body_text); text-decoration: none; font-size: 13px; opacity: .85; }
        .ra-freeplay:hover { border-color: var(--primary); opacity: 1; }
    </style>

    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        // Hide broken system icons. Inline onerror= is blocked by the gaming CSP
        // (script-src-attr); delegate in capture phase ('error' doesn't bubble).
        document.addEventListener('error', function (e) {
            if (e.target.tagName === 'IMG' && e.target.closest('.arcade-ficha__imagen')) {
                e.target.style.display = 'none';
            }
        }, true);
    </script>
@endsection
