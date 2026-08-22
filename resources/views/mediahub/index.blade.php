@extends('layout.with-main-and-sidebar')

@section('title')
    <title>{{ __('mediahub.title') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="MediaHub" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb--active">
        {{ __('mediahub.title') }}
    </li>
@endsection

@section('page', 'page__mediahub')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('mediahub.title') }}</h2>
        <div class="panel__body">
            <ul class="mediahub-card__list">
                <li class="mediahub-card__list-item">
                    <a
                        href="{{ route('torrents.index', ['view' => 'group', 'categoryIds' => $tvCategoryIds]) }}"
                        class="mediahub-card"
                    >
                        <h2 class="mediahub-card__heading">{{ __('mediahub.shows') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $tv }} {{ __('mediahub.shows') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a
                        href="{{ route('torrents.index', ['view' => 'group', 'categoryIds' => $movieCategoryIds]) }}"
                        class="mediahub-card"
                    >
                        <h2 class="mediahub-card__heading">{{ __('mediahub.movies') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $movies }} {{ __('mediahub.movies') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.collections.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">
                            {{ __('mediahub.collections') }} hub
                        </h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $collections }} {{ __('mediahub.collections') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.persons.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.persons') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $persons }} {{ __('mediahub.persons') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.genres.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.genres') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $genres }} {{ __('mediahub.genres') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.networks.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.networks') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $networks }} {{ __('mediahub.networks') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.companies.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.companies') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $companies }} {{ __('mediahub.companies') }}
                        </h3>
                    </a>
                </li>
            </ul>
        </div>
    </section>

    {{-- El hub de arriba es enteramente de TMDB, asi que los libros no tenian
         donde aparecer: no habia un gate que abrir, no habia nada que enseñar.
         Panel propio porque el catalogo es otro y el disclaimer de TMDB de la
         barra lateral no le aplica. --}}
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('mediahub.books') }} &amp; {{ __('mediahub.audiobooks') }}</h2>
        <div class="panel__body">
            <ul class="mediahub-card__list">
                <li class="mediahub-card__list-item">
                    <a href="{{ route('torrents.index', ['view' => 'list', 'categoryIds' => $bookCategoryIds]) }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.books') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $books }} {{ __('mediahub.books') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('torrents.index', ['view' => 'list', 'categoryIds' => $audiobookCategoryIds]) }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.audiobooks') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $audiobooks }} {{ __('mediahub.audiobooks') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.authors.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.authors') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $authors }} {{ __('mediahub.authors') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.narrators.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.narrators') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $narrators }} {{ __('mediahub.narrators') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.book-series.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.book-series') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $bookSeries }} {{ __('mediahub.book-series') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.book-genres.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.book-genres') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $bookGenres }} {{ __('mediahub.book-genres') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.publishers.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.publishers') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $publishers }} {{ __('mediahub.publishers') }}
                        </h3>
                    </a>
                </li>
            </ul>
        </div>
    </section>

    {{-- Los juegos tenian el mismo agujero que los libros: esquema completo en
         la base desde 2025 y ninguna vista que lo enseñara. Aqui no hizo falta
         migrar nada, solo pintar lo que ya estaba. --}}
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('mediahub.games') }}</h2>
        <div class="panel__body">
            <ul class="mediahub-card__list">
                <li class="mediahub-card__list-item">
                    <a href="{{ route('torrents.index', ['view' => 'list', 'categoryIds' => $gameCategoryIds]) }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.games') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $games }} {{ __('mediahub.games') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.platforms.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.platforms') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $platforms }} {{ __('mediahub.platforms') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.game-genres.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.game-genres') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $gameGenres }} {{ __('mediahub.game-genres') }}
                        </h3>
                    </a>
                </li>
                <li class="mediahub-card__list-item">
                    <a href="{{ route('mediahub.game-companies.index') }}" class="mediahub-card">
                        <h2 class="mediahub-card__heading">{{ __('mediahub.game-companies') }} hub</h2>
                        <h3 class="mediahub-card__subheading">
                            {{ $gameCompanies }} {{ __('mediahub.game-companies') }}
                        </h3>
                    </a>
                </li>
            </ul>
        </div>
    </section>
@endsection

@section('sidebar')
    {{-- El disclaimer original sólo nombraba a TMDB porque el hub sólo sabía de
         cine. Con libros, audiolibros y juegos dentro son diez proveedores, y
         cada uno pide su atribución. Se listan por categoría para que se vea
         de dónde sale cada ficha. --}}
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('mediahub.providers-heading') }}</h2>
        <div class="panel__body">
            <dl class="key-value">
                <div class="key-value__group">
                    <dt>{{ __('mediahub.providers-movies-tv') }}</dt>
                    <dd>TMDB · IMDb · TVmaze</dd>
                </div>
                <div class="key-value__group">
                    <dt>{{ __('mediahub.providers-anime') }}</dt>
                    <dd>MyAnimeList · AniList</dd>
                </div>
                <div class="key-value__group">
                    <dt>{{ __('mediahub.providers-games') }}</dt>
                    <dd>IGDB</dd>
                </div>
                <div class="key-value__group">
                    <dt>{{ __('mediahub.providers-books') }}</dt>
                    <dd>Google Books · OpenLibrary</dd>
                </div>
                <div class="key-value__group">
                    <dt>{{ __('mediahub.providers-audiobooks') }}</dt>
                    <dd>Audible · Audnexus</dd>
                </div>
            </dl>

            <p style="margin-top: 1rem; font-size: 13px">
                {{ __('mediahub.disclaimer') }}
            </p>

            <div style="text-align: center; margin-top: 1rem">
                <img src="{{ url('/img/tmdb_long.svg') }}" alt="TMDB" style="width: 200px" />
            </div>
        </div>
    </section>
@endsection
