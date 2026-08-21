@extends('layout.with-main')

@section('title')
    <title>{{ __('mediahub.book-genres') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="{{ __('mediahub.book-genres') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('mediahub.index') }}" class="breadcrumb__link">
            {{ __('mediahub.title') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('mediahub.book-genres') }}
    </li>
@endsection

@section('page', 'page__book-genre--index')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('mediahub.book-genres') }}</h2>
        <div class="panel__body">
            <ul class="mediahub-card__list">
                @forelse ($genres as $genre)
                    <li class="mediahub-card__list-item">
                        <a
                            href="{{ route('torrents.index', ['bookGenreId' => $genre->id]) }}"
                            class="mediahub-card"
                        >
                            <h2 class="mediahub-card__heading">{{ $genre->name }}</h2>
                            <h3 class="mediahub-card__subheading">
                                <i class="{{ config('other.font-awesome') }} fa-book"></i>
                                {{ $genre->books_count }} {{ __('mediahub.books') }} |
                                <i class="{{ config('other.font-awesome') }} fa-headphones"></i>
                                {{ $genre->audiobooks_count }} {{ __('mediahub.audiobooks') }}
                            </h3>
                        </a>
                    </li>
                @empty
                    <li class="mediahub-card__list-item">{{ __('mediahub.no-books') }}</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection
