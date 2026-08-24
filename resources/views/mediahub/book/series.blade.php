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
    <title>{{ __('mediahub.book-series') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="{{ __('mediahub.book-series') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('mediahub.index') }}" class="breadcrumb__link">
            {{ __('mediahub.title') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('mediahub.book-series') }}
    </li>
@endsection

@section('page', 'page__book-series--index')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('mediahub.book-series') }}</h2>
        <div class="panel__body">
            <ul class="mediahub-card__list">
                @forelse ($series as $serie)
                    <li class="mediahub-card__list-item">
                        <a
                            href="{{ route('torrents.index', ['bookSeriesId' => $serie->id]) }}"
                            class="mediahub-card"
                        >
                            <h2 class="mediahub-card__heading">{{ $serie->name }}</h2>
                            <h3 class="mediahub-card__subheading">
                                <i class="{{ config('other.font-awesome') }} fa-book"></i>
                                {{ $serie->books_count }} {{ __('mediahub.books') }} |
                                <i class="{{ config('other.font-awesome') }} fa-headphones"></i>
                                {{ $serie->audiobooks_count }} {{ __('mediahub.audiobooks') }}
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
