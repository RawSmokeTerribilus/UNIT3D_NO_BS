@extends('layout.with-main')

@section('title')
    <title>{{ $meta['label'] }} — RetroArch — {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Catálogo de juegos {{ $meta['label'] }} jugables en el navegador." />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb">
        <a href="{{ route('retroarch.index') }}" class="breadcrumb__link">RetroArch</a>
    </li>
    <li class="breadcrumb--active">{{ $meta['label'] }}</li>
@endsection

@section('page', 'page__retroarch_system')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-gamepad"></i>
            {{ $meta['label'] }}
            <span class="ra-badge ra-badge--core">{{ $meta['core'] }}</span>
            <span class="ra-badge">{{ $meta['rom_count'] }} juegos</span>
        </h2>
        <div class="panel__body">
            <form method="get" class="ra-search">
                <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por título…" class="ra-search__input" autocomplete="off" />
                <button type="submit" class="btn btn--filled">Buscar</button>
                @if ($q !== '')
                    <a href="{{ route('retroarch.system', ['system' => $system]) }}" class="ra-search__clear">limpiar</a>
                @endif
            </form>

            @if ($roms->total() === 0)
                <div class="ra-empty">No hay coincidencias.</div>
            @else
                <ul class="ra-rom__list">
                    @foreach ($roms as $rom)
                        <li class="ra-rom__item">
                            <a class="ra-rom__card" href="{{ route('retroarch.show', ['system' => $system, 'slug' => $rom['slug']]) }}">
                                <div class="ra-rom__cover">
                                    @if (! empty($rom['cover']))
                                        <img src="{{ $rom['cover'] }}" alt="Portada de {{ $rom['title'] }}" loading="lazy" onerror="this.style.display='none'" />
                                    @else
                                        <i class="{{ config('other.font-awesome') }} fa-circle-question ra-rom__cover-fallback"></i>
                                    @endif
                                </div>
                                <div class="ra-rom__info">
                                    <h3 class="ra-rom__title">{{ $rom['title'] }}</h3>
                                    <p class="ra-rom__meta">
                                        <span>{{ number_format($rom['size'] / 1024, 0) }} KB</span>
                                    </p>
                                    <span class="btn btn--filled ra-rom__play">
                                        <i class="{{ config('other.font-awesome') }} fa-play"></i> Jugar
                                    </span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="ra-pagination">
                    {{ $roms->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </section>

    <style>
        .ra-search { display: flex; gap: 8px; align-items: center; margin-bottom: 16px; }
        .ra-search__input { flex: 1; max-width: 360px; padding: 8px 12px; background: var(--panel_inner_background); border: 1px solid var(--panel_border); border-radius: 4px; color: var(--body_text); }
        .ra-search__clear { font-size: 12px; opacity: .7; }
        .ra-empty { padding: 24px; text-align: center; opacity: .8; border: 1px dashed var(--panel_border); border-radius: 6px; }
        .ra-rom__list { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; list-style: none; padding: 0; margin: 0 0 24px 0; }
        .ra-rom__card { display: flex; flex-direction: column; gap: 10px; padding: 12px; background: var(--panel_inner_background); border: 1px solid var(--panel_border); border-radius: 8px; text-decoration: none; color: var(--body_text); transition: border-color .2s, transform .15s; height: 100%; }
        .ra-rom__card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .ra-rom__cover { aspect-ratio: 4 / 3; border-radius: 4px; overflow: hidden; background: var(--panel_background); display: flex; align-items: center; justify-content: center; }
        .ra-rom__cover img { width: 100%; height: 100%; object-fit: cover; }
        .ra-rom__cover-fallback { font-size: 36px; opacity: .3; }
        .ra-rom__info { display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .ra-rom__title { font-size: 14px; font-weight: 600; margin: 0; line-height: 1.3; }
        .ra-rom__meta { display: flex; gap: 8px; font-size: 12px; opacity: .7; margin: 0; }
        .ra-rom__play { align-self: flex-start; margin-top: auto; font-size: 13px; padding: 6px 14px; pointer-events: none; }
        .ra-pagination { display: flex; justify-content: center; }
        .ra-badge { background: var(--panel_background); border: 1px solid var(--panel_border); border-radius: 3px; padding: 2px 8px; font-size: 12px; }
        .ra-badge--core { color: var(--primary); border-color: var(--primary); font-family: monospace; }
    </style>
@endsection
