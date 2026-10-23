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
    <title>{{ $meta['label'] }} — RetroArch — {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Catálogo de juegos {{ $meta['label'] }} jugables en el navegador." />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb">
        <a href="{{ route('gaming.index') }}" class="breadcrumb__link">Arcade</a>
    </li>
    <li class="breadcrumb">
        <a href="{{ route('retroarch.index') }}" class="breadcrumb__link">Consolas</a>
    </li>
    <li class="breadcrumb--active">{{ $meta['label'] }}</li>
@endsection

@section('page', 'page__retroarch_system')

@section('main')
    @include('gaming.partials.arcade-estilos')
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-gamepad"></i>
            {{ $meta['label'] }}
            <span class="ra-badge">{{ number_format($meta['rom_count'], 0, ',', '.') }} juegos</span>
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
                <ul class="arcade-fichas arcade-fichas--caja arcade-sala-consolas" id="ra-grid">
                    @include('retroarch.partials.rom-items', ['roms' => $roms->items(), 'system' => $system])
                </ul>

                <div id="ra-sentinel" class="ra-sentinel" aria-hidden="true"></div>
                <div id="ra-loader" class="ra-loader" style="display:none">
                    <i class="{{ config('other.font-awesome') }} fa-spinner fa-spin"></i>
                </div>
            @endif
        </div>
    </section>

    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        (function () {
            const grid   = document.getElementById('ra-grid');
            const loader = document.getElementById('ra-loader');

            console.log('[RA] script reached. grid=' + (grid ? 'FOUND' : 'NULL'));
            if (!grid) return;

            let nextPage = 2;
            let loading  = false;
            let hasMore  = {{ $hasMore ? 'true' : 'false' }};
            const q      = {{ Js::from($q) }};
            const base   = {{ Js::from(route('retroarch.system', ['system' => $system])) }};

            function buildUrl(page) {
                const u = new URL(base);
                u.searchParams.set('page', page);
                if (q) u.searchParams.set('q', q);
                return u.toString();
            }

            async function loadMore() {
                if (loading || !hasMore) return;
                loading = true;
                if (loader) loader.style.display = 'flex';

                try {
                    const res  = await fetch(buildUrl(nextPage), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();

                    grid.insertAdjacentHTML('beforeend', data.html);
                    hasMore  = data.has_more;
                    nextPage = data.next_page;
                } catch (e) {
                    console.error('RetroArch scroll load failed:', e);
                    hasMore = false;
                } finally {
                    loading = false;
                    if (loader) loader.style.display = 'none';
                }
            }

            console.log('[RA] hasMore=' + hasMore + ' nextPage=' + nextPage
                + ' scrollH=' + document.documentElement.scrollHeight
                + ' innerH=' + window.innerHeight);

            function checkScroll() {
                if (loading || !hasMore) return;
                const distanceFromBottom = document.documentElement.scrollHeight
                    - window.scrollY - window.innerHeight;
                if (distanceFromBottom < 400) loadMore();
            }

            window.addEventListener('scroll', checkScroll, { passive: true });
            // Also check on load in case first page doesn't fill the viewport.
            checkScroll();

            // Hide broken cover images — handles both initial and lazy-loaded cards.
            // Cannot use inline onerror= due to CSP nonce policy.
            document.addEventListener('error', function (e) {
                if (e.target.tagName === 'IMG' && e.target.closest('.arcade-ficha__imagen')) {
                    e.target.style.display = 'none';
                }
            }, true);
        })();
    </script>

    <style>
        /* Allow the panel to grow as items are dynamically appended.
           The theme sets overflow:hidden on .panelV2 which freezes the height
           in flex layout, clipping anything appended after first paint. */
        .page__retroarch_system .panelV2 { overflow: visible !important; }
        .ra-search { display: flex; gap: 8px; align-items: center; margin-bottom: 16px; }
        .ra-search__input { flex: 1; max-width: 360px; padding: 8px 12px; background: var(--panel_inner_background); border: 1px solid var(--panel_border); border-radius: 4px; color: var(--body_text); }
        .ra-search__clear { font-size: 12px; opacity: .7; }
        .ra-empty { padding: 24px; text-align: center; opacity: .8; border: 1px dashed var(--panel_border); border-radius: 6px; }
        .ra-sentinel { height: 1px; }
        .ra-loader { justify-content: center; padding: 24px; font-size: 24px; opacity: .5; }
        .ra-badge { background: var(--panel_background); border: 1px solid var(--panel_border); border-radius: 3px; padding: 2px 8px; font-size: 12px; }
        .ra-badge--core { color: var(--primary); border-color: var(--primary); font-family: monospace; }
    </style>
@endsection
