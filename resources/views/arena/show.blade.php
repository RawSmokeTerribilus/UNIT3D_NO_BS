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
    <title>{{ $modulo['nombre'] }} — Arena — {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li class="breadcrumb">
        <a href="{{ route('gaming.index') }}" class="breadcrumb__link">Arcade</a>
    </li>
    <li class="breadcrumb">
        <a href="{{ route('arena.index') }}" class="breadcrumb__link">Multijugador</a>
    </li>
    <li class="breadcrumb--active">{{ $modulo['nombre'] }}</li>
@endsection

@section('page', 'page__arena_show')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading arena-show__heading">
            <i class="{{ config('other.font-awesome') }} {{ $modulo['icono'] ?? 'fa-gamepad' }}"></i>
            {{ $modulo['nombre'] }}
            <button type="button" id="arena-fullscreen" class="form__button form__button--outlined arena-show__fullscreen">
                <i class="{{ config('other.font-awesome') }} fa-expand"></i>
                Pantalla completa
            </button>
        </h2>
        <div class="panel__body">
            @if ($cerrada)
                <p class="arena-show__cerrada">
                    <i class="{{ config('other.font-awesome') }} fa-eye-slash"></i>
                    La Arena está cerrada a los socios: la ves porque eres owner/admin.
                </p>
            @endif
            {{-- Todo lo de dentro lo sirve el módulo (fuera de UNIT3D) por /arena-app/<slug>/ --}}
            <div class="arena-show__frame-wrap">
                <iframe
                    id="arena-frame"
                    src="/arena-app/{{ $slug }}/"
                    class="arena-show__frame"
                    title="{{ $modulo['nombre'] }}"
                    allow="autoplay; fullscreen; gamepad; cross-origin-isolated"
                    allowfullscreen
                ></iframe>
            </div>
        </div>
    </section>

    {{-- Sin JS inline: la CSP de gaming.isolation lo bloquea --}}
    <script src="/js/arena-launcher.js?v={{ filemtime(public_path('js/arena-launcher.js')) }}"></script>

    <style>
        .arena-show__heading { display: flex; align-items: center; gap: 8px; }
        .arena-show__fullscreen { margin-left: auto; }
        .arena-show__cerrada { margin: 0 0 12px; padding: 8px 12px; border: 1px dashed var(--panel_border); border-radius: 6px; opacity: .85; }
        .arena-show__frame-wrap { position: relative; width: 100%; aspect-ratio: 16 / 9; min-height: 600px; background: black; border-radius: 6px; overflow: hidden; }
        .arena-show__frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .arena-show__frame-wrap:fullscreen { border-radius: 0; }
    </style>
@endsection
