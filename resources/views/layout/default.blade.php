{{--
    MODIFICADO PARA NOBS

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
    Se distribuye bajo la misma licencia, GNU AGPL v3.0.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('partials.head')
    </head>
    <body @class([
        'fx-scanlines' => auth()->user()->settings->fx_scanlines,
        'fx-glow' => true, // accent glow always on (baseline neon); color from theme_accent --accent

        'fx-grid' => auth()->user()->settings->fx_grid,
        'fx-vignette' => auth()->user()->settings->fx_vignette,
    ])>
        {{-- NOBS Lateral FX (PoC): animated canvas behind content, clipped to the empty sides of <main> --}}
        <style>
            #nobs-fx { position: fixed; inset: 0; width: 100vw; height: 100vh; z-index: -1; pointer-events: none; display: block; }
        </style>
        @if (auth()->user()->settings->lateral_fx !== 'off')
            <canvas
                id="nobs-fx"
                aria-hidden="true"
                data-fx="{{ auth()->user()->settings->lateral_fx }}"
                data-speed="{{ auth()->user()->settings->lateral_fx_speed }}"
                data-density="{{ auth()->user()->settings->lateral_fx_density }}"
                data-hue="{{ auth()->user()->settings->lateral_fx_hue }}"
            ></canvas>
            <script src="{{ asset('js/nobs-fx.js') }}?v=5" defer></script>
        @endif

        {{-- NOBS Perspective Grid: animated floor+ceiling neon grid, gated on the
             fx-grid body class; hue reuses the lateral FX neon tone. Self-gates +
             self-clears (MutationObserver) when the effect is toggled off. --}}
        @if (auth()->user()->settings->fx_grid)
            <canvas
                id="nobs-grid"
                aria-hidden="true"
                data-hue="{{ auth()->user()->settings->lateral_fx_hue }}"
            ></canvas>
            <script src="{{ asset('js/nobs-grid.js') }}?v=1" defer></script>
        @endif

        <div class="fx-overlay" aria-hidden="true"></div>
        <div class="alerts">
            @include('cookie-consent::index')
            @include('partials.alerts')
        </div>
        <header>
            @include('partials.top-nav')
            <nav class="secondary-nav">
                <ol class="breadcrumbsV2">
                    @if (! Route::is('home.index'))
                        <li class="breadcrumbV2">
                            <a class="breadcrumb__link" href="{{ route('home.index') }}">
                                <i class="{{ config('other.font-awesome') }} fa-home"></i>
                            </a>
                        </li>
                    @endif

                    @yield('breadcrumbs')
                </ol>
                <ul class="nav-tabsV2">
                    @yield('nav-tabs')
                </ul>
            </nav>
            @if (Session::has('achievement'))
                @include('partials.achievement-modal')
            @endif

            @if (Session::has('errors'))
                <div id="ERROR_COPY" style="display: none">
                    @foreach ($errors->getBags() as $bag)
                        @foreach ($bag->getMessages() as $errors)
                            @foreach ($errors as $error)
                                {{ $error }}
                                <br />
                            @endforeach
                        @endforeach
                    @endforeach
                </div>
            @endif
        </header>
        <main class="@yield('page')">
            @yield('content')
        </main>
        @include('partials.footer')

        @vite('resources/js/app.js')

        @if (config('other.freeleech') == true || config('other.invite-only') == false || config('other.doubleup') == true)
            <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
                function timer() {
                    return {
                        seconds: '00',
                        minutes: '00',
                        hours: '00',
                        days: '00',
                        distance: 0,
                        countdown: null,
                        promoTime: new Date('{{ config('other.freeleech_until') }}').getTime(),
                        now: new Date().getTime(),
                        start: function () {
                            this.countdown = setInterval(() => {
                                // Calculate time
                                this.now = new Date().getTime();
                                this.distance = this.promoTime - this.now;
                                // Set times
                                this.days = this.padNum(
                                    Math.floor(this.distance / (1000 * 60 * 60 * 24)),
                                );
                                this.hours = this.padNum(
                                    Math.floor(
                                        (this.distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60),
                                    ),
                                );
                                this.minutes = this.padNum(
                                    Math.floor((this.distance % (1000 * 60 * 60)) / (1000 * 60)),
                                );
                                this.seconds = this.padNum(
                                    Math.floor((this.distance % (1000 * 60)) / 1000),
                                );
                                // Stop
                                if (this.distance < 0) {
                                    clearInterval(this.countdown);
                                    this.days = '00';
                                    this.hours = '00';
                                    this.minutes = '00';
                                    this.seconds = '00';
                                }
                            }, 100);
                        },
                        padNum: function (num) {
                            let zero = '';
                            for (let i = 0; i < 2; i++) {
                                zero += '0';
                            }
                            return (zero + num).slice(-2);
                        },
                    };
                }
            </script>
        @endif

        @foreach (['warning', 'success', 'info'] as $key)
            @if (Session::has($key))
                <script
                    nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}"
                    type="module"
                >
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                    });

                    Toast.fire({
                        icon: '{{ $key }}',
                        title: {!! json_encode(Session::get($key)) !!},
                    });
                </script>
            @endif
        @endforeach

        @if (Session::has('errors'))
            <script
                nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}"
                type="module"
            >
                Swal.fire({
                    title: '<strong style=" color: rgb(17,17,17);">Error</strong>',
                    icon: 'error',
                    html: document.getElementById('ERROR_COPY').innerHTML,
                    showCloseButton: true,
                    willOpen: function (el) {
                        el.querySelector('textarea').remove();
                    },
                });
            </script>
        @endif

        <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
            window.addEventListener('success', (event) => {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                });

                Toast.fire({
                    icon: 'success',
                    title: event.detail?.message ?? event.message ?? 'Error desconocido',
                });
            });
        </script>

        <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
            window.addEventListener('error', (event) => {
                Swal.fire({
                    title: '<strong style=" color: rgb(17,17,17);">Error</strong>',
                    icon: 'error',
                    html: event.detail?.message ?? event.message ?? 'Error desconocido',
                    showCloseButton: true,
                });
            });
        </script>

        <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
            document.addEventListener('alpine:init', () => {
                Alpine.data('confirmation', () => ({
                    confirmAction() {
                        Swal.fire({
                            title: '¿Estás seguro?',
                            text: atob(this.$el.dataset.b64DeletionMessage),
                            icon: 'warning',
                            showConfirmButton: true,
                            showCancelButton: true,
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.$root.submit();
                            }
                        });
                    },
                }));
            });
        </script>

        @yield('javascripts')
        @yield('scripts')
        @livewireScriptConfig(['nonce' => HDVinnie\SecureHeaders\SecureHeaders::nonce()])
    </body>
</html>
