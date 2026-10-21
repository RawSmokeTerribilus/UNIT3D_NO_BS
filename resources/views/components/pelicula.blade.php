@props([
    'user',
])

@php
    $viewer = auth()->user();
    $apagadas = $viewer?->settings?->show_escenas === false;
    $version = function (string $fichero): int {
        $ruta = public_path('js/escenas/'.$fichero);

        return file_exists($ruta) ? filemtime($ruta) : 1;
    };
@endphp

@if ($viewer !== null && ! $apagadas)
    @php
        $pelicula = App\Services\EscenasDatos::pelicula($user, $viewer);
        $versiones = [];
        foreach (array_merge(['motor'], $pelicula['orden']) as $fichero) {
            $versiones[$fichero.'.js'] = $version($fichero.'.js');
        }
        $pelicula['versiones'] = $versiones;
    @endphp

    <section class="panelV2" data-pelicula data-pelicula-base="{{ asset('js/escenas') }}/">
        <h2 class="panel__heading">La película de {{ $user->username }}</h2>
        <div class="panel__body" style="padding: 0; background: #000">
            <div
                data-pelicula-portada
                style="max-width: 1280px; aspect-ratio: 16 / 9; margin: 0 auto; display: grid; place-items: center; text-align: center; background: radial-gradient(circle at 50% 40%, #3a1450, #0b0912 70%); font-family: monospace"
            >
                <div style="display: grid; gap: 10px; padding: 16px">
                    <span style="font-size: 1.6rem; letter-spacing: 0.3em; color: #3ee8ff">NOBS PRESENTA</span>
                    <span style="font-size: clamp(2.4rem, 5vw, 4.8rem); font-weight: 700; color: #ff3ea5; text-shadow: 0 0 8px rgba(255, 62, 165, 0.7); overflow-wrap: anywhere">
                        {{ $user->username }}
                    </span>
                    <span style="font-size: 1.4rem; color: #ffb347">
                        {{ count($pelicula['orden']) }} escenas · de la puerta
                        {{ in_array('oficina', $pelicula['orden'], true) ? 'a la oficina de la rata' : 'al SCUMM Bar' }}
                    </span>
                </div>
            </div>
            <canvas
                data-pelicula-lienzo
                role="img"
                aria-label="La película de {{ $user->username }}"
                style="display: none; width: 100%; max-width: 1280px; height: auto; aspect-ratio: 16 / 9; margin: 0 auto; image-rendering: pixelated"
            ></canvas>
            <div style="height: 3px; background: #2a2340">
                <span data-pelicula-barra style="display: block; height: 100%; width: 0; background: #ff3ea5"></span>
            </div>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 10px 12px">
            <button type="button" class="form__button form__button--filled" data-pelicula-play>▶ Ver la película</button>
            <span data-pelicula-capitulo></span>
        </div>
        <script type="application/json" data-pelicula-datos>@json($pelicula)</script>
    </section>
    <script src="{{ asset('js/escenas/pelicula.js') }}?v={{ $version('pelicula.js') }}" defer></script>
@endif
