@extends('layout.with-main')

@section('title')
    <title>{{ __('mediahub.authors') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="{{ __('mediahub.authors') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('mediahub.index') }}" class="breadcrumb__link">
            {{ __('mediahub.title') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('mediahub.authors') }}
    </li>
@endsection

@section('page', 'page__book-author--index')

@section('main')
    {{--
        Rejilla de retratos, no tarjetas de texto: se calca el hub de personas
        de TMDB porque un autor tiene foto igual que un actor, y las fichas ya
        vienen con ella desde books:sync-authors. Mismas clases person--still /
        person--no-still, mismas iniciales de respaldo.

        Un mismo autor enlaza a sus e-books Y a sus audiolibros: el filtro va
        por olid, que es la clave que comparten los dos pivotes.
    --}}
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('mediahub.authors') }}</h2>
        <div
            class="panel__body"
            style="
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 2rem;
            "
        >
            @forelse ($authors as $author)
                <figure style="display: flex; flex-direction: column; align-items: center">
                    <a href="{{ route('torrents.index', ['bookAuthorOlid' => $author->olid]) }}">
                        @if ($author->photo_url === null)
                            <div class="person--no-still">{{ $author->initials() }}</div>
                        @else
                            <img
                                alt="{{ $author->name }}"
                                src="{{ tmdb_image('cast_mid', $author->photo_url) }}"
                                class="person--still"
                                loading="lazy"
                            />
                        @endif
                    </a>
                    <figcaption style="text-align: center">
                        {{ $author->name }}
                        <br />
                        <small>
                            @if ($author->books_count > 0)
                                <i class="{{ config('other.font-awesome') }} fa-book"></i>
                                {{ $author->books_count }}
                            @endif
                            @if ($author->audiobooks_count > 0)
                                <i class="{{ config('other.font-awesome') }} fa-headphones"></i>
                                {{ $author->audiobooks_count }}
                            @endif
                        </small>
                    </figcaption>
                </figure>
            @empty
                {{ __('mediahub.no-books') }}
            @endforelse
        </div>
    </section>
@endsection
