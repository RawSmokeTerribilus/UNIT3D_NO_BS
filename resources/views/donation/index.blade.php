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

    Varios métodos, 2026-10-12. Al entrar se abre «Antes de donar»: una lista de
    comprobación contra el «dono por si acaso» y la elección del método. El
    método elegido filtra los tramos (qué método cubre qué tramo lo decide la
    matriz de Configuración → Donaciones) y el contenido del diálogo de pago.
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

        // Las instrucciones de cada método se escriben en el panel como texto
        // plano: una línea en blanco separa párrafos. Se escapan SIEMPRE.
        $parrafos = fn (?string $texto) => $texto === null ? [] : preg_split('/\R\s*\R/', trim($texto));

        // Al volver de un error de validación se recupera el método y el tramo
        // para no hacer pasar otra vez por la lista de comprobación.
        $metodoPrevio = old('gateway_id') !== null ? (int) old('gateway_id') : null;
        $tramoPrevio = old('package_id') !== null ? (int) old('package_id') : null;
        $saltarAntes = $metodoPrevio !== null || session()->has('success');
    @endphp

    {{-- Estado en Alpine: componente `donar`, definido en el <script> del final
         (método elegido, lista de comprobación y la ruleta). Nada de comillas
         dobles dentro de los atributos. --}}
    <section
        x-data="donar({{ $metodoPrevio ?? 'null' }})"
        x-bind:class="{ 'donation-panel--de-lejos': deLejos }"
        x-init="
            @if ($saltarAntes)
                @if ($errors->any() && $tramoPrevio !== null)
                    $nextTick(() => $refs['dialog{{ $tramoPrevio }}']?.showModal());
                @endif
            @else
                $nextTick(() => $refs.antes.showModal());
            @endif
        "
        class="panelV2 donation-panel"
    >
        <h2 class="panel__heading">¡Sostén {{ config('other.title') }}!</h2>
        <div class="panel__body donation-panel__body">
            <div class="donation-manifesto">
                <p class="donation-manifesto__lead">
                    <strong>Aquí no se vende ratio.</strong>
                    {{ config('donation.description') }}
                </p>
                {{-- «El dinero va íntegro a mantener los cacharros encendidos» se
                     perdió al portar la maqueta del rediseño: se sustituyó el párrafo
                     entero por el del diseñador. Es la única frase del bloque que dice
                     A DÓNDE VA EL DINERO, que es lo primero que quiere saber alguien
                     antes de soltar 50 €. No quitarla. --}}
                <p>
                    Esto no es una tienda: el dinero va íntegro a mantener los cacharros
                    encendidos. Los premios de abajo los damos a manos llenas
                    precisamente porque no valen nada, y son los mismos en todos los
                    tramos. Lo único que cambia es cuánto duran y cuántos puntos caen.
                    Dona si te sobra; si no te sobra, no dones: el sitio seguirá aquí
                    igual.
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

            @if ($gateways->isEmpty())
                <p class="donation-notice">
                    <i class="{{ config('other.font-awesome') }} fa-circle-info"></i>
                    <span>Ahora mismo no hay ningún método de pago abierto. Vuelve otro día.</span>
                </p>
            @else
                <p class="donation-elegido">
                    <span x-show="metodo === null">Todavía no has elegido cómo pagar.</span>
                    @foreach ($gateways as $gateway)
                        <span x-show="metodo === {{ $gateway->id }}" x-cloak>
                            Pagando con
                            @if ($gateway->icono)
                                <i class="{{ $gateway->icono }}"></i>
                            @endif
                            <strong>{{ $gateway->name }}</strong>
                        </span>
                    @endforeach
                    <button
                        type="button"
                        class="form__button form__button--outlined"
                        x-on:click="$refs.antes.showModal()"
                    >
                        <span x-text="metodo === null ? 'Elegir método' : 'Cambiar'"></span>
                    </button>
                </p>
            @endif

            <div class="donation-packages">
                @foreach ($packages as $package)
                    @php
                        $esVitalicio = $package->donor_value === null;
                        // Lista de ids como literal JS: sólo enteros, sin comillas.
                        $cubre = '['.implode(',', $ofertas[$package->id] ?? []).']';
                    @endphp

                    <div @class(['donation-package__wrapper', 'donation-package__wrapper--lifetime' => $esVitalicio])>
                        <article
                            @class(['donation-package', 'donation-package--lifetime' => $esVitalicio])
                            x-bind:class="{ 'donation-package--no-disponible': metodo !== null && ! {{ $cubre }}.includes(metodo) }"
                        >
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
                                @if (($ofertas[$package->id] ?? []) === [])
                                    <p class="donation-package__no-disponible">No disponible ahora mismo</p>
                                @else
                                    <p
                                        class="donation-package__no-disponible"
                                        x-show="metodo !== null && ! {{ $cubre }}.includes(metodo)"
                                        x-cloak
                                    >
                                        No disponible con este método
                                    </p>
                                    <button
                                        type="button"
                                        @class([
                                            'form__button',
                                            'form__button--filled' => $esVitalicio,
                                            'form__button--outlined' => !$esVitalicio,
                                        ])
                                        x-show="metodo === null || {{ $cubre }}.includes(metodo)"
                                        x-on:click.stop="metodo === null ? $refs.antes.showModal() : $refs.dialog{{ $package->id }}.showModal()"
                                    >
                                        <i class="{{ config('other.font-awesome') }} fa-heart"></i>
                                        ¡Lo quiero!
                                    </button>
                                @endif
                            </footer>
                        </article>
                    </div>
                @endforeach
            </div>

            <p class="donation-notice text-warning">
                <i class="{{ config('other.font-awesome') }} fa-triangle-exclamation"></i>
                <span>
                    Cada donación la revisa una persona a mano, así que puede tardar hasta
                    48&nbsp;horas en aplicarse. No pasa nada, no se ha perdido. Si algo sale
                    mal (importe equivocado, sin justificante, el tramo que no era) no vuelvas
                    a pagar: escribe al staff por privado y lo arreglamos.
                </span>
            </p>
        </div>

        {{-- Antes de donar. Se abre al entrar. La lista no es decorativa: los
             métodos no se pueden pulsar hasta marcar las tres. --}}
        <dialog class="dialog donation-dialog donation-antes" x-ref="antes">
            <h2 class="dialog__heading">Antes de donar</h2>
            <div class="donation-dialog__step-body" style="grid-column: auto">
                @if ($porcentajeObjetivo >= 100)
                    <p class="donation-antes__objetivo donation-antes__objetivo--cubierto">
                        <strong>Este mes ya está cubierto.</strong>
                        Gracias de verdad. No hace falta que dones ahora: si te apetece
                        ayudar, guárdalo para un mes flojo.
                    </p>
                @else
                    <p class="donation-antes__objetivo">
                        Este mes llevamos el <strong>{{ $porcentajeObjetivo }}&nbsp;%</strong>
                        de lo que cuesta mantener esto encendido.
                    </p>
                @endif

                <ul class="donation-antes__checks">
                    <li>
                        <label>
                            <input type="checkbox" class="form__checkbox" x-model="checks[0]" />
                            <span>
                                Es una donación: no se devuelve y no compra nada. Los premios se
                                regalan igual a todo el mundo.
                            </span>
                        </label>
                    </li>
                    <li>
                        <label>
                            <input type="checkbox" class="form__checkbox" x-model="checks[1]" />
                            <span>
                                Me lo puedo permitir. Si este dinero te hace falta para otra
                                cosa, no dones: el sitio seguirá aquí igual.
                            </span>
                        </label>
                    </li>
                    <li>
                        <label>
                            <input type="checkbox" class="form__checkbox" x-model="checks[2]" />
                            <span>
                                Sé que la revisa una persona a mano y puede tardar hasta
                                48&nbsp;horas en aplicarse.
                            </span>
                        </label>
                    </li>
                </ul>

                @if ($gateways->isNotEmpty())
                    <p><strong>¿Cómo quieres pagar?</strong></p>
                    <ul
                        class="donation-metodos"
                        x-bind:class="{ 'donation-metodos--bloqueados': ! checks.every(Boolean) }"
                    >
                        @foreach ($gateways as $gateway)
                            @php
                                $tramosMetodo = $packages
                                    ->filter(fn ($p) => in_array($gateway->id, $ofertas[$p->id] ?? [], true))
                                    ->map(fn ($p) => $importe($p->cost))
                                    ->implode(' · ');
                            @endphp

                            <li>
                                <button
                                    type="button"
                                    class="donation-metodo"
                                    x-bind:disabled="! checks.every(Boolean)"
                                    x-on:click="metodo = {{ $gateway->id }}; $refs.antes.close()"
                                >
                                    @if ($gateway->icono)
                                        <i class="{{ $gateway->icono }}"></i>
                                    @endif
                                    <span class="donation-metodo__nombre">{{ $gateway->name }}</span>
                                    <span class="donation-metodo__tramos">{{ $tramosMetodo }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p>Ahora mismo no hay ningún método de pago abierto.</p>
                @endif

                <p class="donation-antes__pie">
                    <a href="{{ route('home.index') }}">Ahora no, gracias</a>
                    <button
                        type="button"
                        class="form__button form__button--outlined"
                        x-on:click="abrirRuleta()"
                    >
                        Sólo quiero mirar
                    </button>
                </p>
            </div>
        </dialog>

        {{-- La ruleta del mirón. La pidió la comunidad para donar; el dinero no
             se juega, así que vive en «Sólo quiero mirar», donde no hay dinero.
             Humor, no transacción: ninguna casilla da ni quita nada, y siempre se
             sale con Esc o con el enlace de abajo. Las casillas están en el JS. --}}
        <dialog
            class="dialog donation-dialog donation-ruleta"
            x-ref="ruleta"
            x-on:close="pararSonido()"
        >
            <h2 class="dialog__heading">La ruleta del mirón</h2>
            <div class="donation-dialog__step-body" style="grid-column: auto">
                <p>
                    🎲 La casa siempre gana. Y la casa dice que gires.
                </p>
                <div class="donation-ruleta__mesa">
                    <span class="donation-ruleta__aguja" aria-hidden="true"></span>
                    <div class="donation-ruleta__rueda" x-ref="rueda"></div>
                    <span class="donation-ruleta__eje" aria-hidden="true"></span>
                </div>
                <p class="donation-ruleta__pista">Dale al botón o arrástrala con fuerza.</p>
                <p class="donation-ruleta__resultado" aria-live="polite" x-text="resultado"></p>
                <p class="donation-antes__pie">
                    <a href="{{ route('home.index') }}">Me voy, que esto es una secta</a>
                    <span style="display: flex; gap: 0.6rem; flex-wrap: wrap">
                        <button
                            type="button"
                            class="form__button form__button--text donation-ruleta__sonido"
                            x-on:click="cambiarSonido()"
                            x-bind:aria-pressed="sonido"
                        >
                            <span x-text="sonido ? '🔊 Sonido' : '🔇 Sin sonido'"></span>
                        </button>
                        <button
                            type="button"
                            class="form__button form__button--filled"
                            x-bind:disabled="girando"
                            x-on:click="girar()"
                        >
                            <span x-text="girando ? 'Girando…' : (resultado ? 'Otra vez' : 'Girar')"></span>
                        </button>
                    </span>
                </p>
                <div class="donation-ruleta__lluvia" x-ref="lluvia" aria-hidden="true"></div>
            </div>
        </dialog>

        {{-- Los diálogos van DENTRO del `x-data` de la sección: `$refs` no cruza
             fuera del componente Alpine que los declara. --}}
        @foreach ($packages as $package)
            <dialog class="dialog donation-dialog" x-ref="dialog{{ $package->id }}">
                <h2 class="dialog__heading">
                    {{ $package->name }} · {{ $importe($package->cost) }}
                </h2>

                {{-- Un bloque por método que cubre este tramo; sólo existe en el
                     DOM el del método elegido (x-if, no x-show: así el `required`
                     de los formularios ocultos no bloquea el envío). --}}
                @foreach ($gateways->filter(fn ($g) => in_array($g->id, $ofertas[$package->id] ?? [], true)) as $gateway)
                    <template x-if="metodo === {{ $gateway->id }}">
                        <ol class="donation-dialog__steps">
                            <li class="donation-dialog__step">
                                <h3 class="donation-dialog__step-title">
                                    Primero paga con {{ $gateway->name }}
                                </h3>
                                <div class="donation-dialog__step-body">
                                    @foreach ($parrafos($gateway->instruccionesPara($importe($package->cost), auth()->user()->username)) as $parrafo)
                                        <p>{!! nl2br(e($parrafo)) !!}</p>
                                    @endforeach

                                    @if ($gateway->esEnlace())
                                        <a
                                            class="form__button form__button--filled"
                                            href="{{ $gateway->address }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <i class="{{ config('other.font-awesome') }} fa-arrow-up-right-from-square"></i>
                                            Abrir {{ $gateway->name }}
                                        </a>
                                    @else
                                        <div class="donation-direccion" x-data="{ copiado: false }">
                                            <input
                                                class="form__text"
                                                type="text"
                                                readonly
                                                value="{{ $gateway->address }}"
                                                x-ref="direccion"
                                                x-on:click="$el.select()"
                                                aria-label="Dirección de {{ $gateway->name }}"
                                            />
                                            <button
                                                type="button"
                                                class="form__button form__button--outlined"
                                                x-on:click="navigator.clipboard.writeText($refs.direccion.value).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) }).catch(() => $refs.direccion.select())"
                                            >
                                                <i class="{{ config('other.font-awesome') }} fa-copy"></i>
                                                <span x-text="copiado ? '¡Copiada!' : 'Copiar'"></span>
                                            </button>
                                        </div>
                                        @if (isset($qrs[$gateway->id]))
                                            <div class="donation-qr">{!! $qrs[$gateway->id] !!}</div>
                                        @endif
                                    @endif
                                </div>
                            </li>
                            <li class="donation-dialog__step">
                                <h3 class="donation-dialog__step-title">
                                    Después pega aquí el justificante
                                </h3>
                                <div class="donation-dialog__step-body">
                                    <form
                                        class="dialog__form"
                                        method="POST"
                                        action="{{ route('donations.store') }}"
                                        x-on:submit="$el.querySelector('[type=submit]').disabled = true"
                                    >
                                        @csrf
                                        <input type="hidden" name="package_id" value="{{ $package->id }}" />
                                        <input type="hidden" name="gateway_id" value="{{ $gateway->id }}" />
                                        @php
                                            // ¿Es este el formulario que volvió con error? El
                                            // aviso general del sitio queda DEBAJO del diálogo
                                            // (un <dialog> modal va en la capa superior), así
                                            // que el error se repite aquí dentro.
                                            $esElQueFallo = $errors->any() && $tramoPrevio === $package->id && $metodoPrevio === $gateway->id;
                                        @endphp

                                        @if ($esElQueFallo)
                                            <p class="donation-dialog__nota text-danger" role="alert">
                                                <i class="{{ config('other.font-awesome') }} fa-circle-exclamation"></i>
                                                <span>{{ $errors->first('transaction') ?: $errors->first('gateway_id') ?: $errors->first() }}</span>
                                            </p>
                                        @endif

                                        <p class="form__group">
                                            <input
                                                id="transaction-{{ $package->id }}-{{ $gateway->id }}"
                                                class="form__text"
                                                type="text"
                                                name="transaction"
                                                value="{{ $esElQueFallo ? old('transaction') : '' }}"
                                                required
                                                autocomplete="off"
                                                placeholder=" "
                                            />
                                            <label
                                                class="form__label form__label--floating"
                                                for="transaction-{{ $package->id }}-{{ $gateway->id }}"
                                            >
                                                {{ $gateway->etiqueta_justificante ?? 'Justificante del pago' }}
                                            </label>
                                        </p>
                                        <p class="donation-dialog__hint">
                                            Todavía no lo tienes hasta que pagues en el paso 1. Si
                                            ya pagaste y cerraste esta ventana, vuelve a abrir el
                                            tramo y pégalo: no pagues dos veces.
                                        </p>
                                        <p class="form__group form__group--horizontal">
                                            <button type="submit" class="form__button form__button--filled">
                                                Enviar
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
                    </template>
                @endforeach
            </dialog>
        @endforeach
    </section>

    @php
        // Destinos de la casilla 🎲: config/ruleta.php. Las rutas que no existan
        // en este entorno se saltan.
        $destinosRuleta = collect(config('ruleta.destinos', []))
            ->filter(fn (array $d) => Illuminate\Support\Facades\Route::has($d['ruta']))
            ->map(fn (array $d) => [
                'url'   => route($d['ruta'], $d['parametros'] ?? []),
                'texto' => $d['texto'],
            ])
            ->values()
            ->all();
    @endphp

    @vite('resources/js/vendor/ruleta.js')

    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('donar', (metodoInicial) => ({
                metodo: metodoInicial,
                checks: [false, false, false],

                // --- la ruleta del mirón ---
                rueda: null,
                girando: false,
                resultado: '',
                deLejos: false,
                sonido: true,
                audio: null,
                ultimoTic: 0,
                destinos: @json($destinosRuleta),
                casillas: [
                    { emoji: '👀', etiqueta: 'miras', texto: 'Puedes mirar.', accion: 'mirar' },
                    { emoji: '🚫', etiqueta: 'no miras', texto: 'No puedes mirar. La casa ha hablado.', accion: 'volver' },
                    { emoji: '🔭', etiqueta: 'de lejos', texto: 'Puedes mirar, pero de lejos.', accion: 'lejos' },
                    { emoji: '🎲', etiqueta: 'a otra parte', texto: 'Destino aleatorio. Agárrate.', accion: 'destino' },
                    { emoji: '🔁', etiqueta: 'otra vez', texto: 'La ruleta no se decide. Otra vez.', accion: 'otra' },
                    { emoji: '🆓', etiqueta: 'gratis', texto: 'Mirar es gratis. Como siempre, vaya.', accion: 'mirar' },
                ],
                // 18 casillas: cada resultado tres veces, en un orden en el que
                // nunca quedan dos iguales juntas (tampoco la última con la
                // primera). Los números son índices de `casillas`.
                segmentos: [0, 1, 2, 3, 4, 5, 2, 4, 0, 5, 1, 3, 4, 0, 3, 1, 5, 2],

                init() {
                    try {
                        this.sonido = localStorage.getItem('nobs.ruleta.sonido') !== 'no';
                    } catch (e) {}
                },

                abrirRuleta() {
                    this.$refs.antes.close();
                    this.resultado = '';
                    this.$refs.ruleta.showModal();
                    this.$nextTick(() => this.montarRueda());
                },

                montarRueda() {
                    if (this.rueda !== null || typeof window.SpinWheel !== 'function') {
                        return;
                    }

                    this.rueda = new window.SpinWheel(this.$refs.rueda, {
                        items: this.segmentos.map((c) => ({
                            label: this.casillas[c].emoji + ' ' + this.casillas[c].etiqueta,
                        })),
                        itemBackgroundColors: ['#8e1b2e', '#15151b'],
                        itemLabelColors: ['#f3ead2'],
                        itemLabelFont: 'system-ui, sans-serif',
                        itemLabelRadius: 0.93,
                        itemLabelRadiusMax: 0.24,
                        itemLabelFontSizeMax: 22,
                        lineColor: '#d4af37',
                        lineWidth: 1,
                        borderColor: '#d4af37',
                        borderWidth: 4,
                        radius: 0.98,
                        rotationResistance: -70,
                        isInteractive: true,
                        onCurrentIndexChange: () => this.tic(),
                        onSpin: () => {
                            this.girando = true;
                            this.resultado = '';
                            this.despertarAudio();
                        },
                        onRest: (evento) => {
                            if (!this.girando) {
                                return;
                            }

                            this.girando = false;
                            const casilla = this.segmentos[evento.currentIndex];
                            this.quitarDeLaBolsa('nobs.ruleta.bolsa', casilla);
                            this.aplicar(this.casillas[casilla]);
                        },
                    });
                },

                girar() {
                    if (this.girando || this.rueda === null) {
                        return;
                    }

                    // El resultado sale de una BOLSA barajada, no de tirar a
                    // pelo: con seis resultados el azar puro repite mucho y
                    // parece trucado. No vuelve a salir uno hasta que han salido
                    // todos. Luego se elige una de sus tres casillas al azar.
                    const casilla = this.sacarDeLaBolsa('nobs.ruleta.bolsa', this.casillas.length);
                    const opciones = this.segmentos
                        .map((c, i) => (c === casilla ? i : -1))
                        .filter((i) => i >= 0);
                    const indice = opciones[Math.floor(Math.random() * opciones.length)];
                    const quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                    // El ángulo final lo calculamos nosotros y no `spinToItem`:
                    // ese, sin centrar, elige CUALQUIER punto de la casilla,
                    // bordes incluidos, y caer en la raya da para discutir.
                    // Aquí cae dentro, con un 20 % de margen a cada lado.
                    // Con casillas iguales, la i ocupa [i·paso, (i+1)·paso)
                    // desde arriba; el puntero (arriba) marca la casilla que
                    // cumple (-rotación) mod 360 dentro de ese tramo.
                    const paso = 360 / this.segmentos.length;
                    const angulo = (indice + 0.5) * paso + (Math.random() - 0.5) * paso * 0.6;
                    const actual = this.rueda.rotation;
                    const vueltas = quieto ? 0 : 4 * 360;
                    const destino = actual + vueltas + (((-angulo - actual) % 360) + 360) % 360;

                    this.girando = true;
                    this.despertarAudio();
                    this.rueda.spinTo(destino, quieto ? 1 : 4200, (t) => 1 - Math.pow(1 - t, 4));
                },

                // --- bolsas sin repetición, guardadas en el navegador ---
                leerBolsa(clave) {
                    try {
                        return JSON.parse(localStorage.getItem(clave) || '[]');
                    } catch (e) {
                        return [];
                    }
                },

                guardarBolsa(clave, bolsa) {
                    try {
                        localStorage.setItem(clave, JSON.stringify(bolsa));
                    } catch (e) {}
                },

                sacarDeLaBolsa(clave, tamano) {
                    let bolsa = this.leerBolsa(clave).filter((i) => Number.isInteger(i) && i >= 0 && i < tamano);
                    let ultimo = null;

                    try {
                        ultimo = JSON.parse(localStorage.getItem(clave + '.ultimo'));
                    } catch (e) {}

                    // Al rellenar, el último de la tanda anterior no puede ser
                    // el primero de la nueva: si no, la costura repite.
                    let candidatos = bolsa;

                    if (bolsa.length === 0) {
                        bolsa = [...Array(tamano).keys()];
                        candidatos = tamano > 1 ? bolsa.filter((i) => i !== ultimo) : bolsa;
                    }

                    const sacado = candidatos[Math.floor(Math.random() * candidatos.length)];
                    this.guardarBolsa(clave, bolsa.filter((i) => i !== sacado));

                    try {
                        localStorage.setItem(clave + '.ultimo', JSON.stringify(sacado));
                    } catch (e) {}

                    return sacado;
                },

                // Cuando la rueda se gira a mano, el resultado lo decide la
                // física; aun así se quita de la bolsa para no repetirlo luego.
                quitarDeLaBolsa(clave, valor) {
                    const bolsa = this.leerBolsa(clave);

                    if (bolsa.includes(valor)) {
                        this.guardarBolsa(clave, bolsa.filter((i) => i !== valor));
                    }

                    try {
                        localStorage.setItem(clave + '.ultimo', JSON.stringify(valor));
                    } catch (e) {}
                },

                // --- sonido: un clic sintetizado por casilla, sin ficheros ---
                despertarAudio() {
                    if (!this.sonido || this.audio !== null) {
                        return;
                    }

                    try {
                        this.audio = new (window.AudioContext || window.webkitAudioContext)();
                    } catch (e) {
                        this.audio = null;
                    }
                },

                tic() {
                    if (!this.sonido || this.audio === null) {
                        return;
                    }

                    const ahora = this.audio.currentTime;

                    // A toda velocidad los clics se pegan en un zumbido: como
                    // mucho uno cada 35 ms.
                    if (ahora - this.ultimoTic < 0.035) {
                        return;
                    }

                    this.ultimoTic = ahora;
                    const oscilador = this.audio.createOscillator();
                    const volumen = this.audio.createGain();
                    oscilador.type = 'square';
                    oscilador.frequency.value = 1700;
                    volumen.gain.setValueAtTime(0.04, ahora);
                    volumen.gain.exponentialRampToValueAtTime(0.0001, ahora + 0.03);
                    oscilador.connect(volumen).connect(this.audio.destination);
                    oscilador.start(ahora);
                    oscilador.stop(ahora + 0.035);
                },

                cambiarSonido() {
                    this.sonido = !this.sonido;

                    try {
                        localStorage.setItem('nobs.ruleta.sonido', this.sonido ? 'si' : 'no');
                    } catch (e) {}

                    if (!this.sonido) {
                        this.pararSonido();
                    }
                },

                pararSonido() {
                    if (this.audio !== null) {
                        this.audio.close().catch(() => {});
                        this.audio = null;
                    }
                },

                // --- lluvia del emoji que ha salido ---
                lluvia(emoji) {
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        return;
                    }

                    for (let n = 0; n < 22; n++) {
                        const gota = document.createElement('span');
                        gota.className = 'donation-ruleta__gota';
                        gota.textContent = emoji;
                        gota.style.left = Math.random() * 100 + '%';
                        gota.style.fontSize = 2 + Math.random() * 2.5 + 'rem';
                        gota.style.animationDelay = Math.random() * 0.7 + 's';
                        this.$refs.lluvia.appendChild(gota);
                        setTimeout(() => gota.remove(), 3200);
                    }
                },

                aplicar(casilla) {
                    this.resultado = casilla.emoji + ' ' + casilla.texto;
                    this.lluvia(casilla.emoji);

                    switch (casilla.accion) {
                        case 'mirar':
                            setTimeout(() => this.$refs.ruleta.close(), 2200);
                            break;
                        case 'volver':
                            setTimeout(() => {
                                this.$refs.ruleta.close();
                                this.$refs.antes.showModal();
                            }, 2200);
                            break;
                        case 'lejos':
                            setTimeout(() => {
                                this.$refs.ruleta.close();
                                this.deLejos = true;
                                setTimeout(() => (this.deLejos = false), 7000);
                            }, 1800);
                            break;
                        case 'destino': {
                            if (this.destinos.length === 0) {
                                break;
                            }

                            const d = this.destinos[this.sacarDeLaBolsa('nobs.ruleta.destinos', this.destinos.length)];
                            this.resultado += ' ' + d.texto;
                            setTimeout(() => (window.location.href = d.url), 3000);
                            break;
                        }
                        case 'otra':
                            setTimeout(() => this.girar(), 1500);
                            break;
                    }
                },
            }));
        });
    </script>
@endsection
