{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
<form
    action="{{ $action }}"
    method="POST"
    @isset($confirm) data-confirm="{{ $confirm }}" @endisset
>
    @csrf
    <button
        type="submit"
        class="cmd-btn cmd-btn--{{ $level }}"
        title="{{ $tip }}"
    >
        <i class="{{ config('other.font-awesome') }} {{ $icon }}"></i>
        {{ $label }}
    </button>
</form>
