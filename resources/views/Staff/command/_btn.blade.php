@php
    $confirmJs = isset($confirm)
        ? "return confirm(" . json_encode($confirm) . ")"
        : null;
@endphp

<form
    action="{{ $action }}"
    method="POST"
    @if($confirmJs) onsubmit="{{ $confirmJs }}" @endif
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
