{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0

    Pantalla de aviso de H&R (App\Http\Middleware\HitRunAvisoPendiente).
--}}
@extends('layout.with-main')

@section('title')
    <title>Aviso importante - {{ config('other.title') }}</title>
@endsection

@section('page', 'page__hitrun-aviso')

@section('main')
    <section class="panelV2 hra">
        <h2 class="panel__heading hra__heading">
            <i class="{{ config('other.font-awesome') }} fa-exclamation-triangle"></i>
            AVISO IMPORTANTE SOBRE TU CUENTA
        </h2>
        <div class="panel__body hra__body">
            <p class="hra__texto">
                SE TE HA ENVIADO UN MENSAJE PRIVADO CON UNA ADVERTENCIA SOBRE TUS HIT AND RUN.
            </p>
            <p class="hra__texto">
                TÓMATELO EN SERIO: SI EL {{ $aviso->plazo_hasta->timezone('Europe/Madrid')->format('d/m/Y') }}
                NO HAS EMPEZADO A ARREGLARLO, EL SISTEMA ELIMINARÁ TU CUENTA.
            </p>
            <p>En el mensaje tienes la lista de torrents y cómo se arregla. De todo se sale.</p>
            <form method="POST" action="{{ route('hitrun_aviso.ack') }}">
                @csrf
                <button type="submit" class="form__button form__button--filled hra__boton">
                    LO HE LEÍDO — IR AL MENSAJE
                </button>
            </form>
        </div>
    </section>

    <style>
        .hra { max-width: 72rem; margin: 3rem auto; }
        .hra__heading { background: linear-gradient(135deg, #7f1d1d 0%, #b91c1c 100%) !important; }
        .hra__body { text-align: center; }
        .hra__texto { font-size: 1.8rem; font-weight: 700; line-height: 1.5; text-transform: uppercase; }
        .hra__boton { background: #b91c1c !important; border-color: #b91c1c !important; color: #fff !important; font-weight: 700; }
    </style>
@endsection
