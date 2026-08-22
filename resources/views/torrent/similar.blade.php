@extends('layout.with-main')

@section('title')
    <title>
        {{ __('common.similar') }} - {{ $meta->title ?? $meta->name }}
        ({{ substr($meta->release_date ?? $meta->first_air_date, 0, 4) }}) -
        {{ config('other.title') }}
    </title>
@endsection

@section('meta')
    <meta
        name="description"
        content="{{ __('common.similar') }} - {{ $meta->title ?? $meta->name }} ({{ substr($meta->release_date ?? $meta->first_air_date, 0, 4) }})"
    />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('torrents.index') }}" class="breadcrumb__link">
            {{ __('torrent.torrents') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('common.similar') }} - {{ $meta->title ?? $meta->name }}
        ({{ \substr($meta->release_date ?? $meta->first_air_date, 0, 4) }})
    </li>
@endsection

@section('page', 'page__torrent-similar--index')

@section('main')
    @switch(true)
        @case($category->movie_meta)
            @include('torrent.partials.movie-meta')

            @break
        @case($category->tv_meta)
            @include('torrent.partials.tv-meta')

            @break
        @case($category->game_meta)
            @include('torrent.partials.game-meta')

            @break
        @case($category->audiobook_meta)
            {{-- La ficha del grupo puede ser la grabación o la obra, según qué
                 haya: los de Audible traen Audiobook y una lectura libre, Book. --}}
            @include($meta instanceof \App\Models\Audiobook ? 'torrent.partials.audiobook-meta' : 'torrent.partials.book-meta',
                     ['meta' => $meta, 'isbn13' => $isbn13 ?? null, 'asin' => $meta->asin ?? null])

            @break
        @case($category->book_meta)
            {{-- Un audiolibro también cae aquí: se agrupa por el ISBN de la
                 OBRA, así que la ficha que encabeza el grupo es la del libro y
                 sale junto a su e-book, que es lo que uno espera de
                 "similares". --}}
            @include('torrent.partials.book-meta', ['meta' => $meta, 'isbn13' => $isbn13 ?? null])

            @break
        @default
            @include('torrent.partials.no-meta')

            @break
    @endswitch
    @livewire('similar-torrent', ['category' => $category, 'tmdbId' => $tmdb, 'igdbId' => $igdb, 'isbn13' => $isbn13 ?? null, 'work' => $meta])
@endsection
