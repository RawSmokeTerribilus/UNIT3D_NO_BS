<section class="meta">
    @if (Storage::disk('torrent-banners')->exists("torrent-banner_$torrent->id.jpg"))
        <img
            class="meta__backdrop"
            src="{{ route('authenticated_images.torrent_banner', ['id' => $torrent->id]) }}"
            alt=""
        />
    @endif

    <span class="meta__poster-link">
        <img
            src="{{ Storage::disk('torrent-covers')->exists("torrent-cover_$torrent->id.jpg") ? route('authenticated_images.torrent_cover', ['id' => $torrent->id]) : url('img/sin-imagen.svg') }}"
            class="meta__poster"
        />
    </span>
    <div class="meta__actions">
        <a class="meta__dropdown-button" href="#">
            <i class="{{ config('other.font-awesome') }} fa-ellipsis-v"></i>
        </a>
        <ul class="meta__dropdown">
            <li>
                <a
                    href="{{ route('torrents.create', ['category_id' => $category->id, 'title' => rawurlencode($meta->title ?? '') ?? 'Unknown', 'imdb' => $torrent?->imdb ?? '', 'tmdb' => $meta?->id ?? '']) }}"
                >
                    {{ __('common.upload') }}
                </a>
            </li>
            <li>
                <a
                    href="{{ route('requests.create', ['title' => rawurlencode($meta?->title ?? '') ?? 'Unknown', 'imdb' => $torrent?->imdb ?? '', 'tmdb' => $meta?->id ?? '']) }}"
                >
                    Request similar
                </a>
            </li>
            {{-- Cualquier id que `resolveAndScrapeMeta` sepa usar vale para
                 ofrecer el botón, no sólo IMDb y MAL. Un libro o un audiolibro
                 cuya ficha no se pobló --la API no contestó, la cuota se agotó--
                 cae en este parcial, que es justo cuando más falta hace poder
                 reintentar, y el botón no estaba. --}}
            @php($tieneAlgunId = ($torrent?->imdb ?? 0) > 0
                || ($torrent?->mal ?? 0) > 0
                || ($torrent?->igdb ?? 0) > 0
                || ($torrent?->tmdb_movie_id ?? 0) > 0
                || ($torrent?->tmdb_tv_id ?? 0) > 0
                || $torrent?->isbn13 !== null
                || $torrent?->asin !== null)
            @if ($tieneAlgunId && (auth()->user()->group->is_modo || (auth()->id() === $torrent?->user_id && $torrent?->created_at?->gt(now()->subDay()))))
                <li>
                    <form action="{{ route('torrents.refresh_meta', ['id' => $torrent->id]) }}" method="post">
                        @csrf
                        <button style="cursor: pointer" title="Volver a pedir la ficha con los identificadores del torrent">
                            Refrescar metadata
                        </button>
                    </form>
                </li>
            @endif
        </ul>
    </div>
    <ul class="meta__ids">
        @if (isset($torrent) && $torrent->imdb > 0)
            <li class="meta__imdb">
                <a
                    class="meta-id-tag"
                    href="https://www.imdb.com/title/tt{{ \str_pad((int) $torrent->imdb, 7, '0', STR_PAD_LEFT) }}"
                    title="Internet Movie Database"
                    target="_blank"
                >
                    IMDB:
                    {{ \str_pad((string) $torrent->imdb, 7, '0', STR_PAD_LEFT) }}
                </a>
            </li>
        @endif

        @if (isset($torrent) && $torrent->tmdb_movie_id > 0)
            <li class="meta__tmdb">
                <a
                    class="meta-id-tag"
                    href="https://www.themoviedb.org/movie/{{ $torrent->tmdb_movie_id }}"
                    title="The Movie Database"
                    target="_blank"
                >
                    TMDB: {{ $torrent->tmdb_movie_id }}
                </a>
            </li>
        @endif

        @if (isset($torrent) && $torrent->mal > 0)
            <li class="meta__mal">
                <a
                    class="meta-id-tag"
                    href="https://myanimelist.net/anime/{{ $torrent->mal }}"
                    title="MyAnimeList"
                    target="_blank"
                >
                    MAL: {{ $torrent->mal }}
                </a>
            </li>
        @endif

        @if (isset($torrent) && $torrent->tvdb > 0)
            <li class="meta__tvdb">
                <a
                    class="meta-id-tag"
                    href="https://www.thetvdb.com/?tab=series&id={{ $torrent->tvdb }}"
                    title="MyAnimeList"
                    target="_blank"
                >
                    TVDB: {{ $torrent->tvdb }}
                </a>
            </li>
        @endif
    </ul>
    <div class="meta__chips">
        <section class="meta__chip-container">
            @if (isset($torrent->keywords) && $torrent->keywords->isNotEmpty())
                <article class="meta__keywords">
                    <a
                        class="meta-chip"
                        href="{{ route('torrents.index', ['view' => 'group', 'keywords' => $torrent->keywords->pluck('name')->join(', ')]) }}"
                    >
                        <i class="{{ config('other.font-awesome') }} fa-tag meta-chip__icon"></i>
                        <h2 class="meta-chip__name">Keywords</h2>
                        <h3 class="meta-chip__value">
                            {{ $torrent->keywords->pluck('name')->join(', ') }}
                        </h3>
                    </a>
                </article>
            @endif
        </section>
    </div>

    {{-- Por qué esta ficha está vacía. El scrape ocurre en un job, así que su
         fallo no puede contestar a la subida: el job lo deja escrito al
         rendirse y aquí se lee. Va al pie y en letra pequeña a propósito: es
         una nota de diagnóstico, no el contenido de la ficha. --}}
    @php($avisoMeta = collect([
        $torrent?->isbn13 ? 'meta-error:book:'.$torrent->isbn13 : null,
        $torrent?->asin ? 'meta-error:audiobook:'.$torrent->asin : null,
        ($torrent?->igdb ?? 0) > 0 ? 'meta-error:game:'.$torrent->igdb : null,
    ])->filter()->map(fn ($clave) => cache()->get($clave))->filter()->first())

    @if ($avisoMeta)
        <p
            class="meta__no-meta-warning"
            style="margin: 0.75rem 0 0; font-size: 0.7rem; line-height: 1.3; opacity: 0.6"
        >
            <i class="{{ config('other.font-awesome') }} fa-triangle-exclamation"></i>
            No se pudo traer la ficha: {{ $avisoMeta['motivo'] }}
            ({{ $avisoMeta['cuando'] }})
        </p>
    @endif
</section>
