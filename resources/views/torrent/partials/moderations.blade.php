{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
{{--
    El historial de moderación del torrent, visible para todo el staff.

    Pedido por un moderador, y con razón: al rechazar o aplazar, el motivo es
    obligatorio, pero acababa únicamente dentro del mensaje privado entre quien
    decidió y el uploader. Ningún otro moderador podía leerlo, así que no había
    forma de mantener un criterio común ni de saber por qué se rechazó algo.

    Se muestra la SECUENCIA y no sólo la última decisión: lo normal es aplazar,
    que el uploader corrija y volver a mirar, y eso sólo se entiende en orden.

    El ida y vuelta posterior sigue en la conversación con el uploader, y ahí
    NO se enlaza a propósito: las conversaciones son por participante, así que
    otro moderador recibiría un 403. Es justamente la razón de guardar aquí el
    motivo en vez de apuntar al hilo.
--}}
<div class="panelV2" x-data="toggle">
    <h2 class="panel__heading" style="cursor: pointer" x-on:click="toggle">
        <i class="{{ config('other.font-awesome') }} fa-gavel"></i>
        {{ __('torrent.moderation-history') }}
        <i
            class="{{ config('other.font-awesome') }} fa-plus-circle fa-pull-right"
            x-show="isToggledOff"
        ></i>
        <i
            class="{{ config('other.font-awesome') }} fa-minus-circle fa-pull-right"
            x-show="isToggledOn"
            x-cloak
        ></i>
    </h2>
    <div class="data-table-wrapper" x-show="isToggledOn" x-cloak>
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('common.date') }}</th>
                    <th>{{ __('torrent.status') }}</th>
                    <th>{{ __('common.staff') }}</th>
                    <th>{{ __('common.reason') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($torrent->moderations as $moderation)
                    <tr>
                        <td>
                            <time
                                datetime="{{ $moderation->created_at }}"
                                title="{{ $moderation->created_at }}"
                            >
                                {{ $moderation->created_at?->toDayDateTimeString() }}
                            </time>
                        </td>
                        <td>
                            @php($colores = [
                                \App\Enums\ModerationStatus::APPROVED->value => 'text-green',
                                \App\Enums\ModerationStatus::REJECTED->value => 'text-red',
                                \App\Enums\ModerationStatus::POSTPONED->value => 'text-orange',
                            ])
                            <span class="{{ $colores[$moderation->status->value] ?? '' }}">
                                <b>{{ $moderation->etiqueta() }}</b>
                            </span>
                        </td>
                        <td>
                            @if ($moderation->user !== null)
                                <x-user-tag :anon="false" :user="$moderation->user" />
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td style="white-space: pre-wrap">{{ $moderation->message }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">{{ __('torrent.no-moderation-history') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
