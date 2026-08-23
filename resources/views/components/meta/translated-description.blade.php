@props([
    'texto',
    'original' => null,
    'idioma' => null,
])

{{--
    La sinopsis, avisando cuando es una traducción automática y dejando ver el
    original.

    Existe como componente y no copiado en cada parcial porque lo usan los
    tres: libro, audiolibro y juego. Antes sólo lo tenía el libro, y como
    aviso a secas dentro de un `title=`, que en móvil no se ve nunca.

    El aviso va DELANTE del texto a propósito: `.meta__description` tiene
    `max-height: 150px` con scroll, así que al final quedaba fuera de la parte
    visible y había que bajar con la rueda para verlo. Un aviso que no se ve
    no avisa.

    El botón usa Alpine y clases que ya existen (`form__button--text`), así que
    no hace falta compilar nada.
--}}
@php($esTraducida = $idioma !== null && $idioma !== '' && $original !== null && trim((string) $original) !== '')

@if ($esTraducida)
    <p class="meta__description" x-data="{ verOriginal: false }">
        <small>
            <i class="{{ config('other.font-awesome') }} fa-language"></i>
            {{ __('torrent.auto-translated', ['idioma' => strtoupper($idioma)]) }}
            <button
                type="button"
                class="form__button form__button--text"
                x-on:click="verOriginal = !verOriginal"
            >
                <span x-show="!verOriginal">{{ __('torrent.view-original') }}</span>
                <span x-cloak x-show="verOriginal">{{ __('torrent.view-translation') }}</span>
            </button>
        </small>
        <br />
        <span x-show="!verOriginal">{{ $texto }}</span>
        <span x-cloak x-show="verOriginal">{{ $original }}</span>
    </p>
@else
    <p class="meta__description">{{ $texto }}</p>
@endif
