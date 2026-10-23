{{--
    NOBS — varias escenas cosidas en un solo lienzo, una detrás de otra y en bucle
    (el motor de la película del perfil: public/js/escenas/pelicula.js con
    data-pelicula-auto + data-pelicula-bucle). Ocupa lo que una sola escena.

    id:      clave para recordar «Ocultar» por navegador
    titulo:  cabecera del panel
    datos:   [ idEscena => datos ], en el orden en que se reproducen
--}}
@props([
    'id',
    'titulo',
    'datos' => [],
])

@php
    $apagadas = auth()->check() && auth()->user()->settings?->show_escenas === false;
    $version = function (string $fichero): int {
        $ruta = public_path('js/escenas/'.$fichero);

        return file_exists($ruta) ? filemtime($ruta) : 1;
    };
@endphp

@if (! $apagadas)
    @php
        $datos = value($datos);
        $orden = array_keys($datos);
        $versiones = [];
        foreach (array_merge(['motor'], $orden) as $fichero) {
            $versiones[$fichero.'.js'] = $version($fichero.'.js');
        }
        $cfg = ['orden' => $orden, 'datos' => $datos, 'versiones' => $versiones];
    @endphp
    <section class="panelV2 nobs-escena" data-pelicula data-pelicula-auto data-pelicula-bucle data-pelicula-id="{{ $id }}" data-pelicula-base="{{ asset('js/escenas') }}/">
        <header class="panel__header">
            <h2 class="panel__heading">{{ $titulo }} <small data-pelicula-capitulo style="font-weight: normal; opacity: .6; margin-left: 8px"></small></h2>
            <div class="panel__actions">
                <div class="panel__action">
                    <button type="button" class="form__button form__button--text" data-pelicula-toggle>Ocultar</button>
                </div>
            </div>
        </header>
        <div class="panel__body" data-pelicula-cuerpo style="padding: 0; background: #000">
            <canvas
                data-pelicula-lienzo
                role="img"
                aria-label="{{ $titulo }}"
                style="display: none; width: 100%; max-width: 1280px; height: auto; aspect-ratio: 16 / 9; margin: 0 auto; image-rendering: pixelated"
            ></canvas>
            <div style="height: 3px; background: #2a2340">
                <span data-pelicula-barra style="display: block; height: 100%; width: 0; background: #ff3ea5"></span>
            </div>
            {{-- pelicula.js exige el botón ▶; con auto no se llega a ver --}}
            <button type="button" data-pelicula-play hidden>▶</button>
        </div>
        <script type="application/json" data-pelicula-datos>@json($cfg)</script>
    </section>
    <script src="{{ asset('js/escenas/pelicula.js') }}?v={{ $version('pelicula.js') }}" defer></script>
@endif
