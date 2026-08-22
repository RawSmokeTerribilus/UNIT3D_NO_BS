@extends('layout.with-main')

@section('title')
    <title>
        {{ $torrent->name }} - {{ __('torrent.torrents') }} - {{ config('other.title') }}
    </title>
@endsection

@section('meta')
    <meta
        name="description"
        content="{{ __('torrent.meta-desc', ['name' => $torrent->name]) }}!"
    />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('torrents.index') }}" class="breadcrumb__link">
            {{ __('torrent.torrents') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ $torrent->name }}
    </li>
@endsection

@section('page', 'page__torrent--show')

@section('main')
    @switch(true)
        @case($torrent->category->movie_meta)
            @include('torrent.partials.movie-meta', ['category' => $torrent->category, 'meta' => $torrent->movie, 'tmdb' => $torrent->tmdb_movie_id])

            @break
        @case($torrent->category->tv_meta)
            @if ($torrent->tv !== null)
                @include('torrent.partials.tv-meta', ['category' => $torrent->category, 'meta' => $torrent->tv, 'tmdb' => $torrent->tmdb_tv_id])
            @elseif ($torrent->malAnime !== null)
                @include('torrent.partials.mal-meta', ['category' => $torrent->category, 'mal' => $torrent->malAnime])
            @else
                @include('torrent.partials.no-meta', ['category' => $torrent->category])
            @endif

            @break
        @case($torrent->category->game_meta)
            @include('torrent.partials.game-meta', ['category' => $torrent->category, 'meta' => $torrent->game, 'igdb' => $torrent->igdb])

            @break
        @case($torrent->category->book_meta)
            @include('torrent.partials.book-meta', ['category' => $torrent->category, 'meta' => $torrent->book, 'isbn13' => $torrent->isbn13])

            @break
        @case($torrent->category->audiobook_meta)
            {{-- Mismo patrón que tv_meta cuatro líneas más arriba: si la ficha
                 propia no existe, se prueba la otra antes de rendirse.

                 Una lectura libre no tiene ASIN, porque la grabación no existe
                 en ningún catálogo comercial, así que $torrent->audiobook es
                 null. Pero el libro que se lee sí tiene ISBN y su ficha está
                 cargada: la portada, la sinopsis y el autor son de la OBRA y no
                 cambian porque los lea otra voz. Sin esto salía "NO META"
                 teniendo el dato delante. --}}
            @if ($torrent->audiobook !== null)
                @include('torrent.partials.audiobook-meta', ['category' => $torrent->category, 'meta' => $torrent->audiobook, 'asin' => $torrent->asin])
            @elseif ($torrent->book !== null)
                @include('torrent.partials.book-meta', ['category' => $torrent->category, 'meta' => $torrent->book, 'isbn13' => $torrent->isbn13])
            @else
                @include('torrent.partials.no-meta', ['category' => $torrent->category])
            @endif

            @break
        @default
            @include('torrent.partials.no-meta', ['category' => $torrent->category])

            @break
    @endswitch
    <h1 class="torrent__name">
        {{ $torrent->name }}
    </h1>
    @include('torrent.partials.general')
    @include('torrent.partials.buttons')
    <livewire:swarm-intelligence :torrentId="$torrent->id" />

    {{-- Tools block --}}
    @if (auth()->user()->internals()->exists() ||auth()->user()->group->is_editor ||auth()->user()->group->is_modo ||(auth()->id() === $torrent->user_id && $canEdit))
        @include('torrent.partials.tools')
    @endif

    {{-- Audits, reports, downloads block --}}
    @if (auth()->user()->group->is_modo)
        @include('torrent.partials.audits')
        @include('torrent.partials.reports')
        @include('torrent.partials.downloads')
    @endif

    {{-- MediaInfo block --}}
    @if ($torrent->mediainfo !== null)
        @include('torrent.partials.mediainfo')
    @endif

    {{-- BDInfo block --}}
    @if ($torrent->bdinfo !== null)
        @include('torrent.partials.bdinfo')
    @endif

    {{-- Description block --}}
    @include('torrent.partials.description')

    {{-- Subtitles block --}}
    @if ($torrent->category->movie_meta || $torrent->category->tv_meta)
        @include('torrent.partials.subtitles')
    @endif

    {{-- Extra meta block --}}
    @include('torrent.partials.extra-meta', ['meta' => $torrent->movie ?? $torrent->tv ?? $torrent->game ?? null])

    {{-- Comments block --}}
    @if ($torrent->status === \App\Enums\ModerationStatus::APPROVED)
        @include('torrent.partials.comments')
    @endif
@endsection
