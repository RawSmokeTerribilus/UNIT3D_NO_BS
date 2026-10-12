@extends('layout.with-main')

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('staff.dashboard.index') }}" class="breadcrumb__link">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumbV2">
        <a href="{{ route('staff.gateways.index') }}" class="breadcrumb__link">Métodos de pago</a>
    </li>
    <li class="breadcrumb--active">Nuevo método</li>
@endsection

@section('page', 'page__staff-donation-gateway--create')

@section('main')
    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">Nuevo método de pago</h2>
        </header>
        <div class="panel__body">
            <form class="form" role="form" method="POST" action="{{ route('staff.gateways.store') }}">
                @csrf
                @include('Staff.donation-gateway._campos', ['gateway' => null])
                <p class="form__group">
                    <button type="submit" class="form__button form__button--filled">Crear</button>
                </p>
            </form>
        </div>
    </section>
@endsection
