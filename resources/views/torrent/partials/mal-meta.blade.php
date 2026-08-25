{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
<section class="meta">
    <a
        class="meta__title-link"
        href="https://myanimelist.net/anime/{{ $mal->id }}"
        target="_blank"
        rel="noreferrer"
    >
        <h1 class="meta__title">
            {{ $mal->title_english ?? $mal->title }}
            @if ($mal->start_date)
                ({{ $mal->start_date->year }})
            @endif
        </h1>
    </a>

    <a
        class="meta__poster-link"
        href="https://myanimelist.net/anime/{{ $mal->id }}"
        target="_blank"
        rel="noreferrer"
    >
        <img
            src="{{ $mal->poster ?? url('img/sin-imagen.svg') }}"
            class="meta__poster"
        />
    </a>

    <div class="meta__actions">
        <a class="meta__dropdown-button" href="#">
            <i class="{{ config('other.font-awesome') }} fa-ellipsis-v"></i>
        </a>
        <ul class="meta__dropdown">
            <li>
                <a href="{{ route('torrents.create', ['category_id' => $category->id, 'mal' => $mal->id]) }}">
                    {{ __('common.upload') }}
                </a>
            </li>
            <li>
                <form action="{{ route('torrents.mal.update', ['torrent' => $torrent]) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <button
                        @if (cache()->has('mal-anime-scraper:' . $mal->id))
                            disabled
                            title="Recently updated. Try again in a few hours."
                        @endif
                        style="cursor: pointer"
                    >
                        Update MAL metadata
                    </button>
                </form>
            </li>
        </ul>
    </div>

    <ul class="work__tags">
        <li class="work__media-type">
            <a class="work__media-type-link" href="{{ route('torrents.index', ['categoryIds' => [$category->id]]) }}">
                {{ $category->name }}
            </a>
        </li>
        @if ($mal->media_type)
            <li class="work__language">
                <span class="work__language-link">{{ strtoupper(str_replace('_', ' ', $mal->media_type)) }}</span>
            </li>
        @endif
        @if ($mal->num_episodes)
            <li class="work__runtime">
                <span class="work__runtime-text">{{ $mal->num_episodes }} eps</span>
            </li>
        @endif
        @if ($mal->mean)
            <li class="work__rating">
                <span class="work__rating-text" title="MAL community score">
                    {{ round($mal->mean * 10) }}%
                </span>
            </li>
        @endif
    </ul>

    <ul class="meta__ids">
        <li class="meta__mal">
            <a
                class="meta-id-tag"
                href="https://myanimelist.net/anime/{{ $mal->id }}"
                title="My Anime List: {{ $mal->id }}"
                target="_blank"
            >
                <img src="{{ url('/img/meta/mal.svg') }}" />
            </a>
        </li>
        @if ($torrent->imdb)
            <li class="meta__imdb">
                <a
                    class="meta-id-tag"
                    href="https://www.imdb.com/title/tt{{ str_pad($torrent->imdb, max(strlen($torrent->imdb), 7), '0', STR_PAD_LEFT) }}"
                    title="IMDB: {{ $torrent->imdb }}"
                    target="_blank"
                >
                    <img src="{{ url('/img/meta/imdb.svg') }}" />
                </a>
            </li>
        @endif
    </ul>

    <p class="meta__description">{{ $mal->synopsis }}</p>

    @if ($mal->title !== ($mal->title_english ?? $mal->title) || $mal->title_japanese)
        <p style="color: #a5a5a5; font-size: 0.85em; margin-top: 0.5rem;">
            @if ($mal->title)
                <strong>Romaji:</strong> {{ $mal->title }}
            @endif
            @if ($mal->title_japanese)
                &nbsp;|&nbsp;<strong>Japanese:</strong> {{ $mal->title_japanese }}
            @endif
        </p>
    @endif

    <div class="meta__chips">
        @if (!empty($mal->genres))
            <section class="meta__chip-container">
                <h2 class="meta__heading">Extra information</h2>
                <article class="meta__genres">
                    <a class="meta-chip" href="#">
                        <i class="{{ config('other.font-awesome') }} fa-theater-masks meta-chip__icon"></i>
                        <h2 class="meta-chip__name">Genres</h2>
                        <h3 class="meta-chip__value">
                            {{ collect($mal->genres)->pluck('name')->join(' / ') }}
                        </h3>
                    </a>
                </article>
                @if ($mal->status)
                    <article class="meta__genres">
                        <a class="meta-chip" href="#">
                            <i class="{{ config('other.font-awesome') }} fa-broadcast-tower meta-chip__icon"></i>
                            <h2 class="meta-chip__name">Status</h2>
                            <h3 class="meta-chip__value">
                                {{ ucwords(str_replace('_', ' ', $mal->status)) }}
                            </h3>
                        </a>
                    </article>
                @endif
                @if ($mal->rank)
                    <article class="meta__genres">
                        <a class="meta-chip" href="#">
                            <i class="{{ config('other.font-awesome') }} fa-trophy meta-chip__icon"></i>
                            <h2 class="meta-chip__name">MAL Rank</h2>
                            <h3 class="meta-chip__value">#{{ number_format($mal->rank) }}</h3>
                        </a>
                    </article>
                @endif
                @if ($torrent->keywords?->isNotEmpty())
                    <article class="meta__keywords">
                        <a
                            class="meta-chip"
                            href="{{ route('torrents.index', ['keywords' => $torrent->keywords->pluck('name')->join(', ')]) }}"
                        >
                            <i class="{{ config('other.font-awesome') }} fa-tag meta-chip__icon"></i>
                            <h2 class="meta-chip__name">Keywords</h2>
                            <h3 class="meta-chip__value">{{ $torrent->keywords->pluck('name')->join(', ') }}</h3>
                        </a>
                    </article>
                @endif
            </section>
        @endif
    </div>
</section>
