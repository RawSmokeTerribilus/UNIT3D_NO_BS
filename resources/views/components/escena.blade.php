@props([
    'id',
    'datos' => [],
    'titulo' => null,
    'panel' => true,
])

@php
    $escenas = ['puerta', 'recepcion', 'bufe', 'bar', 'trofeos', 'pista', 'scumm', 'oficina'];
    $apagadas = auth()->check() && auth()->user()->settings?->show_escenas === false;
    $version = function (string $fichero): int {
        $ruta = public_path('js/escenas/'.$fichero);

        return file_exists($ruta) ? filemtime($ruta) : 1;
    };
@endphp

@if (in_array($id, $escenas, true) && ! $apagadas)
    @php
        $datos = value($datos);
    @endphp
    @if ($panel)
        <section class="panelV2 nobs-escena">
            <header class="panel__header">
                <h2 class="panel__heading">{{ $titulo ?? 'Escena' }}</h2>
                <div class="panel__actions">
                    <div class="panel__action">
                        <button type="button" class="form__button form__button--text" data-escena-toggle="{{ $id }}">
                            Ocultar
                        </button>
                    </div>
                </div>
            </header>
            <div class="panel__body" data-escena-cuerpo="{{ $id }}" style="padding: 0; background: #000">
                <canvas
                    data-escena="{{ $id }}"
                    role="img"
                    aria-label="{{ $titulo ?? 'Escena animada' }}"
                    style="display: block; width: 100%; max-width: 1280px; height: auto; aspect-ratio: 16 / 9; margin: 0 auto; image-rendering: pixelated"
                ></canvas>
            </div>
        </section>
    @else
        <div class="nobs-escena" style="margin: 0 0 16px">
            <canvas
                data-escena="{{ $id }}"
                role="img"
                aria-label="{{ $titulo ?? 'Escena animada' }}"
                style="display: block; width: 100%; height: auto; aspect-ratio: 16 / 9; image-rendering: pixelated; border-radius: 4px"
            ></canvas>
        </div>
    @endif
    <script type="application/json" data-escena-datos="{{ $id }}">@json($datos)</script>
    @once
        <script src="{{ asset('js/escenas/motor.js') }}?v={{ $version('motor.js') }}" defer></script>
        <script src="{{ asset('js/escenas/lanzador.js') }}?v={{ $version('lanzador.js') }}" defer></script>
    @endonce
    <script src="{{ asset('js/escenas/'.$id.'.js') }}?v={{ $version($id.'.js') }}" defer></script>
@endif
