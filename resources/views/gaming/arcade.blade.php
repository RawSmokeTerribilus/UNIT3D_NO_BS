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
    <title>Arcade — {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Juegos en el navegador: multijugador entre socios, consolas clásicas y aventuras gráficas." />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb--active">Arcade</li>
@endsection

@section('page', 'page__arcade')

@section('main')
    @include('gaming.partials.arcade-estilos')

    {{-- Tres puertas; las imágenes ya dicen qué hay detrás --}}
    <p class="arcade-pista">▸ Elige una puerta</p>
    <nav class="arcade-puertas" aria-label="Secciones del Arcade">
        @include('gaming.partials.puerta', ['puerta' => 'multijugador', 'nombre' => 'Multijugador entre socios', 'href' => $arenaAbierta ? route('arena.index') : null])
        @include('gaming.partials.puerta', ['puerta' => 'consolas', 'nombre' => 'Consolas clásicas', 'href' => route('retroarch.index')])
        @include('gaming.partials.puerta', ['puerta' => 'aventuras', 'nombre' => 'Aventuras gráficas', 'href' => route('gaming.aventuras')])
    </nav>

    {{-- Escenas de las secciones cosidas en un solo lienzo, en bucle (falta la de la Arena) --}}
    <x-escenas-cosidas
        id="arcade"
        titulo="Escenas del Arcade"
        :datos="fn () => [
            'pista' => App\Services\EscenasDatos::pista($sistemas),
            'scumm' => App\Services\EscenasDatos::scumm($juegos),
        ]"
    />
@endsection
