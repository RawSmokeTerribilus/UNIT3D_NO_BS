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
    <title>Aventuras gráficas — Arcade — {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Juega a clásicos de aventura gráfica directamente en el navegador." />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb">
        <a href="{{ route('gaming.index') }}" class="breadcrumb__link">Arcade</a>
    </li>
    <li class="breadcrumb--active">Aventuras gráficas</li>
@endsection

@section('page', 'page__gaming')

@section('main')
    @include('gaming.partials.arcade-estilos')
    @include('gaming.partials.puerta', ['puerta' => 'aventuras', 'nombre' => 'Aventuras gráficas', 'cabecera' => true])
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-skull-crossbones"></i>
            Aventuras gráficas
        </h2>
        <div class="panel__body">
            <p class="arcade-intro">Las aventuras gráficas de siempre, en el navegador. Las partidas guardadas van a tu cuenta.</p>

            <ul class="arcade-fichas arcade-fichas--caratula arcade-sala-aventuras">
                @foreach ($juegos as $juego)
                    @include('gaming.partials.ficha', [
                        'forma'   => 'caratula',
                        'imagen'  => $juego['cover'],
                        'titulo'  => $juego['titulo'],
                        'detalle' => $juego['año'].' · '.$juego['idioma'],
                        'href'    => route('gaming.show', ['gameId' => $juego['id']]),
                        'accion'  => 'Jugar ›',
                    ])
                @endforeach
            </ul>

            <div class="gaming-notice">
                <i class="{{ config('other.font-awesome') }} fa-circle-info"></i>
                Los juegos corren íntegramente en tu navegador. Necesitas una conexión estable
                para la carga inicial. Las partidas se sincronizan con tu cuenta automáticamente
                al guardar dentro del juego.
            </div>

        </div>
    </section>

    <style>
        .gaming-notice {
            background: var(--panel_inner_background);
            border: 1px solid var(--panel_border);
            border-left: 4px solid var(--primary);
            border-radius: 4px;
            padding: 12px 16px;
            font-size: 14px;
            color: var(--body_text);
            opacity: .85;
        }

        .gaming-notice i {
            margin-right: 6px;
            color: var(--primary);
        }

    </style>

    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        // Hide broken cover images. Inline onerror= is blocked by the gaming CSP
        // (script-src-attr, no 'unsafe-hashes'); delegate instead. Capture phase
        // because 'error' does not bubble.
        document.addEventListener('error', function (e) {
            if (e.target.tagName === 'IMG' && e.target.closest('.arcade-ficha__imagen')) {
                e.target.style.display = 'none';
            }
        }, true);
    </script>
@endsection
