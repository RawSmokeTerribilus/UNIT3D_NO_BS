{{--
    NOBS — Arcade: una puerta (imagen de la serie «press to play»).
    $puerta:  multijugador | consolas | aventuras  (public/img/arcade/puerta-<puerta>-{1600,2400}.webp)
    $nombre:  texto alternativo (la imagen ya dice qué hay detrás)
    $href:    destino; null = puerta cerrada
    $cabecera: true = cabecera de sección (sin enlace, más baja)
--}}
@php
    $base = '/img/arcade/puerta-'.$puerta;
    $v = filemtime(public_path('img/arcade/puerta-'.$puerta.'-1600.webp'));
    $cabecera ??= false;
@endphp
@if ($cabecera)
    <div class="arcade-puerta arcade-puerta--{{ $puerta }} arcade-cabecera">
        <img src="{{ $base }}-1600.webp?v={{ $v }}" srcset="{{ $base }}-1600.webp?v={{ $v }} 1600w, {{ $base }}-2400.webp?v={{ $v }} 2400w" sizes="(max-width: 1400px) 100vw, 1400px" alt="{{ $nombre }}">
    </div>
@elseif ($href)
    <a class="arcade-puerta arcade-puerta--{{ $puerta }}" href="{{ $href }}" aria-label="{{ $nombre }} — entrar">
        <img src="{{ $base }}-1600.webp?v={{ $v }}" srcset="{{ $base }}-1600.webp?v={{ $v }} 1600w, {{ $base }}-2400.webp?v={{ $v }} 2400w" sizes="(max-width: 1400px) 100vw, 1400px" alt="{{ $nombre }}" loading="lazy">
        <span class="arcade-puerta__entrar">Entrar ›</span>
    </a>
@else
    <div class="arcade-puerta arcade-puerta--{{ $puerta }} arcade-puerta--cerrada" aria-label="{{ $nombre }} — cerrada">
        <img src="{{ $base }}-1600.webp?v={{ $v }}" srcset="{{ $base }}-1600.webp?v={{ $v }} 1600w, {{ $base }}-2400.webp?v={{ $v }} 2400w" sizes="(max-width: 1400px) 100vw, 1400px" alt="{{ $nombre }}" loading="lazy">
        <span class="arcade-puerta__entrar">Cerrada</span>
    </div>
@endif
