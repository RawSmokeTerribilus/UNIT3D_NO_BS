{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
@extends('layout.with-main-and-sidebar')

@section('title')
    <title>Configuración Global - {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li>
        <a href="{{ route('staff.dashboard.index') }}">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        Configuración Global
    </li>
@endsection

@section('main')
    @livewire('staff.config-manager')
@endsection
