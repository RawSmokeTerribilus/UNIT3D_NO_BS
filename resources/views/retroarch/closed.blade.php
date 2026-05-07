@extends('layout.with-main')

@section('title')
    <title>{{ $meta['label'] }} — Cerrado — {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li class="breadcrumb">
        <a href="{{ route('retroarch.index') }}" class="breadcrumb__link">RetroArch</a>
    </li>
    <li class="breadcrumb--active">{{ $meta['label'] }}</li>
@endsection

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-lock"></i>
            {{ $meta['label'] }} — Cerrado por mantenimiento
        </h2>
        <div class="panel__body">
            <div class="ra-closed">
                @if (! empty($meta['icon']))
                    <img src="{{ $meta['icon'] }}" alt="{{ $meta['label'] }}" class="ra-closed__icon" onerror="this.style.display='none'" />
                @endif
                <div class="ra-closed__copy">
                    <p class="ra-closed__lead">Esta sección está cerrada temporalmente.</p>
                    @if ($reason)
                        <p class="ra-closed__reason">{{ $reason }}</p>
                    @endif
                    <p class="ra-closed__hint">
                        Los {{ $meta['rom_count'] }} juegos de {{ $meta['label'] }} están en disco pero el core actual no los reconoce.
                        Volveremos a abrirlo cuando el set de ROMs y el core encajen.
                    </p>
                </div>
            </div>
            <div class="ra-show__actions">
                <a href="{{ route('retroarch.index') }}" class="btn">
                    <i class="{{ config('other.font-awesome') }} fa-arrow-left"></i> Volver al catálogo
                </a>
            </div>
        </div>
    </section>

    <style>
        .ra-closed { display: flex; gap: 24px; align-items: center; padding: 24px; background: var(--panel_inner_background); border: 1px dashed var(--panel_border); border-radius: 8px; margin-bottom: 16px; }
        .ra-closed__icon { width: 96px; height: 96px; object-fit: contain; opacity: .5; flex-shrink: 0; }
        .ra-closed__copy { flex: 1; }
        .ra-closed__lead { font-size: 16px; font-weight: 600; margin: 0 0 8px 0; }
        .ra-closed__reason { margin: 0 0 8px 0; opacity: .85; }
        .ra-closed__hint { margin: 0; opacity: .7; font-size: 13px; }
        .ra-show__actions { display: flex; gap: 8px; }
    </style>
@endsection
