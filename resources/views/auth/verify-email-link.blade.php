{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
    <head>
        <meta charset="UTF-8" />
        <title>{{ __('auth.verify-email') }} - {{ config('other.title') }}</title>
        @section('meta')
        <meta
            name="description"
            content="{{ __('auth.login-now-on') }} {{ config('other.title') }} . {{ __('auth.not-a-member') }}"
        />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta property="og:title" content="{{ __('auth.verify-email') }}" />
        <meta property="og:site_name" content="{{ config('other.title') }}" />
        <meta property="og:type" content="website" />
        <meta property="og:image" content="{{ url('/img/og.png') }}" />
        <meta property="og:description" content="{{ config('unit3d.powered-by') }}" />
        <meta property="og:url" content="{{ url('/') }}" />
        <meta property="og:locale" content="{{ config('app.locale') }}" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        @show
        <link rel="shortcut icon" href="{{ url('/favicon.ico') }}" type="image/x-icon" />
        <link rel="icon" href="{{ url('/favicon.ico') }}" type="image/x-icon" />
        @vite('resources/sass/pages/_auth.scss')
    </head>
    <body>
        <main>
            <section class="auth-form">
                <form
                    class="auth-form__form"
                    method="POST"
                    action="{{ $confirmUrl }}"
                >
                    @csrf
                    <a class="auth-form__branding" href="{{ route('login') }}">
                        <img src="{{ url('/img/logo_main.png') }}" alt="{{ config('other.title') }}" class="auth-form__logo">
                    </a>
                    <ul class="auth-form__important-infos">
                        <li class="auth-form__important-info">Confirmar verificación de correo</li>
                        <li class="auth-form__important-info">
                            Hemos recibido un enlace de activación válido para <strong>{{ $user->email }}</strong>.
                        </li>
                        <li class="auth-form__important-info">
                            Este paso extra evita que escáneres de correo o visores de previsualización activen cuentas automáticamente.
                        </li>
                    </ul>

                    @if (Session::has('errors'))
                        <ul class="auth-form__errors">
                            @foreach ($errors->all() as $error)
                                <li class="auth-form__error">{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <button class="auth-form__primary-button">Verificar mi cuenta</button>
                </form>
            </section>
        </main>
    </body>
</html>
