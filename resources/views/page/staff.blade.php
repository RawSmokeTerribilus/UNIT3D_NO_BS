@extends('layout.with-main-and-sidebar')

@section('breadcrumbs')
    <li class="breadcrumb--active">{{ config('other.title') }} {{ __('common.staff') }}</li>
@endsection

@section('page', 'page__staff--index')

@section('main')
    @foreach ($staff as $group)
        <section class="panelV2">
            <h2 class="panel__heading">{{ $group->name }}</h2>
            <div class="panel__body user-card-wrapper">
                @foreach ($group->users as $user)
                    {{-- El ORDEN importa. `background` es el atajo: al no llevar
                         componente de color, resetea `background-color` a
                         transparent y se come cualquier declaracion anterior.
                         Por eso el color va DESPUES del atajo, no antes.
                         `groups.effect` dejo de ser una url pelada y pasó a ser
                         un atajo completo (`url(...) center/auto 100% repeat-x`),
                         que como `background-image` seria invalido — de ahi el
                         atajo. Sin el color detras, `.user-card__username` (que
                         es `color: #fff` fijo) queda blanco sobre blanco en los
                         temas claros. --}}
                    <a
                        href="{{ route('users.show', ['user' => $user]) }}"
                        class="user-card"
                        style="
                            background: {{ $group->effect }};
                            background-color: {{ $user->group->color }};
                        "
                    >
                        <h3 class="user-card__username">
                            {{ $user->username }}
                        </h3>
                        <i class="fal {{ $user->group->icon }} user-card__icon"></i>
                        @if ($user->title !== null)
                            <p class="user-card__title">
                                {{ __('page.staff-title') }}: {{ $user->title }}
                            </p>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection

@section('sidebar')
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('common.info') }}</h2>
        <div class="panel__body">Please contact staff via the helpdesk for account support.</div>
        <div class="panel__body">
            <p class="form__group form__group--horizontal">
                <a
                    class="form__button form__button--filled form__button--centered"
                    href="{{ route('tickets.index') }}"
                >
                    Open helpdesk
                </a>
            </p>
        </div>
    </section>
@endsection
