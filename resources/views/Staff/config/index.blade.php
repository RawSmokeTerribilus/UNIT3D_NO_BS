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
