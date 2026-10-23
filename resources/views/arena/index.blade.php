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
    <title>Arena — {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Juegos multijugador entre socios, en el navegador." />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb--active">Arena</li>
@endsection

@section('page', 'page__arena')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-chess-board"></i>
            Arena — partidas entre socios
        </h2>
        <div class="panel__body">
            @if ($cerrada)
                <p class="arena-intro">
                    <i class="{{ config('other.font-awesome') }} fa-eye-slash"></i>
                    La Arena está cerrada a los socios: la ves porque eres owner/admin.
                </p>
            @endif
            <p class="arena-intro">
                Juegos multijugador que corren en tu navegador, sin instalar nada. Las partidas
                las sirve el tracker y solo entran socios.
            </p>
            <div class="arena-grid">
                @forelse ($modulos as $slug => $modulo)
                    <a class="arena-card" href="{{ route('arena.show', ['slug' => $slug]) }}">
                        <i class="{{ config('other.font-awesome') }} {{ $modulo['icono'] ?? 'fa-gamepad' }} arena-card__icon"></i>
                        <span class="arena-card__name">{{ $modulo['nombre'] }}</span>
                        <span class="arena-card__desc">{{ $modulo['descripcion'] ?? '' }}</span>
                    </a>
                @empty
                    <p>Ahora mismo no hay ningún juego abierto.</p>
                @endforelse
            </div>
        </div>
    </section>

    <style>
        .arena-intro { margin-bottom: 16px; opacity: .85; }
        .arena-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
        .arena-card { display: flex; flex-direction: column; gap: 6px; padding: 16px; background: var(--panel_inner_background); border: 1px solid var(--panel_border); border-radius: 6px; color: var(--body_text); text-decoration: none; }
        .arena-card:hover { border-color: var(--primary); }
        .arena-card__icon { font-size: 28px; color: var(--primary); }
        .arena-card__name { font-weight: 700; font-size: 16px; }
        .arena-card__desc { font-size: 13px; opacity: .8; }
    </style>
@endsection
