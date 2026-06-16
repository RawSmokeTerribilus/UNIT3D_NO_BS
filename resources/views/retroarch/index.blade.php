@extends('layout.with-main')

@section('title')
    <title>Arcade RetroArch — {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Catálogo de juegos retro jugables en el navegador con RetroArch." />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb--active">RetroArch</li>
@endsection

@section('page', 'page__retroarch')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-gamepad"></i>
            Arcade RetroArch — Selecciona sistema
        </h2>
        <div class="panel__body">
            <p class="ra-intro">
                Juega ROMs clásicos directamente en el navegador. Elige un sistema para ver
                el catálogo. El motor RetroArch corre en tu máquina vía WebAssembly y los
                guardados van a tu cuenta.
            </p>

            @if (count($systems) === 0)
                <div class="ra-empty">
                    No hay catálogo. Ejecuta <code>php artisan retroarch:scan-roms</code> primero.
                </div>
            @else
                <ul class="ra-system__list">
                    @foreach ($systems as $sys)
                        <li class="ra-system__item">
                            <a class="ra-system__card {{ $sys['unavailable'] ? 'ra-system__card--closed' : '' }}"
                               href="{{ route('retroarch.system', ['system' => $sys['slug']]) }}">
                                @if (! empty($sys['icon']))
                                    <img src="{{ $sys['icon'] }}" alt="{{ $sys['label'] }}" class="ra-system__icon" loading="lazy" />
                                @endif
                                <div class="ra-system__info">
                                    <h3 class="ra-system__title">{{ $sys['label'] }}</h3>
                                    <p class="ra-system__meta">
                                        <span class="ra-badge">{{ $sys['rom_count'] }} juegos</span>
                                        @if ($sys['unavailable'])
                                            <span class="ra-badge ra-badge--closed">
                                                <i class="{{ config('other.font-awesome') }} fa-lock"></i> Cerrado
                                            </span>
                                        @else
                                            <span class="ra-badge ra-badge--core">{{ $sys['core'] }}</span>
                                        @endif
                                    </p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            <a class="ra-freeplay" href="/retroarch/index.html" target="_blank" rel="noopener noreferrer">
                <i class="{{ config('other.font-awesome') }} fa-upload"></i>
                Modo libre — sube tu propio ROM (3DO, PSX, DOS, C64, Vectrex, Doom, Cave Story…)
            </a>
        </div>
    </section>

    <style>
        .ra-intro { font-size: 14px; line-height: 1.7; margin-bottom: 24px; opacity: .9; }
        .ra-empty { padding: 24px; text-align: center; opacity: .8; border: 1px dashed var(--panel_border); border-radius: 6px; }
        .ra-system__list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; list-style: none; padding: 0; margin: 0 0 24px 0; }
        .ra-system__card { display: flex; gap: 14px; align-items: center; padding: 14px 16px; background: var(--panel_inner_background); border: 1px solid var(--panel_border); border-radius: 8px; text-decoration: none; color: var(--body_text); transition: border-color .2s, transform .15s; }
        .ra-system__card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .ra-system__icon { width: 56px; height: 56px; object-fit: contain; flex-shrink: 0; }
        .ra-system__info { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .ra-system__title { font-size: 16px; font-weight: 700; margin: 0; line-height: 1.3; }
        .ra-system__meta { display: flex; gap: 8px; flex-wrap: wrap; margin: 0; }
        .ra-badge { background: var(--panel_background); border: 1px solid var(--panel_border); border-radius: 3px; padding: 2px 8px; font-size: 12px; }
        .ra-badge--core { color: var(--primary); border-color: var(--primary); font-family: monospace; }
        .ra-badge--closed { color: #c66; border-color: #c66; }
        .ra-system__card--closed { opacity: .55; }
        .ra-system__card--closed:hover { transform: none; }
        .ra-freeplay { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: var(--panel_background); border: 1px solid var(--panel_border); border-radius: 6px; color: var(--body_text); text-decoration: none; font-size: 13px; opacity: .85; }
        .ra-freeplay:hover { border-color: var(--primary); opacity: 1; }
    </style>

    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        // Hide broken system icons. Inline onerror= is blocked by the gaming CSP
        // (script-src-attr); delegate in capture phase ('error' doesn't bubble).
        document.addEventListener('error', function (e) {
            if (e.target.tagName === 'IMG' && e.target.classList.contains('ra-system__icon')) {
                e.target.style.display = 'none';
            }
        }, true);
    </script>
@endsection
