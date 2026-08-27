{{--
    NOBS — Nuclear Order Bit Syndicate
    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra derivada de UNIT3D Community Edition (HDInnovations), de la que hereda
    la licencia GNU AGPL v3.0.

    La vista original estaba pensada para criptomonedas: pintaba la pasarela en
    un <input disabled> para copiar una wallet a mano. Aquí una pasarela cuya
    dirección es una URL se renderiza como enlace, que es lo que necesita PayPal.
--}}
@extends('layout.with-main')

@section('title')
    <title>Donar - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Sostén {{ config('other.title') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb--active">Donar</li>
@endsection

@section('page', 'page__donation--index')

@section('main')
    <section x-data class="panelV2">
        <h2 class="panel__heading">¡SOSTÉN {{ strtoupper(config('other.title')) }}!</h2>
        <div class="panel__body">
            <p>{{ config('donation.description') }}</p>
            <p class="text-info">
                <strong>Aquí no se vende ratio.</strong>
                Esto no es una tienda: el dinero va íntegro a mantener los cacharros
                encendidos. Los premios de abajo son un chiste con el que llevamos
                décadas —
                <em>¡ARENA EN LA CARA NUNCA MÁS!</em>
                — y los damos a manos llenas precisamente porque no valen nada.
                Dona si te sobra. Si no te sobra, no dones: el sitio seguirá aquí igual.
            </p>
            <div class="donation-packages">
                @foreach ($packages as $package)
                    <div class="donation-package__wrapper">
                        <div class="donation-package">
                            <div class="donation-package__header">
                                <div class="donation-package__name">
                                    @if ($package->badge_icon !== null)
                                        <i
                                            class="{{ $package->badge_icon }}"
                                            style="color: {{ $package->badge_color ?? 'inherit' }}"
                                            title="{{ $package->badge_title }}"
                                        ></i>
                                    @endif

                                    {{ $package->name }}
                                </div>
                                <div class="donation-package__price-days">
                                    <span class="donation-package__price">
                                        {{ $package->cost }} {{ config('donation.currency') }}
                                    </span>
                                    <span class="donation-package__separator">-</span>
                                    <span class="donation-package__days">
                                        @if ($package->donor_value === null)
                                            Para siempre
                                        @else
                                            {{ $package->donor_value }} días
                                        @endif
                                    </span>
                                </div>
                                <div class="donation-package__description">
                                    {{ $package->description }}
                                </div>
                            </div>
                            <div class="donation-package__benefits-list">
                                <ol class="benefits-list">
                                    @if ($package->badge_title !== null)
                                        <li>
                                            Rango
                                            <strong>{{ $package->badge_title }}</strong>
                                            junto a tu nombre, a la vista de todo el mundo
                                        </li>
                                    @endif

                                    <li>Freeleech permanente: lo que bajes no te lo cuenta nadie</li>
                                    <li>
                                        @if ($package->donor_value === null)
                                            Subida multiplicada por 2,55
                                        @else
                                            Subida multiplicada por 2
                                        @endif
                                    </li>
                                    <li>Inmunidad a los avisos automáticos (no lo restriegues)</li>
                                    <li>Invitaciones sin restricción, aunque estén cerradas</li>
                                    <li
                                        style="
                                            background-image: url(/img/sparkels.gif);
                                            width: auto;
                                        "
                                    >
                                        Purpurina en el nombre
                                    </li>
                                    <li>
                                        Estrella de donante
                                        @if ($package->donor_value === null)
                                            <i
                                                id="lifeline"
                                                class="fal fa-star"
                                                title="Donante de por vida"
                                            ></i>
                                        @else
                                            <i class="fal fa-star text-gold" title="Donante"></i>
                                        @endif
                                    </li>
                                    @if ($package->donor_value === null)
                                        <li>Ranuras de descarga ilimitadas</li>
                                        <li>Icono propio, el que tú subas</li>
                                    @endif

                                    @if ($package->upload_value !== null)
                                        <li>
                                            {{ App\Helpers\StringHelper::formatBytes($package->upload_value) }}
                                            de subida, de regalo
                                        </li>
                                    @endif

                                    @if ($package->bonus_value !== null)
                                        <li>
                                            {{ number_format($package->bonus_value) }} puntos BON
                                        </li>
                                    @endif

                                    @if ($package->invite_value !== null)
                                        <li>{{ $package->invite_value }} invitaciones</li>
                                    @endif

                                    <li>
                                        La tibia satisfacción de que
                                        {{ config('other.title') }}
                                        siga encendido un mes más
                                    </li>
                                </ol>
                            </div>
                            <div class="donation-package__footer">
                                <p class="form__group form__group--horizontal">
                                    <button
                                        class="form__button form__button--filled form__button--centered"
                                        x-on:click.stop="$refs.dialog{{ $package->id }}.showModal()"
                                    >
                                        <i class="fas fa-handshake"></i>
                                        ¡LO QUIERO!
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @foreach ($packages as $package)
            <dialog class="dialog" x-ref="dialog{{ $package->id }}">
                <h4 class="dialog__heading">
                    Donar {{ $package->cost }} {{ config('donation.currency') }}
                </h4>
                <form
                    class="dialog__form"
                    method="POST"
                    action="{{ route('donations.store') }}"
                    x-on:click.outside="$refs.dialog{{ $package->id }}.close()"
                >
                    @csrf
                    <span class="text-success text-center">
                        Son dos pasos: primero pagas, luego nos pegas el resguardo.
                    </span>
                    <div class="form__group--horizontal">
                        @foreach ($gateways->sortBy('position') as $gateway)
                            @if (str_starts_with($gateway->address, 'http'))
                                <p class="form__group">
                                    <a
                                        class="form__button form__button--filled form__button--centered"
                                        href="{{ $gateway->address }}{{ str_contains($gateway->address, '?') ? '&' : '?' }}amount={{ $package->cost }}&currency_code={{ config('donation.currency') }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <i class="fas fa-arrow-up-right-from-square"></i>
                                        Pagar con {{ $gateway->name }}
                                    </a>
                                </p>
                            @else
                                <p class="form__group">
                                    <input
                                        class="form__text"
                                        type="text"
                                        disabled
                                        value="{{ $gateway->address }}"
                                        id="{{ 'gateway-' . $gateway->id }}"
                                    />
                                    <label
                                        for="{{ 'gateway-' . $gateway->id }}"
                                        class="form__label form__label--floating"
                                    >
                                        {{ $gateway->name }}
                                    </label>
                                </p>
                            @endif
                        @endforeach

                        <p class="text-info">
                            Manda
                            <strong>
                                {{ $package->cost }} {{ config('donation.currency') }}
                            </strong>
                            por la pasarela que prefieras. Apunta el número de
                            transacción, el del recibo o lo que te dé, y pégalo aquí abajo.
                        </p>
                    </div>
                    <div class="form__group--horizontal">
                        <p class="form__group">
                            <input
                                class="form__text"
                                type="text"
                                disabled
                                value="{{ $package->cost }}"
                                id="package-cost"
                            />
                            <label for="package-cost" class="form__label form__label--floating">
                                Importe
                            </label>
                        </p>
                        <p class="form__group">
                            <input
                                class="form__text"
                                type="text"
                                value=""
                                id="proof"
                                name="transaction"
                            />
                            <label for="proof" class="form__label form__label--floating">
                                Nº de transacción o de recibo
                            </label>
                        </p>
                    </div>
                    <span class="text-warning">
                        * Lo revisamos a mano. Puede tardar hasta 48 horas.
                    </span>
                    <p class="form__group">
                        <input type="hidden" name="package_id" value="{{ $package->id }}" />
                        <button class="form__button form__button--filled">Enviar resguardo</button>
                        <button
                            formmethod="dialog"
                            formnovalidate
                            class="form__button form__button--outlined"
                        >
                            {{ __('common.cancel') }}
                        </button>
                    </p>
                </form>
            </dialog>
        @endforeach
    </section>
@endsection
