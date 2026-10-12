{{--
    NOBS — campos de un método de pago, compartidos por crear y editar.
    `$gateway` es null al crear.

    Antes era una fila de tabla con cuatro celdas. Con las instrucciones y el
    patrón del justificante ya no cabe: una dirección de Monero son 95
    caracteres y las instrucciones son un párrafo.
--}}
<div class="form__group--horizontal">
    <p class="form__group">
        <input
            id="name"
            class="form__text"
            type="text"
            name="name"
            value="{{ old('name', $gateway?->name) }}"
            required
            placeholder=" "
        />
        <label for="name" class="form__label form__label--floating">Nombre (lo ve el donante)</label>
    </p>
    <p class="form__group">
        <input
            id="position"
            class="form__text"
            type="number"
            name="position"
            value="{{ old('position', $gateway?->position) }}"
            placeholder=" "
        />
        <label for="position" class="form__label form__label--floating">Posición</label>
    </p>
    <p class="form__group">
        <input
            id="icono"
            class="form__text"
            type="text"
            name="icono"
            value="{{ old('icono', $gateway?->icono) }}"
            placeholder=" "
        />
        <label for="icono" class="form__label form__label--floating">
            Icono Font Awesome (p. ej. «fab fa-ethereum»)
        </label>
    </p>
</div>
<p class="form__group">
    <input
        id="address"
        class="form__text"
        type="text"
        name="address"
        value="{{ old('address', $gateway?->address) }}"
        required
        placeholder=" "
    />
    <label for="address" class="form__label form__label--floating">
        Dirección de la wallet, o enlace (si empieza por http se pinta como botón)
    </label>
</p>
<p class="form__group">
    <textarea
        id="instrucciones"
        class="form__textarea"
        name="instrucciones"
        placeholder=" "
    >{{ old('instrucciones', $gateway?->instrucciones) }}</textarea>
    <label for="instrucciones" class="form__label form__label--floating">
        Instrucciones para el donante. Huecos: {importe} y {usuario}. Una línea en blanco separa párrafos.
    </label>
</p>
<div class="form__group--horizontal">
    <p class="form__group">
        <input
            id="etiqueta_justificante"
            class="form__text"
            type="text"
            name="etiqueta_justificante"
            value="{{ old('etiqueta_justificante', $gateway?->etiqueta_justificante) }}"
            placeholder=" "
        />
        <label for="etiqueta_justificante" class="form__label form__label--floating">
            Qué se le pide pegar (p. ej. «Hash de la transacción»)
        </label>
    </p>
    <p class="form__group">
        <input
            id="patron_justificante"
            class="form__text"
            type="text"
            name="patron_justificante"
            value="{{ old('patron_justificante', $gateway?->patron_justificante) }}"
            placeholder=" "
        />
        <label for="patron_justificante" class="form__label form__label--floating">
            Patrón (regex sin delimitadores; vacío = cualquier texto)
        </label>
    </p>
</div>
<p class="form__group">
    <input name="is_active" type="hidden" value="0" />
    <input
        id="is_active"
        class="form__checkbox"
        name="is_active"
        type="checkbox"
        value="1"
        @checked(old('is_active', $gateway?->is_active))
    />
    <label for="is_active" class="form__label">
        Activo. En qué tramos se ofrece se elige en Configuración → Donaciones.
    </label>
</p>
