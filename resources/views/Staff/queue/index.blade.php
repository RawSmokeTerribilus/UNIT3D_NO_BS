{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
@extends('layout.with-main')

@section('title')
    <title>Colas - {{ __('staff.staff-dashboard') }} - {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Colas - {{ __('staff.staff-dashboard') }}" />
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('staff.dashboard.index') }}" class="breadcrumb__link">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumb--active">Colas</li>
@endsection

@section('nav-tabs')
    @foreach ($totals as $name => $t)
        @php($active = $name === $queue)
        <li class="{{ $active ? 'nav-tab--active' : 'nav-tabV2' }}">
            <a
                class="{{ $active ? 'nav-tab--active__link' : 'nav-tab__link' }}"
                href="{{ route('staff.queues.index', ['queue' => $name]) }}"
                title="{{ $t['running'] }} en curso · {{ $t['pending'] }} en cola · {{ $t['failed'] }} fallidos"
            >
                {{ $name }}
                @if ($t['running'] + $t['pending'] > 0)
                    <span class="qm-tab qm-tab--run">{{ $t['running'] + $t['pending'] }}</span>
                @endif
                @if ($t['failed'] > 0)
                    <span class="qm-tab qm-tab--fail">✖ {{ $t['failed'] }}</span>
                @endif
            </a>
        </li>
    @endforeach
@endsection

@section('page', 'page__staff-queue--index')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-stream"></i>
            Cola «{{ $queue }}»
        </h2>
        <div class="panel__body">
            @livewire('staff.queue-monitor', ['queue' => $queue], key('qm-'.$queue))
            <p class="qm-foot">
                Lo terminado se guarda {{ \App\Services\QueueMonitor::HISTORY_DAYS }} días (máx.
                {{ number_format(\App\Services\QueueMonitor::HISTORY_MAX, 0, ',', '.') }} por cola, en Redis). Los fallidos
                viven en failed_jobs y se podan solos a los 30 días. Horas en hora peninsular.
            </p>
        </div>
    </section>

    <style>
        [x-cloak] { display: none !important; }

        .qm-tab {
            display: inline-block;
            margin-left: 0.35rem;
            padding: 0 0.4rem;
            border-radius: 0.6rem;
            font-size: 0.75em;
            line-height: 1.5;
        }
        .qm-tab--run  { background: rgba(56, 139, 253, 0.25); color: #79c0ff; }
        .qm-tab--fail { background: rgba(248, 81, 73, 0.25); color: #ff7b72; }

        .qm { font-size: 1.2rem; }

        .qm-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 1rem;
            margin-bottom: 0.75rem;
        }
        .qm-filters, .qm-nav { display: flex; align-items: center; gap: 0.25rem; }
        .qm-filter {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid #30363d;
            border-radius: 0.35rem;
            color: #8b949e;
            padding: 0.3rem 0.7rem;
            cursor: pointer;
        }
        .qm-filter:hover { color: #c9d1d9; }
        .qm-filter--on { background: #1f6feb33; border-color: #1f6feb; color: #c9d1d9; }
        .qm-filter--fail.qm-filter--on { background: #f8514933; border-color: #f85149; }
        .qm-nav__count { min-width: 4.5rem; text-align: center; color: #8b949e; font-family: monospace; }
        .qm-search {
            flex: 1 1 14rem;
            min-width: 0;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 0.35rem;
            color: #c9d1d9;
            padding: 0.35rem 0.6rem;
        }
        .qm-live { display: flex; align-items: center; gap: 0.3rem; color: #8b949e; cursor: pointer; }

        .qm-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem 1.2rem;
            margin-bottom: 0.75rem;
            color: #8b949e;
            font-family: monospace;
        }
        .qm-s--run  { color: #79c0ff; }
        .qm-s--ok   { color: #3fb950; }
        .qm-s--fail { color: #ff7b72; }

        .qm-notice {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #0d1117;
            border: 1px solid #30363d;
            border-left: 3px solid #3fb950;
            border-radius: 0.35rem;
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.75rem;
            color: #c9d1d9;
            font-family: monospace;
        }
        .qm-x { margin-left: auto; background: none; border: 0; color: #8b949e; cursor: pointer; font-size: 1.4rem; }

        .qm-console {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 0.5rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 1.15rem;
            color: #c9d1d9;
            overflow: hidden;
        }
        .qm-group { border-bottom: 1px solid #21262d; border-left: 3px solid transparent; }
        .qm-group:last-child { border-bottom: 0; }
        .qm-group--fail { border-left-color: #f85149; background: rgba(248, 81, 73, 0.05); }
        .qm-group--run  { border-left-color: #388bfd; }
        .qm-group__head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.75rem;
            cursor: pointer;
            user-select: none;
        }
        .qm-group__head:hover { background: rgba(255, 255, 255, 0.03); }
        .qm-caret { width: 0.8rem; color: #8b949e; transition: transform 0.12s ease; }
        .qm-caret--open { transform: rotate(90deg); }
        .qm-group__name { font-weight: 600; }
        .qm-tag { color: #8b949e; font-size: 0.9em; }
        .qm-group__meta { margin-left: auto; color: #8b949e; font-size: 0.9em; }
        .qm-pill {
            padding: 0 0.45rem;
            border-radius: 0.6rem;
            background: rgba(110, 118, 129, 0.25);
            color: #c9d1d9;
            font-size: 0.85em;
        }
        .qm-pill--run  { background: rgba(56, 139, 253, 0.2); color: #79c0ff; }
        .qm-pill--wait { background: rgba(110, 118, 129, 0.35); }
        .qm-pill--ok   { background: rgba(63, 185, 80, 0.15); color: #3fb950; }
        .qm-pill--warn { background: rgba(210, 153, 34, 0.2); color: #d29922; }
        .qm-pill--fail { background: rgba(248, 81, 73, 0.2); color: #ff7b72; }

        .qm-group__body { border-top: 1px solid #21262d; }
        .qm-group__actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.75rem 0.4rem 2rem;
            color: #8b949e;
        }
        .qm-line {
            display: flex;
            align-items: baseline;
            gap: 0.6rem;
            padding: 0.2rem 0.75rem 0.2rem 2rem;
            border-top: 1px solid #161b22;
        }
        .qm-line__time { flex: 0 0 auto; color: #6e7681; }
        .qm-line__icon { flex: 0 0 1rem; text-align: center; }
        .qm-line__desc { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; }
        .qm-line__err { display: block; color: #ff7b72; }
        .qm-line__meta { flex: 0 0 auto; color: #6e7681; font-size: 0.9em; }
        .qm-line__acts { flex: 0 0 auto; display: flex; gap: 0.2rem; }
        .qm-uuid { margin-left: 0.4rem; }
        .qm-line--ok   .qm-line__icon { color: #3fb950; }
        .qm-line--run  .qm-line__icon { color: #79c0ff; }
        .qm-line--wait .qm-line__icon { color: #8b949e; }
        .qm-line--warn { background: rgba(210, 153, 34, 0.08); }
        .qm-line--warn .qm-line__icon { color: #d29922; }
        .qm-line--fail { background: rgba(248, 81, 73, 0.12); color: #ffa198; }
        .qm-line--fail .qm-line__icon { color: #f85149; }
        .qm-flash { outline: 2px solid #f0883e; outline-offset: -2px; }

        .qm-act {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid #30363d;
            border-radius: 0.3rem;
            color: #8b949e;
            padding: 0.05rem 0.45rem;
            cursor: pointer;
        }
        .qm-act:hover { color: #c9d1d9; border-color: #8b949e; }
        .qm-act--danger:hover { color: #ff7b72; border-color: #f85149; }

        .qm-trace {
            margin: 0;
            padding: 0.6rem 0.75rem 0.6rem 2rem;
            max-height: 24rem;
            overflow: auto;
            background: #010409;
            color: #ffa198;
            font-size: 0.9em;
            white-space: pre-wrap;
        }
        .qm-more {
            display: block;
            width: 100%;
            padding: 0.35rem;
            background: none;
            border: 0;
            border-top: 1px solid #21262d;
            color: #58a6ff;
            cursor: pointer;
        }
        .qm-empty { padding: 1.5rem; text-align: center; color: #8b949e; }
        .qm-foot { margin-top: 0.75rem; color: #8b949e; font-size: 1.05rem; }

        @media (max-width: 640px) {
            .qm-line { flex-wrap: wrap; padding-left: 0.75rem; }
            .qm-group__meta { margin-left: 0; width: 100%; }
        }
    </style>
@endsection
