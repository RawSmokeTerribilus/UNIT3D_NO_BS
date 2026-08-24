{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
{{--
    Audiobook metadata panel.

    $meta is an App\Models\Audiobook (may be null while the scrape job is
    queued), $asin the id carried by the torrent. Narrator and runtime are
    given top billing because they are what separates two recordings of the
    same book — a page without them misses the point of the category.

    Covers come from m.media-amazon.com, which was already on the art-proxy
    allowlist for OMDb, so they serve same-origin with no extra plumbing.
--}}
<section class="meta">
    <span class="meta__title-link">
        <h1 class="meta__title">
            {{ $meta->title ?? __('common.no-meta-found') }}
            @if ($meta?->release_date)
                ({{ $meta->release_date->format('Y') }})
            @endif
        </h1>
    </span>
    @if ($meta?->subtitle)
        <h2 class="meta__tagline">{{ $meta->subtitle }}</h2>
    @endif
    <span class="meta__poster-link">
        <img
            src="{{ $meta?->cover_url ? tmdb_image('poster_big', $meta->cover_url) : 'https://via.placeholder.com/400x600' }}"
            class="meta__poster"
            alt="{{ $meta->title ?? '' }}"
        />
    </span>
    <div class="meta__actions">
        <a class="meta__dropdown-button" href="#">
            <i class="{{ config('other.font-awesome') }} fa-ellipsis-v"></i>
        </a>
        <ul class="meta__dropdown">
            <li>
                <a
                    href="{{
                        route('torrents.create', [
                            'category_id' => $category->id,
                            'title' => rawurlencode($meta?->title ?? ''),
                            'asin' => $asin ?? '',
                        ])
                    }}"
                >
                    {{ __('common.upload') }}
                </a>
            </li>
            <li>
                <a
                    href="{{
                        route('requests.create', [
                            'category_id' => $category->id,
                            'title' => rawurlencode($meta?->title ?? ''),
                        ])
                    }}"
                >
                    Request similar
                </a>
            </li>
            {{-- El mismo partial sirve una ficha de torrent y una de peticion.
                Refrescar metadata solo tiene sentido en la primera: en una
                peticion, $torrent->id es de la tabla `requests` y la ruta
                apuntaria a otro torrent. --}}
            {{-- Igual que en book-meta: este parcial lo incluye también la vista
                 de similares, que encabeza un GRUPO y no tiene torrent. Una
                 variable indefinida revienta antes de que `instanceof` la
                 mire. --}}
            @php($torrentActual = $torrent ?? null)
            @if ($asin && $torrentActual instanceof \App\Models\Torrent && (auth()->user()->group->is_modo || (auth()->id() === $torrentActual->user_id && $torrentActual->created_at?->gt(now()->subDay()))))
                <li>
                    <form action="{{ route('torrents.refresh_meta', ['id' => $torrentActual->id]) }}" method="post">
                        @csrf

                        <button
                            @if (cache()->has('audiobook-scraper:' . $asin))
                                disabled
                                title="Esta ficha se actualizó hace poco. Vuelve a intentarlo más tarde."
                            @endif
                            style="cursor: pointer"
                            title="Volver a pedir la ficha a Audnexus"
                        >
                            Refrescar metadata
                        </button>
                    </form>
                </li>
            @endif
        </ul>
    </div>
    <ul class="meta__ids">
        @if ($asin)
            <li class="meta__audible">
                <a
                    class="meta-id-tag"
                    href="https://www.audible.{{ $meta?->region === 'us' ? 'com' : ($meta?->region ?? 'es') }}/pd/{{ $asin }}"
                    title="Audible: {{ $asin }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Audible
                </a>
            </li>
        @endif
    </ul>
    <x-meta.translated-description
        :texto="$meta?->description ?? ''"
        :original="$meta?->description_original"
        :idioma="$meta?->description_source_language"
    />
    <div class="meta__chips">
        <section class="meta__chip-container">
            <h2 class="meta__heading">Esta grabación</h2>
            @foreach ($meta?->narrators ?? [] as $narrator)
                <article class="meta-chip-wrapper meta-chip">
                    <i
                        class="{{ config('other.font-awesome') }} fa-microphone-lines meta-chip__icon"
                    ></i>
                    <h2 class="meta-chip__name">Narración</h2>
                    <h3 class="meta-chip__value">{{ $narrator }}</h3>
                </article>
            @endforeach
            @if ($meta?->runtimeForHumans())
                <article class="meta-chip-wrapper meta-chip">
                    <i class="{{ config('other.font-awesome') }} fa-clock meta-chip__icon"></i>
                    <h2 class="meta-chip__name">Duración</h2>
                    <h3 class="meta-chip__value">{{ $meta->runtimeForHumans() }}</h3>
                </article>
            @endif
            @if ($meta?->language)
                <article class="meta-chip-wrapper meta-chip">
                    <i class="{{ config('other.font-awesome') }} fa-language meta-chip__icon"></i>
                    <h2 class="meta-chip__name">Idioma</h2>
                    <h3 class="meta-chip__value">{{ ucfirst($meta->language) }}</h3>
                </article>
            @endif
        </section>
        <section class="meta__chip-container">
            <h2 class="meta__heading">Obra</h2>
            @foreach ($meta?->authors ?? [] as $author)
                <article class="meta-chip-wrapper meta-chip">
                    <i class="{{ config('other.font-awesome') }} fa-user-pen meta-chip__icon"></i>
                    <h2 class="meta-chip__name">Autor</h2>
                    <h3 class="meta-chip__value">{{ $author }}</h3>
                </article>
            @endforeach
            @if ($meta?->series)
                <article class="meta-chip-wrapper meta-chip">
                    <i class="{{ config('other.font-awesome') }} fa-layer-group meta-chip__icon"></i>
                    <h2 class="meta-chip__name">Serie</h2>
                    <h3 class="meta-chip__value">
                        {{ $meta->series }}@if ($meta->series_position)
                            , {{ $meta->series_position }}
                        @endif
                    </h3>
                </article>
            @endif
            @if ($meta?->publisher)
                <article class="meta-chip-wrapper meta-chip">
                    <i class="{{ config('other.font-awesome') }} fa-building meta-chip__icon"></i>
                    <h2 class="meta-chip__name">Editorial</h2>
                    <h3 class="meta-chip__value">{{ $meta->publisher }}</h3>
                </article>
            @endif
            @if ($meta?->isbn13)
                <article class="meta-chip-wrapper meta-chip">
                    <i class="{{ config('other.font-awesome') }} fa-barcode meta-chip__icon"></i>
                    <h2 class="meta-chip__name">ISBN-13</h2>
                    <h3 class="meta-chip__value">{{ $meta->isbn13 }}</h3>
                </article>
            @endif
        </section>
        @if ($meta?->genres)
            <section class="meta__chip-container">
                <h2 class="meta__heading">Géneros</h2>
                <article class="meta__genres meta-chip">
                    <i
                        class="{{ config('other.font-awesome') }} fa-theater-masks meta-chip__icon"
                    ></i>
                    <h2 class="meta-chip__name">Géneros</h2>
                    <h3 class="meta-chip__value">
                        {{ implode(' / ', array_slice($meta->genres, 0, 8)) }}
                    </h3>
                </article>
            </section>
        @endif
    </div>
</section>
