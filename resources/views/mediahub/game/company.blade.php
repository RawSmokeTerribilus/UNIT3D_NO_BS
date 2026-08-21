@extends('layout.with-main')

@section('title')
    <title>{{ __('mediahub.game-companies') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="{{ __('mediahub.game-companies') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('mediahub.index') }}" class="breadcrumb__link">
            {{ __('mediahub.title') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('mediahub.game-companies') }}
    </li>
@endsection

@section('page', 'page__game-company--index')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('mediahub.game-companies') }}</h2>
        <div class="panel__body">
            <ul class="mediahub-card__list">
                @forelse ($companies as $company)
                    <li class="mediahub-card__list-item">
                        <a
                            href="{{ route('torrents.index', ['igdbCompanyId' => $company->id]) }}"
                            class="mediahub-card"
                        >
                            <h2 class="mediahub-card__heading">{{ $company->name }}</h2>
                            <h3 class="mediahub-card__subheading">
                                <i class="{{ config('other.font-awesome') }} fa-gamepad"></i>
                                {{ $company->games_count }} {{ __('mediahub.games') }}
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
