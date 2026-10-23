{{--
    NOBS — Arcade: ficha común de las salas (juegos de la Arena, consolas, aventuras, ROMs).
    La imagen manda; debajo, título y una línea de detalle. Va dentro de <ul class="arcade-fichas">.

    $forma:    panoramica (16:10, recortada) | logo (logo sobre placa clara) | caratula (3:4) | caja (4:3, entera)
    $imagen:   URL de la imagen (null = hueco con ?)
    $srcset:   opcional
    $titulo:   texto (alt de la imagen y línea de abajo)
    $detalle:  opcional, línea pequeña
    $href:     destino; null = cerrada
    $accion:   texto de la pastilla al pasar («Entrar ›», «Jugar ›»)
    $insignia: opcional (p. ej. «Cerrado»)
--}}
@php
    $srcset ??= null;
    $detalle ??= null;
    $accion ??= 'Entrar ›';
    $insignia ??= null;
    $etiqueta = $href ? 'a' : 'div';
@endphp
<li class="arcade-ficha-item">
    <{{ $etiqueta }} class="arcade-ficha arcade-ficha--{{ $forma }} {{ $href ? '' : 'arcade-ficha--cerrada' }}" @if ($href) href="{{ $href }}" @endif>
        <span class="arcade-ficha__imagen">
            @if ($imagen)
                <img src="{{ $imagen }}" @if ($srcset) srcset="{{ $srcset }}" sizes="(max-width: 700px) 100vw, 400px" @endif alt="{{ $titulo }}" loading="lazy">
            @else
                <span class="arcade-ficha__hueco" aria-hidden="true">?</span>
            @endif
            @if ($insignia)
                <span class="arcade-ficha__insignia">{{ $insignia }}</span>
            @elseif ($href)
                <span class="arcade-ficha__accion">{{ $accion }}</span>
            @endif
        </span>
        <span class="arcade-ficha__titulo">{{ $titulo }}</span>
        @if ($detalle)
            <span class="arcade-ficha__detalle">{{ $detalle }}</span>
        @endif
    </{{ $etiqueta }}>
</li>
