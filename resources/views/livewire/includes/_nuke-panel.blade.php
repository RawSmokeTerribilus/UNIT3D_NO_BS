{{--
    NOBS — Nuclear Order Bit Syndicate
    Copyright (C) 2026 RawSmoke
    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    Modulo «nuke» de la lista de reportes. La llave, la tapa y la seta llevan su
    estado en el navegador (public/js/nuke-panel.js) y van con wire:ignore para
    que Livewire no las resetee al refrescar. Los interruptores y el contador
    son de Livewire: la cuenta la hace el servidor. Solo admin y superiores.
--}}
<section class="panelV2 nuke" data-nuke-root>
    <h2 class="panel__heading nuke__titulo">
        Nuke 'em all
        <small>protocolo de descarte masivo de reportes</small>
    </h2>
    <div class="nuke__chasis">
        <div class="nuke__col nuke__col--llave" wire:ignore data-nuke-lado>
            <span class="nuke__piloto" aria-hidden="true"></span>
            <span class="nuke__leds" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span>
            <button
                type="button"
                class="nuke__llave"
                data-nuke-llave
                aria-pressed="false"
                title="Girar la llave para armar el sistema"
            >
                <span class="nuke__bombin"><span class="nuke__paleta"></span></span>
            </button>
            <span class="nuke__rotulo"><b>1</b> llave</span>
        </div>

        <div class="nuke__col nuke__col--modos">
            <label class="nuke__switch" title="Los reportes marcados en la lista">
                <input type="checkbox" wire:model.live="modoMarcados" />
                <span class="nuke__palanca" aria-hidden="true"></span>
                <span class="nuke__rotulo"><b>2</b> marcados</span>
            </label>
            <label class="nuke__switch" title="Todos los abiertos del denunciante escrito en el primer filtro de arriba">
                <input type="checkbox" wire:model.live="modoUsuario" />
                <span class="nuke__palanca" aria-hidden="true"></span>
                <span class="nuke__rotulo"><b>2</b> del denunciante</span>
            </label>
            <output
                class="nuke__display nuke__display--{{ $nukeModo }}"
                data-nuke-display
                data-cuenta="{{ $nukeCuenta }}"
            >
                @switch($nukeModo)
                    @case('todos')
                        TODOS · {{ $nukeCuenta }} abiertos
                        @break
                    @case('marcados')
                        {{ $nukeCuenta }} marcados
                        @break
                    @case('usuario')
                        {{ $nukeCuenta }} de {{ trim($reporter) }}
                        @break
                    @case('usuario-sin-nombre')
                        ↑ nombre exacto del denunciante en el primer filtro
                        @break
                    @default
                        elige modo
                @endswitch
            </output>
        </div>

        <div class="nuke__col nuke__col--seta" wire:ignore data-nuke-lado>
            <div class="nuke__caja">
                <span class="nuke__franjas" aria-hidden="true"></span>
                <button
                    type="button"
                    class="nuke__seta"
                    data-nuke-seta
                    disabled
                    aria-label="Mantener pulsado para descartar los reportes"
                >
                    <span class="nuke__anillo" aria-hidden="true"></span>
                    <span class="nuke__calavera" aria-hidden="true">☠</span>
                </button>
                <button
                    type="button"
                    class="nuke__tapa"
                    data-nuke-tapa
                    aria-label="Levantar la tapa de seguridad"
                >
                    <span>caution</span>
                </button>
            </div>
            <span class="nuke__rotulo"><b>3</b> tapa <b>4</b> mantener</span>
            <span class="nuke__estado" data-nuke-estado aria-live="polite">desarmado</span>
        </div>
    </div>
</section>
