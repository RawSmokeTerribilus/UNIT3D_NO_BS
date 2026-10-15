@extends('layout.with-main')

@section('title')
    <title>{{ __('torrent.peers') }} - {{ $torrent->name }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="{{ __('torrent.peers') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('torrents.index') }}" class="breadcrumb__link">
            {{ __('torrent.torrents') }}
        </a>
    </li>
    <li class="breadcrumbV2">
        <a href="{{ route('torrents.show', ['id' => $torrent->id]) }}" class="breadcrumb__link">
            {{ $torrent->name }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('torrent.peers') }}
    </li>
@endsection

@section('nav-tabs')
    <li class="nav-tab--active">
        <a class="nav-tab--active__link" href="{{ route('peers', ['id' => $torrent]) }}">
            {{ __('torrent.peers') }}
        </a>
    </li>
    <li class="nav-tabV2">
        <a class="nav-tab__link" href="{{ route('history', ['id' => $torrent]) }}">
            {{ __('torrent.history') }}
        </a>
    </li>
    @if (config('announce.external_tracker.is_enabled') && auth()->user()->group->is_modo)
        <li class="nav-tabV2">
            <a
                class="nav-tab__link"
                href="{{ route('torrents.external_tracker', ['id' => $torrent]) }}"
            >
                External tracker
            </a>
        </li>
    @endif
@endsection

@section('page', 'page__torrent-peer--index')

@section('main')
    @if ($isStaff)
        <section class="panelV2">
            <h2 class="panel__heading">Diagnóstico por socio</h2>
            <div class="panel__body">
                @if (config('announce.external_tracker.is_enabled') && ! $hasTracker)
                    <p class="text-danger">
                        No se ha podido leer la memoria del announce: los estados de abajo
                        vienen sólo de la base.
                    </p>
                @endif

                @forelse ($diagnosis as $entry)
                    <p>
                        <x-user-tag :user="$entry['user']" :anon="false" />
                        : {{ $entry['active'] }} activo(s)
                        @if ($entry['ghosts'] > 0)
                            · <span class="text-orange">{{ $entry['ghosts'] }} fantasma(s)</span>
                        @endif
                        @if ($entry['stopped'] > 0)
                            · <span class="text-muted">{{ $entry['stopped'] }} parado(s)</span>
                        @endif
                        @if (! $hasTracker && $entry['inactive'] > 0)
                            · <span class="text-orange">{{ $entry['inactive'] }} inactivo(s)</span>
                        @endif
                        @if ($entry['restarts']->count() >= 2)
                            <br />
                            → {{ $entry['restarts']->count() }} de un solo anuncio
                            ({{ $entry['restarts']->map(fn ($date) => $date->format('d/m H:i'))->implode(', ') }} UTC):
                            el cliente se reinició o se volvió a añadir el torrent sin mandar «stopped».
                        @endif
                        @if ($entry['active'] > 1)
                            <br />
                            → {{ $entry['active'] }} pares activos a la vez: dos clientes, o el mismo
                            con varias IP o puertos.
                        @endif
                        @if ($entry['mismatch'] > 0)
                            <br />
                            → {{ $entry['mismatch'] }} activo(s) en la web que el announce ya da por
                            caducados; se igualan en el próximo «Limpiar peers viejos» (cada hora).
                        @endif
                        @if ($entry['agents']->count() > 1)
                            <br />
                            → clientes distintos: {{ $entry['agents']->implode(', ') }}
                        @endif
                    </p>
                @empty
                    <p class="text-muted">Nadie con más de un par ni con pares inactivos.</p>
                @endforelse
            </div>
        </section>
    @endif

    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">
                {{ __('torrent.torrent') }} {{ __('torrent.peers') }} ({{ $activePeers->count() }})
            </h2>
            @if ($hasOwnPeers)
                <div class="panel__actions">
                    <form
                        class="panel__action"
                        action="{{ route('peers.reap_mine', ['id' => $torrent->id]) }}"
                        method="POST"
                    >
                        @csrf
                        <button
                            class="form__button form__button--filled"
                            style="background: var(--color-red, #ff0039); color: #fff"
                            title="Suelta tus pares de este torrent que llevan más de 65 min sin anunciar (cliente reiniciado o cerrado de golpe), para que no cuenten en el límite de 3. Los más recientes no se tocan: pueden seguir vivos"
                        >
                            Limpiar mis pares
                        </button>
                    </form>
                </div>
            @endif
        </header>
        @include('torrent.partials.peers-table', ['rows' => $activePeers])
    </section>

    @if ($inactivePeers->isNotEmpty())
        <section class="panelV2">
            <details @if ($isStaff) open @endif>
                <summary class="panel__heading">
                    Inactivos ({{ $inactivePeers->count() }}): no cuentan para seeders/leechers
                    ni para el límite de 3 por torrent; la lista se vacía sola a los 2 días
                </summary>
                @include('torrent.partials.peers-table', ['rows' => $inactivePeers])
            </details>
        </section>
    @endif
@endsection
