{{--
    Leyenda de controles del teclado por sistema.

    Teclas: las de serie de RetroArch (no las cambiamos): Z = B, X = A, A = Y, S = X,
    Q = L, W = R, Mayús derecha = SELECT, Enter = START. Cada core reparte esos botones
    del mando virtual (RetroPad) a los de su consola; aquí se traduce a los nombres de
    los botones de verdad. Sistema sin entrada = leyenda genérica del RetroPad.
    Se rellena sistema a sistema, comprobando la asignación del core en su documentación.
--}}
@php
    $mapas = [
        // Snes9x: RetroPad y mando de SNES son el mismo (B, A, Y, X, L, R)
        'snes' => [
            ['↑ ↓ ← →', 'Cruceta'],
            ['Z', 'B (botón amarillo, abajo)'],
            ['X', 'A (botón rojo, derecha)'],
            ['A', 'Y (botón verde, izquierda)'],
            ['S', 'X (botón azul, arriba)'],
            ['Q · W', 'L · R (gatillos)'],
            ['Mayús derecha', 'SELECT'],
            ['Enter', 'START'],
        ],
        // Nestopia (docs.libretro.com/library/nestopia): B, A y sus turbos en Y, X
        'nes' => [
            ['↑ ↓ ← →', 'Cruceta'],
            ['Z', 'B (izquierda)'],
            ['X', 'A (derecha)'],
            ['A', 'B turbo (mantener = disparo repetido)'],
            ['S', 'A turbo (mantener = disparo repetido)'],
            ['Mayús derecha', 'SELECT'],
            ['Enter', 'START'],
        ],
        // Gambatte (docs.libretro.com/library/gambatte): B, A; L/R cambian la paleta del Game Boy clásico
        'gb' => [
            ['↑ ↓ ← →', 'Cruceta'],
            ['Z', 'B'],
            ['X', 'A'],
            ['Mayús derecha', 'SELECT'],
            ['Enter', 'START'],
            ['Q · W', 'Paleta de colores anterior · siguiente (el verde de siempre o a color)'],
        ],
        'gbc' => [
            ['↑ ↓ ← →', 'Cruceta'],
            ['Z', 'B'],
            ['X', 'A'],
            ['Mayús derecha', 'SELECT'],
            ['Enter', 'START'],
        ],
        // mGBA (docs.libretro.com/library/mgba): B, A, L, R (los turbos dependen de la versión del core: fuera)
        'gba' => [
            ['↑ ↓ ← →', 'Cruceta'],
            ['Z', 'B'],
            ['X', 'A'],
            ['Q · W', 'L · R (gatillos)'],
            ['Mayús derecha', 'SELECT'],
            ['Enter', 'START'],
        ],
        // Genesis Plus GX (docs.libretro.com/library/genesis_plus_gx): Y→A, B→B, A→C; 6 botones: L→X, X→Y, R→Z
        'genesis' => [
            ['↑ ↓ ← →', 'Cruceta'],
            ['A', 'A'],
            ['Z', 'B'],
            ['X', 'C'],
            ['Enter', 'START'],
            ['Q · S · W', 'X · Y · Z (solo juegos de mando de 6 botones, p. ej. Street Fighter II)'],
            ['Mayús derecha', 'MODE (mando de 6 botones)'],
        ],
        // Mismo core: mando de Master System de dos botones; START = botón PAUSE de la consola
        'sms' => [
            ['↑ ↓ ← →', 'Cruceta'],
            ['Z', 'Botón 1 (suele ser empezar y saltar/disparar)'],
            ['X', 'Botón 2'],
            ['Enter', 'PAUSE (el botón de la consola)'],
        ],
        'gg' => [
            ['↑ ↓ ← →', 'Cruceta'],
            ['Z', 'Botón 1'],
            ['X', 'Botón 2'],
            ['Enter', 'START'],
        ],
        // Beetle PCE Fast (docs.libretro.com/library/beetle_pce_fast), mando de 2 botones: B→II, A→I, Start→RUN
        'pce' => [
            ['↑ ↓ ← →', 'Cruceta'],
            ['Z', 'II (izquierda)'],
            ['X', 'I (derecha)'],
            ['Mayús derecha', 'SELECT'],
            ['Enter', 'RUN (empezar / pausa)'],
        ],
    ];
    $mapa = $mapas[$system] ?? null;
@endphp

<div class="ra-show__controls-table">
    <h3 class="ra-show__controls-title">
        Controles del teclado
        @if ($mapa)
            · {{ config('retroarch.systems.'.$system.'.label', $system) }}
        @endif
    </h3>
    <table class="ra-show__keys">
        <tbody>
            @if ($mapa)
                @foreach ($mapa as [$teclas, $boton])
                    <tr>
                        <td>
                            {{-- « · » separa teclas; la cruceta se pinta flecha a flecha --}}
                            @foreach (explode(' · ', $teclas) as $i => $tecla)
                                @if ($i > 0) · @endif
                                @foreach ($tecla === '↑ ↓ ← →' ? explode(' ', $tecla) : [$tecla] as $t)
                                    <kbd>{{ $t }}</kbd>
                                @endforeach
                            @endforeach
                        </td>
                        <td>{{ $boton }}</td>
                    </tr>
                @endforeach
            @else
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
            @endif
            <tr><td><kbd>F1</kbd></td><td>Menú interno de RetroArch (configurar mandos, DIPs, save state…)</td></tr>
        </tbody>
    </table>
</div>
