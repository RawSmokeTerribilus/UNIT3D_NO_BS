{{--
    Ficha de e-book.

    $meta es un App\Models\Book (puede ser null mientras el job de scrape
    sigue en cola) y $isbn13 el id que lleva el torrent.

    La estructura calca la de movie-meta a propósito: mismo poster a la
    izquierda, misma línea de datos, misma fila de logos de proveedor y las
    mismas columnas de chips. Un libro no tiene mediainfo ni capturas, así que
    la ficha ES sus metadatos: si esto se ve pobre, la página se ve pobre.

    Todo lo que se pinta aquí sale de la base. No se consulta a ningún
    proveedor en tiempo de render.
--}}
<section class="meta">
    {{-- Sin enlace de "similares": esa ruta va por id de TMDB y un libro no
         tiene. Agrupar ediciones por ISBN es trabajo aparte. --}}
    <span class="meta__title-link">
        <h1 class="meta__title">
            {{ $meta->title ?? __('common.no-meta-found') }}
            @if ($meta?->first_publish_year)
                ({{ $meta->first_publish_year }})
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
                            'isbn13' => $isbn13 ?? '',
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
                            'isbn13' => $isbn13 ?? '',
                        ])
                    }}"
                >
                    Request similar
                </a>
            </li>
            {{-- El mismo partial sirve una ficha de torrent y una de petición.
                 Refrescar metadata sólo tiene sentido en la primera: en una
                 petición, $torrent->id es de la tabla `requests` y la ruta
                 apuntaría a otro torrent. --}}
            @if ($isbn13 && $torrent instanceof \App\Models\Torrent && (auth()->user()->group->is_modo || (auth()->id() === $torrent?->user_id && $torrent?->created_at?->gt(now()->subDay()))))
                <li>
                    <form action="{{ route('torrents.refresh_meta', ['id' => $torrent->id]) }}" method="post">
                        @csrf

                        <button
                            @if (cache()->has('book-scraper:' . $isbn13))
                                disabled
                                title="Esta ficha se actualizó hace poco. Vuelve a intentarlo más tarde."
                            @endif
                            style="cursor: pointer"
                            title="Volver a pedir la ficha a Google Books y OpenLibrary"
                        >
                            Refrescar metadata
                        </button>
                    </form>
                </li>
            @endif
        </ul>
    </div>

    {{-- Línea de datos, el equivalente a "Película • en • 2h 13m • 70%" --}}
    <ul class="work__tags">
        <li class="work__type">{{ $category->name }}</li>
        @if ($meta?->languages)
            <li class="work__language">{{ strtoupper(implode(', ', $meta->languages)) }}</li>
        @endif
        @if ($meta?->page_count)
            <li class="work__runtime">
                <span class="work__runtime-text">{{ $meta->page_count }} {{ __('torrent.pages') }}</span>
            </li>
        @endif
        @if ($meta?->ratingPercent() !== null)
            <li class="work__rating">
                <span class="work__rating-text" title="{{ $meta->ratings_count }} {{ __('torrent.votes') }}">
                    {{ $meta->ratingPercent() }}%
                </span>
            </li>
        @endif
        @if ($meta?->preview_link)
            <li class="work__trailer">
                <a class="work__trailer-link" href="{{ $meta->preview_link }}" target="_blank" rel="noopener noreferrer">
                    {{ __('torrent.preview') }}
                </a>
            </li>
        @endif
    </ul>

    {{-- Logos de proveedor, misma fila y mismo componente que TMDB/IMDb --}}
    <ul class="meta__ids">
        @if ($meta?->google_volume_id)
            <li class="meta__google-books">
                <a
                    class="meta-id-tag"
                    href="https://books.google.com/books?id={{ $meta->google_volume_id }}"
                    title="Google Books: {{ $meta->isbn13 }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <img src="{{ url('/img/meta/google-books.svg') }}" alt="Google Books" />
                </a>
            </li>
        @endif
        @if ($meta?->olid)
            <li class="meta__openlibrary">
                <a
                    class="meta-id-tag"
                    href="https://openlibrary.org/works/{{ $meta->olid }}"
                    title="OpenLibrary: {{ $meta->olid }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <img src="{{ url('/img/meta/openlibrary.svg') }}" alt="OpenLibrary" />
                </a>
            </li>
        @endif
    </ul>

    <p class="meta__description">
        {{-- El aviso va DELANTE del texto a proposito: .meta__description
             tiene max-height 150px con scroll, asi que al final quedaba fuera
             de la parte visible y habia que bajar con la rueda para verlo.
             Un aviso que no se ve no avisa. --}}
        @if ($meta?->description_source_language)
            <small
                title="{{ $meta->description_original }}"
            >{{ __('torrent.auto-translated', ['idioma' => strtoupper($meta->description_source_language)]) }}</small>
            <br />
        @endif

        {{ $meta?->description ?? '' }}
    </p>

    <div class="meta__chips">
        <section class="meta__chip-container" title="{{ __('torrent.authorship') }}">
            <h2 class="meta__heading">{{ __('torrent.authorship') }}</h2>

            {{-- Con ficha resuelta se pinta foto y fechas; sin ella se cae al
                 nombre suelto que da Google Books, que siempre está. --}}
            @forelse ($meta?->bookAuthors ?? [] as $author)
                <article class="meta-chip-wrapper">
                    <a class="meta-chip" href="{{ $author->openLibraryUrl() }}" target="_blank" rel="noopener noreferrer">
                        @if ($author->photo_url)
                            <img
                                class="meta-chip__image"
                                src="{{ tmdb_image('cast_face', $author->photo_url) }}"
                                alt=""
                                loading="lazy"
                            />
                        @else
                            <i class="{{ config('other.font-awesome') }} fa-user-pen meta-chip__icon"></i>
                        @endif
                        <h2 class="meta-chip__name">{{ $author->name }}</h2>
                        <h3 class="meta-chip__value">
                            {{ $author->birth_date ?? __('torrent.author') }}
                        </h3>
                    </a>
                </article>
            @empty
                @foreach ($meta?->authors ?? [] as $author)
                    <article class="meta-chip-wrapper">
                        <span class="meta-chip">
                            <i class="{{ config('other.font-awesome') }} fa-user-pen meta-chip__icon"></i>
                            <h2 class="meta-chip__name">{{ $author }}</h2>
                            <h3 class="meta-chip__value">{{ __('torrent.author') }}</h3>
                        </span>
                    </article>
                @endforeach
            @endforelse
        </section>

        <section class="meta__chip-container" title="{{ __('torrent.edition') }}">
            <h2 class="meta__heading">{{ __('torrent.edition') }}</h2>

            <article class="meta-chip-wrapper">
                <span class="meta-chip">
                    <i class="{{ config('other.font-awesome') }} fa-barcode meta-chip__icon"></i>
                    <h2 class="meta-chip__name">{{ $meta?->isbn13 ?? $isbn13 }}</h2>
                    <h3 class="meta-chip__value">ISBN-13</h3>
                </span>
            </article>

            @if ($meta?->publisher)
                <article class="meta-chip-wrapper">
                    <span class="meta-chip">
                        <i class="{{ config('other.font-awesome') }} fa-building meta-chip__icon"></i>
                        <h2 class="meta-chip__name">{{ $meta->publisher }}</h2>
                        <h3 class="meta-chip__value">{{ __('torrent.publisher') }}</h3>
                    </span>
                </article>
            @endif

            @if ($meta?->series)
                <article class="meta-chip-wrapper">
                    <span class="meta-chip">
                        <i class="{{ config('other.font-awesome') }} fa-layer-group meta-chip__icon"></i>
                        <h2 class="meta-chip__name">{{ $meta->series }}</h2>
                        <h3 class="meta-chip__value">
                            {{ $meta->series_position ? __('torrent.book-number', ['n' => $meta->series_position]) : __('torrent.series') }}
                        </h3>
                    </span>
                </article>
            @endif
        </section>

        <section class="meta__chip-container" title="Información adicional">
            <h2 class="meta__heading">Información adicional</h2>

            @if ($meta?->genres?->isNotEmpty())
                <article class="meta__genres meta-chip">
                    <i class="{{ config('other.font-awesome') }} fa-theater-masks meta-chip__icon"></i>
                    <h2 class="meta-chip__name">{{ __('torrent.genres') }}</h2>
                    <h3 class="meta-chip__value">{{ $meta->genres->pluck('name')->join(' / ') }}</h3>
                </article>
            @endif

            @if ($meta?->subjects)
                <article class="meta__genres meta-chip">
                    <i class="{{ config('other.font-awesome') }} fa-tags meta-chip__icon"></i>
                    <h2 class="meta-chip__name">{{ __('torrent.subjects') }}</h2>
                    <h3 class="meta-chip__value">{{ implode(' / ', array_slice($meta->subjects, 0, 8)) }}</h3>
                </article>
            @endif
        </section>
    </div>
</section>
