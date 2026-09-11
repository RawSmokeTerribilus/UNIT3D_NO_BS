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

    <section class="alert special-event-alert"
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
             style="display: grid; grid-template-columns: 1fr auto 1fr; align-content: stretch; min-height: 148px; align-items: center; background: #0a0a0a; overflow: hidden; border-radius: 8px; margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: 0 4px 20px rgba(0,0,0,0.7);"
             x-cloak>

        <!-- Lateral Izquierdo (Enjaulado) -->
        <div style="position: relative; width: 100%; height: 100%; -webkit-mask-image: linear-gradient(to right, black 30%, transparent 100%); mask-image: linear-gradient(to right, black 30%, transparent 100%);">
            <img x-bind:src="images[currentIndex]" 
                 style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; object-position: center left; pointer-events: none; opacity: 0.8;" />
        </div>

        <!-- Centro: una promo por bloque, cada una con SU reloj (o sin reloj si no tiene fecha) -->
        @if (!empty($promos))
        <div style="padding: 1.8rem; display: flex; flex-direction: column; align-items: center; gap: 1.4rem; z-index: 10;">
            @foreach ($promos as $promo)
                @php
                    $promoEstilo = [
                        'freeleech' => ['color' => '#bf00ff', 'sombra' => 'rgba(191,0,255,0.6)',   'texto' => '⚡⚡ '.__('common.freeleech_activated').' ⚡⚡'],
                        'openreg'   => ['color' => '#3498db', 'sombra' => 'rgba(52,152,219,0.4)',  'texto' => '🔓 '.__('common.openreg_activated').' 🔓'],
                        'doubleup'  => ['color' => '#f1c40f', 'sombra' => 'rgba(241,196,15,0.4)',  'texto' => '🚀 '.__('common.doubleup_activated').' 🚀'],
                    ][$promo['key']] ?? ['color' => '#fff', 'sombra' => 'rgba(255,255,255,0.4)', 'texto' => $promo['label']];
                @endphp

                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.8rem;">
                    <div style="font-weight: 900; font-size: 1.8rem; text-transform: uppercase; letter-spacing: 2px; text-align: center; color: {{ $promoEstilo['color'] }}; text-shadow: 0 0 15px {{ $promoEstilo['sombra'] }};">
                        {{ $promoEstilo['texto'] }}
                    </div>

                    @if ($promo['until'] !== null)
                        <div
                            x-data="promoTimer('{{ $promo['until'] }}')"
                            x-init="start()"
                            x-show="!terminado"
                            style="display: flex; align-items: center; gap: 12px; font-family: 'JetBrains Mono', monospace;"
                        >
                            <!-- DÍAS: destacado en el color de la promo -->
                            <div style="background: rgba(147, 51, 234, 0.2); padding: 12px 18px; border-radius: 8px; text-align: center; min-width: 85px; border: 1px solid rgba(147, 51, 234, 0.4); backdrop-filter: blur(8px);">
                                <div style="font-size: 2.2rem; font-weight: 900; line-height: 1; color: #d8b4fe;" x-text="days">00</div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; opacity: 0.8; margin-top: 5px; color: #d8b4fe; font-weight: bold;">{{ __('common.day') }}</div>
                            </div>

                            <div style="font-size: 1.5rem; font-weight: bold; opacity: 0.3; color: #fff;">:</div>

                            <div style="background: rgba(255,255,255,0.05); padding: 10px 15px; border-radius: 6px; text-align: center; min-width: 75px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(4px);">
                                <div style="font-size: 1.8rem; font-weight: 800; line-height: 1; color: #fff;" x-text="hours">00</div>
                                <div style="font-size: 0.7rem; text-transform: uppercase; opacity: 0.5; margin-top: 5px; color: #fff;">{{ __('common.hour') }}</div>
                            </div>

                            <div style="font-size: 1.5rem; font-weight: bold; opacity: 0.3; color: #fff;">:</div>

                            <div style="background: rgba(255,255,255,0.05); padding: 10px 15px; border-radius: 6px; text-align: center; min-width: 75px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(4px);">
                                <div style="font-size: 1.8rem; font-weight: 800; line-height: 1; color: #fff;" x-text="minutes">00</div>
                                <div style="font-size: 0.7rem; text-transform: uppercase; opacity: 0.5; margin-top: 5px; color: #fff;">{{ __('common.minute') }}</div>
                            </div>

                            <!-- Separador reactivo -->
                            <div style="font-size: 1.5rem; font-weight: bold; transition: opacity 0.2s; color: #fff;" x-bind:style="tick ? 'opacity: 1' : 'opacity: 0.1'">:</div>

                            <div style="background: rgba(255,255,255,0.03); padding: 10px 15px; border-radius: 6px; text-align: center; min-width: 75px; border: 1px solid rgba(255,255,255,0.05); backdrop-filter: blur(4px);">
                                <div style="font-size: 1.8rem; font-weight: 800; line-height: 1; color: #666;" x-text="seconds">00</div>
                                <div style="font-size: 0.7rem; text-transform: uppercase; opacity: 0.4; margin-top: 5px; color: #fff;">{{ __('common.second') }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @else
            <!-- Sin promo: las dos imágenes laterales se funden en el centro (sus máscaras ya difuminan hacia dentro); espaciador transparente, sin neón -->
            <div aria-hidden="true" style="width: 80px; align-self: stretch;"></div>
        @endif

        <!-- Lateral Derecho (Enjaulado) -->
        <div style="position: relative; width: 100%; height: 100%; -webkit-mask-image: linear-gradient(to left, black 30%, transparent 100%); mask-image: linear-gradient(to left, black 30%, transparent 100%);">
            <img x-bind:src="images[(currentIndex + 1) % images.length]" 
                 style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; object-position: center right; pointer-events: none; opacity: 0.8;" />
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
