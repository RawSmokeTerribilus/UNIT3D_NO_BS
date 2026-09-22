@extends('layout.with-main-and-sidebar')

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('users.show', ['user' => $user]) }}" class="breadcrumb__link">
            {{ $user->username }}
        </a>
    </li>
    <li class="breadcrumbV2">
        <a href="{{ route('users.earnings.index', ['user' => $user]) }}" class="breadcrumb__link">
            {{ __('bon.bonus') }} {{ __('bon.points') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('bon.store') }}
    </li>
@endsection

@section('nav-tabs')
    @include('user.buttons.user')
@endsection

@section('page', 'page__user-transaction--create')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('bon.exchange') }}</h2>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('bon.item') }}</th>
                        <th>Cost</th>
                        <th>{{ __('bon.exchange') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php($requiredThanksRatio = $user->requiredThanksRatioForBonExchange($item))
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->cost }}</td>
                            <td>
                                @if ($item->personal_freeleech && $activefl)
                                    <button disabled class="form__button form__button--filled">
                                        {{ __('bon.activated') }}!
                                    </button>
                                @elseif ($requiredThanksRatio > 0 && ! $user->hasRequiredThanksRatio($requiredThanksRatio))
                                    <button disabled class="form__button form__button--filled">
                                        {{ __('user.thanks-ratio-required', ['ratio' => number_format($requiredThanksRatio, 2, '.', '')]) }}
                                    </button>
                                @elseif ($item->upload && config('other.bon.max-buffer-to-buy-upload') !== null && $user->uploaded - $user->downloaded > config('other.bon.max-buffer-to-buy-upload'))
                                    <button disabled class="form__button form__button--filled">
                                        Too much buffer!
                                    </button>
                                @else
                                    <form
                                        method="POST"
                                        action="{{ route('users.transactions.store', ['user' => $user]) }}"
                                    >
                                        @csrf
                                        <button class="form__button form__button--filled">
                                            {{ __('bon.exchange') }}
                                        </button>
                                        <input
                                            type="hidden"
                                            name="exchange"
                                            value="{{ $item->id }}"
                                        />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

@section('sidebar')
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('bon.your-points') }}</h2>
        <div class="panel__body">{{ $bon }}</div>
    </section>
    @if ($thanksEnabled)
        @php($thanksMet = $completedDownloads > 0 && $thanksNeeded === 0)
        <section class="panelV2">
            <h2 class="panel__heading">
                {{ $thanksMet ? __('bon.thanks-panel-title-open') : __('bon.thanks-panel-title') }}
            </h2>
            <dl class="key-value">
                <div class="key-value__group">
                    <dt>{{ __('bon.thanks-your-ratio') }}</dt>
                    <dd>{{ number_format($thanksRatio, 2, '.', '') }}</dd>
                </div>
                <div class="key-value__group">
                    <dt>{{ __('bon.thanks-needed') }}</dt>
                    <dd>{{ number_format($thanksRequired, 2, '.', '') }}</dd>
                </div>
            </dl>
            <div class="panel__body">
                @if ($completedDownloads === 0)
                    {{ __('bon.thanks-no-downloads') }}
                @else
                    {{ __('bon.thanks-progress', ['thanked' => $thankedDownloads, 'completed' => $completedDownloads]) }}
                    @if ($thanksMet)
                        {{ __('bon.thanks-open') }}
                    @else
                        {{ trans_choice('bon.thanks-missing', $thanksNeeded, ['count' => $thanksNeeded]) }}
                    @endif
                @endif
            </div>
            <div class="panel__body">{{ __('bon.thanks-comment-bonus') }}</div>
            <div class="panel__body">{{ __('bon.thanks-not-upload-ratio') }}</div>
        </section>
    @endif
    <section class="panelV2">
        <h2 class="panel__heading">{{ __('bon.no-refund') }}</h2>
        <div class="panel__body">{{ __('bon.exchange-warning') }}</div>
    </section>
@endsection
