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
