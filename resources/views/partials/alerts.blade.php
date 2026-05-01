@php
    $alertBackdropImages = !empty($backdrops ?? [])
        ? $backdrops
        : [
            'https://image.tmdb.org/t/p/w780/5XBYN5Sb0yBvvodwr8fJa7iyuo2.jpg',
            'https://image.tmdb.org/t/p/w780/8ZTVqvKDQ8emSGUEMjsS4yHAwrp.jpg',
            'https://image.tmdb.org/t/p/w780/zEqyD0SBt6HL7W9JQoWwtd5Do1T.jpg',
        ];
@endphp

@if (config('other.freeleech') == true || config('other.invite-only') == false || config('other.doubleup') == true)
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
             style="display: grid; grid-template-columns: 1fr auto 1fr; background: #111; overflow: hidden; border-radius: 8px; margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: 0 4px 15px rgba(0,0,0,0.5);"
             x-cloak>

        <!-- Lateral Izquierdo (Enjaulado) -->
        <div style="position: relative; width: 100%; height: 100%; -webkit-mask-image: linear-gradient(to right, black 30%, transparent 100%); mask-image: linear-gradient(to right, black 30%, transparent 100%);">
            <img x-bind:src="images[currentIndex]" 
                 style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; object-position: center left; pointer-events: none;" />
        </div>

        <!-- Centro: Instrumental del Reloj (Quien manda en la altura) -->
        <div x-data="timer()" x-init="start()" style="padding: 1.5rem; display: flex; flex-direction: column; align-items: center; gap: 1.2rem; z-index: 10;">
            
            <div style="display: flex; gap: 20px; font-weight: 800; font-size: 1.2rem; text-transform: uppercase; letter-spacing: 1px;">
                @if (config('other.freeleech') == true)
                    <span style="color: #2ecc71; text-shadow: 0 0 8px rgba(46,204,113,0.4);">⚡ {{ __('common.freeleech_activated') }} ⚡</span>
                @endif

                @if (config('other.invite-only') == false)
                    <span style="color: #3498db; text-shadow: 0 0 8px rgba(52,152,219,0.4);">🔓 {{ __('common.openreg_activated') }} 🔓</span>
                @endif

                @if (config('other.doubleup') == true)
                    <span style="color: #f1c40f; text-shadow: 0 0 8px rgba(241,196,15,0.4);">🚀 {{ __('common.doubleup_activated') }} 🚀</span>
                @endif
            </div>

            <div style="display: flex; align-items: center; gap: 10px; font-family: monospace;">
                <!-- Días -->
                <div style="background: rgba(0,0,0,0.5); padding: 10px 15px; border-radius: 6px; text-align: center; min-width: 70px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(4px);">
                    <div style="font-size: 1.8rem; font-weight: 900; line-height: 1; color: #fff;" x-text="String(days).padStart(2, '0')">00</div>
                    <div style="font-size: 0.7rem; text-transform: uppercase; opacity: 0.6; margin-top: 5px; color: #fff;">{{ __('common.day') }}</div>
                </div>

                <div style="font-size: 1.5rem; font-weight: bold; opacity: 0.5; color: #fff;">:</div>

                <!-- Horas -->
                <div style="background: rgba(0,0,0,0.5); padding: 10px 15px; border-radius: 6px; text-align: center; min-width: 70px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(4px);">
                    <div style="font-size: 1.8rem; font-weight: 900; line-height: 1; color: #fff;" x-text="String(hours).padStart(2, '0')">00</div>
                    <div style="font-size: 0.7rem; text-transform: uppercase; opacity: 0.6; margin-top: 5px; color: #fff;">{{ __('common.hour') }}</div>
                </div>

                <div style="font-size: 1.5rem; font-weight: bold; opacity: 0.5; color: #fff;">:</div>

                <!-- Minutos -->
                <div style="background: rgba(0,0,0,0.5); padding: 10px 15px; border-radius: 6px; text-align: center; min-width: 70px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(4px);">
                    <div style="font-size: 1.8rem; font-weight: 900; line-height: 1; color: #fff;" x-text="String(minutes).padStart(2, '0')">00</div>
                    <div style="font-size: 0.7rem; text-transform: uppercase; opacity: 0.6; margin-top: 5px; color: #fff;">{{ __('common.minute') }}</div>
                </div>

                <!-- Separador reactivo -->
                <div style="font-size: 1.5rem; font-weight: bold; transition: opacity 0.2s; color: #fff;" x-bind:style="seconds % 2 === 0 ? 'opacity: 1' : 'opacity: 0.2'">:</div>

                <!-- Segundos -->
                <div style="background: rgba(231,76,60,0.2); padding: 10px 15px; border-radius: 6px; text-align: center; min-width: 70px; border: 1px solid rgba(231,76,60,0.4); backdrop-filter: blur(4px);">
                    <div style="font-size: 1.8rem; font-weight: 900; line-height: 1; color: #e74c3c;" x-text="String(seconds).padStart(2, '0')">00</div>
                    <div style="font-size: 0.7rem; text-transform: uppercase; opacity: 0.8; margin-top: 5px; color: #e74c3c;">{{ __('common.second') }}</div>
                </div>
            </div>
        </div>

        <!-- Lateral Derecho (Enjaulado, cargando imagen asimétrica) -->
        <div style="position: relative; width: 100%; height: 100%; -webkit-mask-image: linear-gradient(to left, black 30%, transparent 100%); mask-image: linear-gradient(to left, black 30%, transparent 100%);">
            <img x-bind:src="images[(currentIndex + 1) % images.length]" 
                 style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; object-position: center right; pointer-events: none;" />
        </div>

    </section>
@endif
