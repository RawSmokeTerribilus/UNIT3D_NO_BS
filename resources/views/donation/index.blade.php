{{--
    NOBS — Nuclear Order Bit Syndicate
    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra derivada de UNIT3D Community Edition (HDInnovations), de la que hereda
    la licencia GNU AGPL v3.0.

    Rediseño 2026-08-28. Tres cambios de fondo respecto a la vista de upstream:

    1. Las ventajas comunes salen UNA vez, arriba, en `.donation-perks`. Antes se
       repetían dentro de las cinco tarjetas, lo que las hacía leerse como una
       escalera de funciones — justo lo contrario del producto: aquí sólo cambian
       la duración y los BON, todo lo demás es idéntico a propósito.
    2. La vitalicia ocupa el ancho entero debajo de los otros cuatro tramos. Con
       cinco tarjetas en una rejilla de cuatro, la quinta se quedaba huérfana en
       una segunda fila; ahora preside en vez de sobrar.
    3. El diálogo va en DOS pasos numerados. Pagar primero y pegar el resguardo
       después era la parte que más confundía, y no se decía en ningún sitio.
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
    @php
        // El importe se formatea a la española (coma decimal) y con el símbolo
        // cuando la moneda es el euro. `config('donation.currency')` guarda el
        // código ISO porque es lo que espera PayPal, pero «5,00 EUR» en una
        // tarjeta se lee peor que «5,00 €».
        $moneda = config('donation.currency');
        $importe = fn ($coste) => number_format((float) $coste, 2, ',', '.').' '.($moneda === 'EUR' ? '€' : $moneda);

        // El nombre de la pasarela NO se escribe en la vista: el proveedor se
        // cambia. Se empezó con PayPal, que en cuenta personal enseña el nombre
        // legal del titular en el checkout, y eso para un sitio con nombre
        // propio queda fuera de lugar.
        $pasarela = config('donation.gateway_label');
        $importePrefijado = (bool) config('donation.amount_prefilled');
    @endphp

    <section x-data class="panelV2 donation-panel">
        <h2 class="panel__heading">¡Sostén {{ config('other.title') }}!</h2>
        <div class="panel__body donation-panel__body">
            <div class="donation-manifesto">
                <p class="donation-manifesto__lead">
                    <strong>Aquí no se vende ratio.</strong>
                    {{ config('donation.description') }}
                </p>
                <p>
                    Los premios de abajo los damos a manos llenas precisamente porque no
                    valen nada, y son los mismos en todos los tramos. Lo único que cambia
                    es cuánto duran y cuántos puntos caen. Dona si te sobra; si no te
                    sobra, no dones: el sitio seguirá aquí igual.
                </p>
            </div>

            {{-- Los recuentos salen de la config, no a mano: si mañana se añade un
                 icono al catálogo, esta frase se actualiza sola. --}}
            <ul class="donation-perks">
                <li class="donation-perks__title">Incluido en todos los tramos, sin excepción</li>
                <li class="donation-perks__item">
                    <i class="{{ config('other.font-awesome') }} fa-check"></i>
                    <span>Freeleech: lo que bajes no te lo cuenta nadie</span>
                </li>
                <li class="donation-perks__item">
                    <i class="{{ config('other.font-awesome') }} fa-check"></i>
                    <span>Subida multiplicada por 2</span>
                </li>
                <li class="donation-perks__item">
                    <i class="{{ config('other.font-awesome') }} fa-check"></i>
                    <span>Ranuras de descarga ilimitadas</span>
                </li>
                <li class="donation-perks__item">
                    <i class="{{ config('other.font-awesome') }} fa-check"></i>
                    <span>Inmunidad a los avisos automáticos (no lo restriegues)</span>
                </li>
                <li class="donation-perks__item">
                    <i class="{{ config('other.font-awesome') }} fa-check"></i>
                    <span>
                        Icono de rango a tu gusto:
                        {{ count(config('perks-donante.iconos')) }} para elegir
                    </span>
                </li>
                <li class="donation-perks__item">
                    <i class="{{ config('other.font-awesome') }} fa-check"></i>
                    <span>
                        Efecto animado en tu nombre:
                        {{ count(config('perks-donante.efectos')) }} para elegir
                    </span>
                </li>
                <li class="donation-perks__item">
                    <i class="{{ config('other.font-awesome') }} fa-check"></i>
                    <span>Insignia de donante junto al nombre</span>
                </li>
                <li class="donation-perks__item">
                    <i class="{{ config('other.font-awesome') }} fa-check"></i>
                    <span>Icono propio, el que tú subas</span>
                </li>
                <li class="donation-perks__item">
                    <i class="{{ config('other.font-awesome') }} fa-check"></i>
                    <span>La tibia satisfacción de que esto siga encendido un rato más</span>
                </li>
            </ul>

            <div class="donation-packages">
                @foreach ($packages as $package)
                    @php $esVitalicio = $package->donor_value === null; @endphp

                    <div @class(['donation-package__wrapper', 'donation-package__wrapper--lifetime' => $esVitalicio])>
                        <article @class(['donation-package', 'donation-package--lifetime' => $esVitalicio])>
                            <header class="donation-package__header">
                                @if ($esVitalicio)
                                    <span class="donation-package__badge">No caduca</span>
                                @endif

                                <h3 class="donation-package__name">{{ $package->name }}</h3>
                                <p class="donation-package__price-days">
                                    <span class="donation-package__price">
                                        {{ $importe($package->cost) }}
                                    </span>
                                    <span class="donation-package__separator">·</span>
                                    <span class="donation-package__days">
                                        @if ($esVitalicio)
                                            y ya está
                                        @else
                                            {{ $package->donor_value }} días
                                        @endif
                                    </span>
                                </p>
                            </header>
                            <p class="donation-package__description">
                                {{ $package->description }}
                            </p>
                            <div class="donation-package__stats">
                                @if ($esVitalicio)
                                    <div class="donation-package__stat">
                                        <span class="donation-package__stat-label">Duración</span>
                                        <span class="donation-package__stat-value">
                                            <i class="{{ config('other.font-awesome') }} fa-infinity"></i>
                                        </span>
                                    </div>
                                @endif

                                @if ($package->bonus_value !== null)
                                    <div class="donation-package__stat">
                                        <span class="donation-package__stat-label">Puntos BON</span>
                                        <span class="donation-package__stat-value">
                                            {{ number_format($package->bonus_value, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @endif

                                @if ($package->fl_token_value !== null)
                                    <div class="donation-package__stat">
                                        <span class="donation-package__stat-label">Cupones</span>
                                        <span class="donation-package__stat-value">
                                            {{ $package->fl_token_value }}
                                            @if ($esVitalicio)
                                                <span class="donation-package__stat-note">simbólico</span>
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <footer class="donation-package__footer">
                                <button
                                    type="button"
                                    @class([
                                        'form__button',
                                        'form__button--filled' => $esVitalicio,
                                        'form__button--outlined' => !$esVitalicio,
                                    ])
                                    x-on:click.stop="$refs.dialog{{ $package->id }}.showModal()"
                                >
                                    <i class="{{ config('other.font-awesome') }} fa-heart"></i>
                                    ¡Lo quiero!
                                </button>
                            </footer>
                        </article>
                    </div>
                @endforeach
            </div>

            <p class="donation-notice text-warning">
                <i class="{{ config('other.font-awesome') }} fa-triangle-exclamation"></i>
                <span>
                    Cada donación la revisa una persona a mano, así que puede tardar hasta
                    48&nbsp;horas en aplicarse. No pasa nada, no se ha perdido.
                </span>
            </p>
        </div>

        {{-- Los diálogos van DENTRO del `x-data` de la sección: `$refs` no cruza
             fuera del componente Alpine que los declara. --}}
        @foreach ($packages as $package)
            <dialog class="dialog donation-dialog" x-ref="dialog{{ $package->id }}">
                <h2 class="dialog__heading">
                    {{ $package->name }} · {{ $importe($package->cost) }}
                </h2>
                <ol class="donation-dialog__steps">
                    <li class="donation-dialog__step">
                        <h3 class="donation-dialog__step-title">Primero paga en {{ $pasarela }}</h3>
                        <div class="donation-dialog__step-body">
                            @if ($package->payment_url !== null)
                                @if ($importePrefijado)
                                    <p>
                                        Se abre en otra pestaña. El importe ya va fijado en
                                        {{ $importe($package->cost) }}, no tienes que escribir nada.
                                    </p>
                                @else
                                    {{-- Si la pasarela no acepta el importe por enlace, hay
                                         que DECIRLO. Prometer que no hay nada que teclear
                                         cuando sí lo hay acaba en donaciones con el importe
                                         equivocado y en un tramo que no cuadra. --}}
                                    <p>
                                        Se abre en otra pestaña. Escribe
                                        <strong>{{ $importe($package->cost) }}</strong>
                                        como cantidad: es lo que corresponde a este tramo.
                                    </p>
                                @endif
                                {{-- El enlace se emite LITERAL. Pegarle `?amount=` a un
                                     botón alojado de PayPal lo tumba con «la organización
                                     seleccionada no está disponible». --}}
                                {{-- El usuario en el comentario. Sin esto la donación
                                     llega sin dueño y hay que cruzarla a mano contra la
                                     lista de pendientes, que es exactamente el trabajo
                                     que el paso 2 existe para evitar. Se pinta el nombre
                                     ya resuelto para que se pueda copiar tal cual, sin
                                     que nadie tenga que recordar cómo se escribía. --}}
                                <p class="donation-dialog__nota">
                                    <i class="{{ config('other.font-awesome') }} fa-circle-exclamation"></i>
                                    <span>
                                        Importante: escribe tu usuario
                                        <code>{{ auth()->user()->username }}</code>
                                        en el comentario de la donación. Es lo que nos
                                        permite saber que es tuya.
                                    </span>
                                </p>
                                <a
                                    class="form__button form__button--filled"
                                    href="{{ $package->payment_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <i class="{{ config('other.font-awesome') }} fa-arrow-up-right-from-square"></i>
                                    Pagar {{ $importe($package->cost) }} en {{ $pasarela }}
                                </a>
                            @else
                                {{-- Sin botón propio se cae a las pasarelas genéricas, que
                                     es como funcionaba antes. Ninguna instalación se rompe
                                     por no haber rellenado `payment_url`. --}}
                                <p>
                                    Manda <strong>{{ $importe($package->cost) }}</strong>
                                    por la pasarela que prefieras.
                                </p>
                                @foreach ($gateways->sortBy('position') as $gateway)
                                    @if (str_starts_with($gateway->address, 'http'))
                                        <a
                                            class="form__button form__button--filled"
                                            href="{{ $gateway->address }}{{ str_contains($gateway->address, '?') ? '&' : '?' }}amount={{ $package->cost }}&currency_code={{ $moneda }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <i class="{{ config('other.font-awesome') }} fa-arrow-up-right-from-square"></i>
                                            Pagar con {{ $gateway->name }}
                                        </a>
                                    @else
                                        <p class="form__group">
                                            <input
                                                class="form__text"
                                                type="text"
                                                disabled
                                                value="{{ $gateway->address }}"
                                                id="{{ 'gateway-'.$gateway->id.'-'.$package->id }}"
                                            />
                                            <label
                                                for="{{ 'gateway-'.$gateway->id.'-'.$package->id }}"
                                                class="form__label form__label--floating"
                                            >
                                                {{ $gateway->name }}
                                            </label>
                                        </p>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </li>
                    <li class="donation-dialog__step">
                        <h3 class="donation-dialog__step-title">
                            Después pega aquí el número de la transacción
                        </h3>
                        <div class="donation-dialog__step-body">
                            <p>
                                {{ $pasarela }} te da un código de recibo al terminar, o te lo
                                manda por correo. Pégalo aquí y envía: sin ese código no
                                podemos saber que la donación es tuya.
                            </p>
                            <form
                                class="dialog__form"
                                method="POST"
                                action="{{ route('donations.store') }}"
                            >
                                @csrf
                                <input type="hidden" name="package_id" value="{{ $package->id }}" />
                                <p class="form__group">
                                    <input
                                        id="transaction-{{ $package->id }}"
                                        class="form__text"
                                        type="text"
                                        name="transaction"
                                        required
                                        placeholder=" "
                                    />
                                    <label
                                        class="form__label form__label--floating"
                                        for="transaction-{{ $package->id }}"
                                    >
                                        Número de transacción o de recibo
                                    </label>
                                </p>
                                <p class="donation-dialog__hint">
                                    Todavía no lo tienes hasta que pagues en el paso 1.
                                </p>
                                <p class="form__group form__group--horizontal">
                                    <button type="submit" class="form__button form__button--filled">
                                        Enviar el recibo
                                    </button>
                                    <button
                                        formmethod="dialog"
                                        formnovalidate
                                        class="form__button form__button--outlined"
                                    >
                                        Cerrar
                                    </button>
                                </p>
                            </form>
                        </div>
                    </li>
                </ol>
            </dialog>
        @endforeach
    </section>
@endsection
