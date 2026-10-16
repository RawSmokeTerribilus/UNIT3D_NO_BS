{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
@php
    $fa = config('other.font-awesome');
    $tz = 'Europe/Madrid';
    $today = now($tz)->toDateString();
    $when = function (float $at) use ($tz, $today): string {
        if ($at <= 0) {
            return '—';
        }
        $t = \Carbon\Carbon::createFromTimestamp($at, $tz);

        return $t->toDateString() === $today ? $t->format('H:i:s') : $t->format('d-m H:i');
    };
    $dur = fn (?int $ms): string => $ms === null ? '' : ($ms < 1000 ? $ms.' ms' : ($ms < 60000 ? round($ms / 1000, 1).' s' : floor($ms / 60000).' min '.round(($ms % 60000) / 1000).' s'));
    $status = [
        'running'  => ['fa-cog fa-spin', 'en curso', 'run'],
        'pending'  => ['fa-hourglass-half', 'en cola', 'wait'],
        'delayed'  => ['fa-clock', 'diferido', 'wait'],
        'ok'       => ['fa-check', 'ok', 'ok'],
        'released' => ['fa-undo', 'devuelto a la cola', 'warn'],
        'retry'    => ['fa-redo', 'error, se reintenta', 'warn'],
        'failed'   => ['fa-times-circle', 'fallido', 'fail'],
    ];
@endphp

<div
    class="qm"
    wire:poll.5s="tick"
    x-data="{
        idx: -1,
        async go(step) {
            let fails = [...this.$root.querySelectorAll('[data-fail]')];
            if (this.idx < 0) {
                {{-- La primera vez abre en el servidor todos los grupos con fallos --}}
                await $wire.openFails();
                fails = [...this.$root.querySelectorAll('[data-fail]')];
            }
            if (! fails.length) return;
            this.idx = (this.idx + step + fails.length) % fails.length;
            const el = fails[this.idx];
            el.scrollIntoView({ block: 'center' });
            el.classList.add('qm-flash');
            setTimeout(() => el.classList.remove('qm-flash'), 1200);
        },
    }"
>
    {{-- Barra: filtros, búsqueda, saltos entre fallos, en vivo --}}
    <div class="qm-bar">
        <div class="qm-filters">
            @foreach (['all' => 'Todo', 'active' => 'En cola', 'failed' => 'Fallos', 'ok' => 'Terminados'] as $key => $text)
                <button
                    type="button"
                    wire:click="$set('filter', '{{ $key }}')"
                    @class(['qm-filter', 'qm-filter--on' => $filter === $key, 'qm-filter--fail' => $key === 'failed'])
                >
                    {{ $text }}
                    @if ($key === 'failed' && $counts['failed'] > 0)
                        <span class="qm-pill qm-pill--fail">{{ $counts['failed'] }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        <input
            type="search"
            class="qm-search"
            placeholder="Filtrar por nombre o datos…"
            wire:model.live.debounce.400ms="search"
        />

        <div class="qm-nav" title="Saltar de fallo en fallo (abre su grupo)">
            <button type="button" class="qm-filter" x-on:click="go(-1)">
                <i class="{{ $fa }} fa-chevron-up"></i>
            </button>
            <span class="qm-nav__count" x-text="(idx < 0 ? '–' : idx + 1) + ' / {{ (int) $fails }}'"></span>
            <button type="button" class="qm-filter" x-on:click="go(1)">
                <i class="{{ $fa }} fa-chevron-down"></i>
            </button>
        </div>

        <label class="qm-live" title="Refresca cada 5 s">
            <input type="checkbox" wire:model.live="live" />
            en vivo
        </label>
    </div>

    {{-- Resumen de la cola --}}
    <div class="qm-summary">
        <span class="qm-s qm-s--run"><i class="{{ $fa }} fa-cog"></i> {{ $counts['running'] }} en curso</span>
        <span class="qm-s"><i class="{{ $fa }} fa-hourglass-half"></i> {{ $counts['pending'] }} en cola</span>
        <span class="qm-s"><i class="{{ $fa }} fa-clock"></i> {{ $counts['delayed'] }} diferidos</span>
        <span class="qm-s qm-s--ok"><i class="{{ $fa }} fa-check"></i> {{ $counts['ok'] }} terminados ({{ \App\Services\QueueMonitor::HISTORY_DAYS }} días)</span>
        <span @class(['qm-s', 'qm-s--fail' => $counts['failed'] > 0])><i class="{{ $fa }} fa-times-circle"></i> {{ $counts['failed'] }} fallidos</span>
    </div>

    @if ($notice !== '')
        <div class="qm-notice">
            <i class="{{ $fa }} fa-terminal"></i>
            {{ $notice }}
            <button type="button" class="qm-x" wire:click="$set('notice', '')">×</button>
        </div>
    @endif

    {{-- La consola --}}
    <div class="qm-console">
        @forelse ($groups as $g)
            <div
                wire:key="g-{{ md5($g['name']) }}"
                @class(['qm-group', 'qm-group--fail' => $g['failed'] > 0, 'qm-group--run' => ($g['counts']['running'] ?? 0) > 0])
            >
                @php($isOpen = isset($open[$g['name']]))
                <div class="qm-group__head" wire:click="toggle(@js($g['name']))" title="{{ $g['name'] }}">
                    <i @class(['fa-caret-right', 'qm-caret', 'qm-caret--open' => $isOpen, $fa])></i>
                    <span class="qm-group__name">{{ $g['label'] }}</span>
                    @if ($g['tag'] !== '')
                        <span class="qm-tag">{{ $g['tag'] }}</span>
                    @endif
                    <span class="qm-pill">{{ count($g['entries']) }}</span>
                    @if (($g['counts']['running'] ?? 0) > 0)
                        <span class="qm-pill qm-pill--run"><i class="{{ $fa }} fa-cog fa-spin"></i> {{ $g['counts']['running'] }}</span>
                    @endif
                    @if (($g['counts']['pending'] ?? 0) + ($g['counts']['delayed'] ?? 0) > 0)
                        <span class="qm-pill qm-pill--wait">{{ ($g['counts']['pending'] ?? 0) + ($g['counts']['delayed'] ?? 0) }} en cola</span>
                    @endif
                    @if (($g['counts']['ok'] ?? 0) > 0)
                        <span class="qm-pill qm-pill--ok">✓ {{ $g['counts']['ok'] }}</span>
                    @endif
                    @if (($g['counts']['retry'] ?? 0) + ($g['counts']['released'] ?? 0) > 0)
                        <span class="qm-pill qm-pill--warn">↻ {{ ($g['counts']['retry'] ?? 0) + ($g['counts']['released'] ?? 0) }}</span>
                    @endif
                    @if ($g['failed'] > 0)
                        <span class="qm-pill qm-pill--fail">✖ {{ $g['failed'] }}</span>
                    @endif
                    <span class="qm-group__meta">
                        @if ($g['avg'] !== null)
                            media {{ $dur($g['avg']) }} ·
                        @endif
                        {{ $when($g['last']) }}
                    </span>
                </div>

                @if ($isOpen)
                <div class="qm-group__body">
                    @if ($g['stored'] > 0)
                        <div class="qm-group__actions">
                            {{ $g['stored'] }} en failed_jobs:
                            <button
                                type="button"
                                class="qm-act"
                                wire:click="retryGroup(@js($g['name']))"
                                wire:confirm="¿Devolver a la cola los {{ $g['stored'] }} fallidos de {{ $g['label'] }}?"
                            ><i class="{{ $fa }} fa-redo"></i> reintentar todos</button>
                            <button
                                type="button"
                                class="qm-act qm-act--danger"
                                wire:click="forgetGroup(@js($g['name']))"
                                wire:confirm="¿Descartar los {{ $g['stored'] }} fallidos de {{ $g['label'] }}? Se borran de failed_jobs."
                            ><i class="{{ $fa }} fa-trash"></i> descartar todos</button>
                        </div>
                    @endif

                    @foreach (array_slice($g['entries'], 0, $g['limit']) as $e)
                        @php([$icon, $text, $tone] = $status[$e['status']] ?? ['fa-question', $e['status'], 'wait'])
                        <div
                            wire:key="e-{{ $e['status'] }}-{{ $e['id'] }}-{{ $loop->index }}"
                            class="qm-line qm-line--{{ $tone }}"
                            @if ($e['status'] === 'failed') data-fail @endif
                        >
                            <span class="qm-line__time" title="{{ $text }}">{{ $when($e['at']) }}</span>
                            <i class="{{ $fa }} {{ $icon }} qm-line__icon" title="{{ $text }}"></i>
                            <span class="qm-line__desc">
                                {{ $e['desc'] !== '' ? $e['desc'] : $text }}
                                @if ($e['error'])
                                    <span class="qm-line__err">{{ $e['error'] }}</span>
                                @endif
                            </span>
                            <span class="qm-line__meta">
                                @if ($e['ms'] !== null)
                                    {{ $dur($e['ms']) }}
                                @endif
                                @if (($e['attempts'] ?? 0) > 1)
                                    · {{ $e['attempts'] }} intentos
                                @endif
                                <span class="qm-uuid" title="{{ $e['id'] }}">{{ substr($e['id'], 0, 8) }}</span>
                            </span>
                            @if ($e['stored'])
                                <span class="qm-line__acts">
                                    <button type="button" class="qm-act" wire:click="trace('{{ $e['id'] }}')" title="Traza completa">
                                        <i class="{{ $fa }} fa-bug"></i>
                                    </button>
                                    <button type="button" class="qm-act" wire:click="retry('{{ $e['id'] }}')" wire:confirm="¿Devolver este job a la cola?" title="Reintentar">
                                        <i class="{{ $fa }} fa-redo"></i>
                                    </button>
                                    <button type="button" class="qm-act qm-act--danger" wire:click="forget('{{ $e['id'] }}')" wire:confirm="¿Descartar este fallido? Se borra de failed_jobs." title="Descartar">
                                        <i class="{{ $fa }} fa-trash"></i>
                                    </button>
                                </span>
                            @endif
                        </div>
                        @isset($traces[$e['id']])
                            <pre class="qm-trace" wire:key="t-{{ $e['id'] }}">{{ $traces[$e['id']] }}</pre>
                        @endisset
                    @endforeach

                    @if (count($g['entries']) > $g['limit'])
                        <button type="button" class="qm-more" wire:click="more(@js($g['name']))">
                            mostrar 100 más ({{ count($g['entries']) - $g['limit'] }} sin pintar)
                        </button>
                    @endif
                </div>
                @endif
            </div>
        @empty
            <div class="qm-empty">Nada en esta cola con este filtro.</div>
        @endforelse
    </div>
</div>
