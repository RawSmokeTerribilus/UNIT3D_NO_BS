@extends('layout.with-main')

@section('title')
    <title>{{ __('mediahub.platforms') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="{{ __('mediahub.platforms') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('mediahub.index') }}" class="breadcrumb__link">
            {{ __('mediahub.title') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('mediahub.platforms') }}
    </li>
@endsection

@section('page', 'page__game-platform--index')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('mediahub.platforms') }}</h2>
        <div class="panel__body">
            <ul class="mediahub-card__list">
                @forelse ($platforms as $platform)
                    <li class="mediahub-card__list-item">
                        <a
                            href="{{ route('torrents.index', ['igdbPlatformId' => $platform->id]) }}"
                            class="mediahub-card"
                        >
                            <h2 class="mediahub-card__heading">{{ $platform->name }}</h2>
                            <h3 class="mediahub-card__subheading">
                                <i class="{{ config('other.font-awesome') }} fa-gamepad"></i>
                                {{ $platform->games_count }} {{ __('mediahub.games') }}
                            </h3>
                        </a>
                    </li>
                @empty
                    <li class="mediahub-card__list-item">{{ __('mediahub.no-games') }}</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection
