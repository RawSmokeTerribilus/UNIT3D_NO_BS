@php
    // Las páginas /gaming/* corren bajo COEP require-corp + CSP estricto (img-src 'self'),
    // así que TMDB directo se bloquea. Reescribimos a nuestro proxy interno, que re-emite
    // la imagen con CORP same-origin desde nuestro propio origen.
    $alertTmdbToProxy = static function (string $url): string {
        if (preg_match('#^https://image\.tmdb\.org/t/p/([^/]+)/([A-Za-z0-9]+\.(?:jpg|jpeg|png|webp))$#', $url, $m)) {
            return route('authenticated_images.tmdb_proxy', ['size' => $m[1], 'file' => $m[2]]);
        }
        return $url;
    };

    $alertBackdropImages = array_map($alertTmdbToProxy, !empty($backdrops ?? [])
        ? $backdrops
        : [
            'https://image.tmdb.org/t/p/w780/5XBYN5Sb0yBvvodwr8fJa7iyuo2.jpg',
            'https://image.tmdb.org/t/p/w780/8ZTVqvKDQ8emSGUEMjsS4yHAwrp.jpg',
            'https://image.tmdb.org/t/p/w780/zEqyD0SBt6HL7W9JQoWwtd5Do1T.jpg',
        ]);
@endphp

    {{-- Estilos en components/_promo-banner.scss (antes, todos en linea: en
         movil no habia forma de pisarlos sin !important). --}}
    <section class="alert special-event-alert promo-banner"
             x-data="{
                images: @js($alertBackdropImages),
                currentIndex: 0,
                initBanner() {
                    setInterval(() => {
                        this.currentIndex = (this.currentIndex + 1) % this.images.length;
                    }, 12000);
                }
             }" 
             x-init="initBanner()" 
             x-cloak>

        <!-- Lateral Izquierdo (Enjaulado) -->
        <div class="promo-banner__side promo-banner__side--left">
            <img class="promo-banner__img" x-bind:src="images[currentIndex]" />
        </div>

        <!-- Centro: una promo por bloque, cada una con SU reloj (o sin reloj si no tiene fecha) -->
        @if (!empty($promos))
        <div class="promo-banner__center">
            @foreach ($promos as $promo)
                @php
                    $promoEstilo = [
                        'freeleech' => ['color' => '#bf00ff', 'sombra' => 'rgba(191,0,255,0.6)',   'texto' => '⚡⚡ '.__('common.freeleech_activated').' ⚡⚡'],
                        'openreg'   => ['color' => '#3498db', 'sombra' => 'rgba(52,152,219,0.4)',  'texto' => '🔓 '.__('common.openreg_activated').' 🔓'],
                        'doubleup'  => ['color' => '#f1c40f', 'sombra' => 'rgba(241,196,15,0.4)',  'texto' => '🚀 '.__('common.doubleup_activated').' 🚀'],
                    ][$promo['key']] ?? ['color' => '#fff', 'sombra' => 'rgba(255,255,255,0.4)', 'texto' => $promo['label']];
                @endphp

                <div class="promo-banner__promo">
                    <div
                        class="promo-banner__title"
                        style="--promo-color: {{ $promoEstilo['color'] }}; --promo-glow: {{ $promoEstilo['sombra'] }};"
                    >
                        {{ $promoEstilo['texto'] }}
                    </div>

                    @if ($promo['until'] !== null)
                        <div
                            class="promo-banner__clock"
                            x-data="promoTimer('{{ $promo['until'] }}')"
                            x-init="start()"
                            x-show="!terminado"
                        >
                            <!-- DÍAS: destacado en el color de la promo -->
                            <div class="promo-banner__unit promo-banner__unit--days">
                                <div class="promo-banner__num" x-text="days">00</div>
                                <div class="promo-banner__label">{{ __('common.day') }}</div>
                            </div>

                            <div class="promo-banner__sep">:</div>

                            <div class="promo-banner__unit">
                                <div class="promo-banner__num" x-text="hours">00</div>
                                <div class="promo-banner__label">{{ __('common.hour') }}</div>
                            </div>

                            <div class="promo-banner__sep">:</div>

                            <div class="promo-banner__unit">
                                <div class="promo-banner__num" x-text="minutes">00</div>
                                <div class="promo-banner__label">{{ __('common.minute') }}</div>
                            </div>

                            <!-- Separador reactivo -->
                            <div class="promo-banner__sep promo-banner__sep--tick" x-bind:style="tick ? 'opacity: 1' : 'opacity: 0.1'">:</div>

                            <div class="promo-banner__unit promo-banner__unit--seconds">
                                <div class="promo-banner__num" x-text="seconds">00</div>
                                <div class="promo-banner__label">{{ __('common.second') }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @else
            <!-- Sin promo: las dos imágenes laterales se funden en el centro (sus máscaras ya difuminan hacia dentro); espaciador transparente, sin neón -->
            <div class="promo-banner__spacer" aria-hidden="true"></div>
        @endif

        <!-- Lateral Derecho (Enjaulado) -->
        <div class="promo-banner__side promo-banner__side--right">
            <img class="promo-banner__img" x-bind:src="images[(currentIndex + 1) % images.length]" />
        </div>

    </section>

@if (!empty($promos))
    {{-- Un contador por promo. La fecha llega en ISO-8601 UTC desde
         PromoState::active(), no interpolada cruda como antes: '09/07/2026 3:00 PM
         EST' dependia de que el navegador supiera leer un formato de EE.UU. con
         zona horaria dentro, y cuando no podia se quedaba en NaN sin decir nada.

         Al llegar a cero el contador se oculta. NO llama al servidor: quien apaga
         la promo es `auto:expire-promos`, y lo hace como maximo diez minutos
         despues. El cliente aqui no manda. --}}
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        function promoTimer(until) {
            return {
                days: '00',
                hours: '00',
                minutes: '00',
                seconds: '00',
                tick: true,
                terminado: false,
                countdown: null,
                promoTime: Date.parse(until),
                start: function () {
                    if (Number.isNaN(this.promoTime)) {
                        this.terminado = true;
                        return;
                    }

                    this.refresh();
                    this.countdown = setInterval(() => this.refresh(), 1000);
                },
                refresh: function () {
                    const distance = this.promoTime - Date.now();

                    if (distance <= 0) {
                        clearInterval(this.countdown);
                        this.terminado = true;
                        return;
                    }

                    const total = Math.floor(distance / 1000);
                    this.days = this.pad(Math.floor(total / 86400));
                    this.hours = this.pad(Math.floor((total % 86400) / 3600));
                    this.minutes = this.pad(Math.floor((total % 3600) / 60));
                    this.seconds = this.pad(total % 60);
                    this.tick = total % 2 === 0;
                },
                pad: function (num) {
                    return String(num).padStart(2, '0');
                },
            };
        }
    </script>
@endif
