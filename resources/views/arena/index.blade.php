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
    <li class="breadcrumb">
        <a href="{{ route('gaming.index') }}" class="breadcrumb__link">Arcade</a>
    </li>
    <li class="breadcrumb--active">Multijugador</li>
@endsection

@section('page', 'page__arena')

@section('main')
    @include('gaming.partials.arcade-estilos')
    @include('gaming.partials.puerta', ['puerta' => 'multijugador', 'nombre' => 'Multijugador entre socios', 'cabecera' => true])
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-chess-board"></i>
            Multijugador
        </h2>
        <div class="panel__body">
            @if ($cerrada)
                <p class="arcade-intro">
                    <i class="{{ config('other.font-awesome') }} fa-eye-slash"></i>
                    La Arena está cerrada a los socios: la ves porque eres owner/admin.
                </p>
            @endif
            <p class="arcade-intro">
                Partidas entre socios, en el navegador y sin instalar nada.
                @if (config('arena.wiki_url'))
                    <a href="{{ config('arena.wiki_url') }}">
                        <i class="{{ config('other.font-awesome') }} fa-circle-question"></i>
                        ¿Cómo funciona?
                    </a>
                @endif
            </p>
            @if ($modulos === [])
                <p>Ahora mismo no hay ningún juego abierto.</p>
            @else
                <ul class="arcade-fichas arcade-fichas--panoramica arcade-sala-multijugador">
                    @foreach ($modulos as $slug => $modulo)
                        @php($img = $modulo['imagen'] ?? null)
                        @include('gaming.partials.ficha', [
                            'forma'   => 'panoramica',
                            'imagen'  => $img ? $img.'-800.webp' : null,
                            'srcset'  => $img ? $img.'-800.webp 800w, '.$img.'-1600.webp 1600w' : null,
                            'titulo'  => $modulo['nombre'],
                            'detalle' => $modulo['detalle'] ?? ($modulo['descripcion'] ?? null),
                            'href'    => route('arena.show', ['slug' => $slug]),
                        ])
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

@endsection
