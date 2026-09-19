@extends('layout.with-main')

@section('title')
    <title>Reports - {{ __('staff.staff-dashboard') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Reports - {{ __('staff.staff-dashboard') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('staff.dashboard.index') }}" class="breadcrumb__link">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('staff.reports-log') }}
    </li>
@endsection

@section('page', 'page__staff-report--index')

@section('main')
    {{-- NOBS: modulo nuke (livewire/includes/_nuke-panel). Fuera del componente para que Livewire no los re-renderice. --}}
    <link rel="stylesheet" href="{{ asset('css/nuke-panel.css') }}?v=1" />
    @livewire('report-search')
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}" src="{{ asset('js/nuke-panel.js') }}?v=1"></script>
@endsection
