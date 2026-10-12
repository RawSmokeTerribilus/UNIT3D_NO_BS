{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    Métodos de pago × tramos. Cada clic se guarda al momento. Una casilla
    marcada sólo se ofrece si su método (columna) y su tramo (fila) están
    encendidos; si no, sale atenuada y vuelve sola al encenderlos.
--}}
<section class="panelV2" style="margin-bottom: 20px">
    <h2 class="panel__heading">
        <i
            class="{{ config('other.font-awesome') }} fa-money-bill-transfer"
            style="margin-right: 8px"
        ></i>
        Métodos de pago por tramo
        <small class="text-muted" style="font-weight: normal">— sólo Owners · se guarda al pulsar</small>
    </h2>
    <div class="panel__body">
        @if (! config('donation.is_enabled'))
            <p class="text-warning">
                <i class="{{ config('other.font-awesome') }} fa-triangle-exclamation"></i>
                El sistema de donaciones está apagado (arriba, en «Donaciones»): nada de esto
                se ve hasta que se encienda.
            </p>
        @endif

        @if ($gateways->isEmpty())
            <p>
                No hay métodos de pago.
                <a href="{{ route('staff.gateways.create') }}">Crear uno</a>
            </p>
        @else
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tramo</th>
                            @foreach ($gateways as $gateway)
                                <th style="text-align: center">
                                    <a href="{{ route('staff.gateways.edit', ['gateway' => $gateway]) }}">
                                        @if ($gateway->icono)
                                            <i class="{{ $gateway->icono }}"></i>
                                        @endif
                                        {{ $gateway->name }}
                                    </a>
                                    <br />
                                    <button
                                        type="button"
                                        class="form__button form__button--text {{ $gateway->is_active ? 'text-green' : 'text-red' }}"
                                        wire:click="toggleMetodo({{ $gateway->id }})"
                                        wire:loading.attr="disabled"
                                        title="Encender o apagar el método entero"
                                    >
                                        <i
                                            class="{{ config('other.font-awesome') }} {{ $gateway->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"
                                        ></i>
                                        {{ $gateway->is_active ? 'Encendido' : 'Apagado' }}
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($packages as $package)
                            <tr>
                                <td>
                                    <strong>{{ $package->name }}</strong>
                                    · {{ number_format((float) $package->cost, 2, ',', '.') }} €
                                    <br />
                                    <button
                                        type="button"
                                        class="form__button form__button--text {{ $package->is_active ? 'text-green' : 'text-red' }}"
                                        wire:click="toggleTramo({{ $package->id }})"
                                        wire:loading.attr="disabled"
                                        title="Encender o apagar el tramo entero"
                                    >
                                        <i
                                            class="{{ config('other.font-awesome') }} {{ $package->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"
                                        ></i>
                                        {{ $package->is_active ? 'Encendido' : 'Apagado' }}
                                    </button>
                                </td>
                                @foreach ($gateways as $gateway)
                                    @php
                                        $marcada = $gateway->packages->contains('id', $package->id);
                                        $efectiva = $marcada && $gateway->is_active && $package->is_active;
                                    @endphp

                                    <td style="text-align: center">
                                        <button
                                            type="button"
                                            class="form__button form__button--text {{ $efectiva ? 'text-green' : 'text-muted' }}"
                                            wire:click="toggleCombinacion({{ $gateway->id }}, {{ $package->id }})"
                                            wire:loading.attr="disabled"
                                            title="{{ $efectiva ? 'Se ofrece' : ($marcada ? 'Marcada, pero el método o el tramo está apagado' : 'No se ofrece') }}"
                                        >
                                            <i
                                                class="{{ config('other.font-awesome') }} {{ $marcada ? 'fa-square-check' : 'fa-square' }} fa-lg"
                                            ></i>
                                        </button>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($huerfanos as $package)
                <p class="text-warning">
                    <i class="{{ config('other.font-awesome') }} fa-triangle-exclamation"></i>
                    «{{ $package->name }}» está encendido pero ningún método lo cubre: en la
                    página de donar saldrá como no disponible.
                </p>
            @endforeach

            <p class="text-muted">
                Direcciones, instrucciones y justificantes de cada método:
                <a href="{{ route('staff.gateways.index') }}">Métodos de pago</a>.
            </p>
        @endif
    </div>
</section>
