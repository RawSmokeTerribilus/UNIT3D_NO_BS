<li class="form__group form__group--short-horizontal">
    <form
        method="POST"
        action="{{ route('requests.approved_fills.destroy', ['torrentRequest' => $torrentRequest]) }}"
        x-data="confirmation"
        style="display: contents"
    >
        @csrf
        @method('DELETE')
        <button
            x-on:click.prevent="confirmAction"
            data-b64-deletion-message="{{ base64_encode('¿Deshacer la aprobación? Se le quita la recompensa en BON a quien la rellenó (hasta lo que tenga).') }}"
            class="form__button form__button--outlined form__button--centered"
        >
            Deshacer aprobación
        </button>
    </form>
</li>
