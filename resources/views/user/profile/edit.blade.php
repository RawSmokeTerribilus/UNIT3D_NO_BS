@extends('layout.with-main')

@section('title')
    <title>{{ $user->username }} {{ __('user.profile') }} - {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('users.show', ['user' => $user]) }}" class="breadcrumb__link">
            {{ $user->username }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('user.edit-profile') }}
    </li>
@endsection

@section('nav-tabs')
    @include('user.buttons.user')
@endsection

@section('page', 'page__user-profile--edit')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('user.edit-profile') }}</h2>
        <div class="panel__body">
            <form
                method="POST"
                action="{{ route('users.update', ['user' => $user]) }}"
                enctype="multipart/form-data"
                class="form"
            >
                @csrf
                @method('PATCH')
                <p class="form__group">
                    <label for="image" class="form__label">{{ __('user.avatar') }}</label>
                    <input
                        id="image"
                        class="form__file"
                        accept=".jpg, .jpeg, .bmp, .png, .tiff, .gif"
                        name="image"
                        type="file"
                    />
                </p>
                @if ($user->is_lifetime)
                    <p class="form__group">
                        <label for="icon" class="form__label">Icono</label>
                        <input
                            id="icon"
                            class="form__file"
                            accept=".jpg, .jpeg, .bmp, .png, .tiff, .gif"
                            name="icon"
                            type="file"
                        />
                    </p>
                @endif

                @if ($user->is_donor)
                    <fieldset class="form__group">
                        <legend class="form__label">Insignia de donante</legend>
                        <p class="form__group">
                            Sustituye a la estrella que va junto a tu nombre. Si no
                            eliges ninguna, se queda la estrella de siempre.
                        </p>
                        <div
                            style="
                                display: flex;
                                flex-wrap: wrap;
                                gap: 0.75rem;
                            "
                        >
                            <label
                                style="
                                    display: flex;
                                    align-items: center;
                                    gap: 0.35rem;
                                    min-width: 11rem;
                                "
                            >
                                <input
                                    type="radio"
                                    name="donor_badge_icon"
                                    value=""
                                    @checked($user->donor_badge_icon === null)
                                />
                                <i class="fal fa-star text-gold"></i>
                                Ninguna (la estrella)
                            </label>
                            @foreach (config('insignias.catalogo') as $fichero => $rotulo)
                                <label
                                    style="
                                        display: flex;
                                        align-items: center;
                                        gap: 0.35rem;
                                        min-width: 11rem;
                                    "
                                >
                                    <input
                                        type="radio"
                                        name="donor_badge_icon"
                                        value="{{ $fichero }}"
                                        @checked($user->donor_badge_icon === $fichero)
                                    />
                                    <img
                                        src="{{ asset('img/insignias/' . $fichero) }}"
                                        alt="{{ $rotulo }}"
                                        style="max-height: 24px; vertical-align: middle"
                                        loading="lazy"
                                    />
                                    {{ $rotulo }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <p class="form__group">
                    <input
                        id="title"
                        class="form__text"
                        name="title"
                        placeholder=" "
                        type="text"
                        value="{{ $user->title }}"
                    />
                    <label for="title" class="form__label form__label--floating">
                        {{ __('user.custom-title') }}
                    </label>
                </p>
                @livewire('bbcode-input', ['name' => 'about', 'label' => __('user.about-me'), 'required' => false, 'content' => old('about', $user->about)], key('about'))
                @livewire('bbcode-input', ['name' => 'signature', 'label' => __('user.forum-signature'), 'required' => false, 'content' => old('signature', $user->signature)], key('signature'))

                <p class="form__group">
                    <button class="form__button form__button--filled">
                        {{ __('common.submit') }}
                    </button>
                </p>
            </form>
        </div>
    </section>

    @include('partials.telegram_settings')
@endsection
