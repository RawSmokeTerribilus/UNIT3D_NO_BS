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
    $range = fn (float $a, float $b): string => $when($a) === $when($b) ? $when($b) : $when($a).' → '.$when($b);
    $dur = fn (?int $ms): string => $ms === null ? '' : ($ms < 1000 ? $ms.' ms' : ($ms < 60000 ? round($ms / 1000, 1).' s' : floor($ms / 60000).' min '.round(($ms % 60000) / 1000).' s'));
    $status = [
        'running'  => ['fa-cog fa-spin', 'en curso', 'run'],
        'pending'  => ['fa-hourglass-half', 'en cola', 'wait'],
        'delayed'  => ['fa-clock', 'diferido', 'wait'],
        'ok'       => ['fa-check', 'terminados', 'ok'],
        'released' => ['fa-undo', 'devueltos a la cola', 'warn'],
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
            if (this.idx < 0) {
                {{-- La primera vez abre en el servidor los grupos con fallos --}}
                await $wire.openFails();
            }
            const fails = [...this.$root.querySelectorAll('[data-fail]')];
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
            placeholder="Filtrar por job, datos o error…"
            wire:model.live.debounce.400ms="search"
        />

        <div class="qm-nav" title="Saltar de un tipo de fallo al siguiente (abre su grupo)">
            <button type="button" class="qm-filter" x-on:click="go(-1)">
                <i class="{{ $fa }} fa-chevron-up"></i>
            </button>
            <span class="qm-nav__count" x-text="(idx < 0 ? '–' : idx + 1) + ' / {{ (int) $kinds }}'"></span>
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
        @if ($newFail > 0)
            <span class="qm-s qm-s--new"><i class="{{ $fa }} fa-bell"></i> {{ $newFail }} nuevos desde tu última visita ({{ $when($seenBefore) }})</span>
        @endif
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
                {{-- Nivel 1: el job --}}
                <div class="qm-group__head" wire:click="toggle(@js($g['name']))" title="{{ $g['name'] }}">
                    <i @class(['fa-caret-right', 'qm-caret', 'qm-caret--open' => $g['open'], $fa])></i>
                    <span class="qm-group__name">{{ $g['label'] }}</span>
                    @if ($g['tag'] !== '')
                        <span class="qm-tag">{{ $g['tag'] }}</span>
                    @endif
                    <span class="qm-pill">{{ $g['total'] }}</span>
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
                    @if ($g['new'] > 0)
                        <span class="qm-pill qm-pill--new">{{ $g['new'] }} nuevos</span>
                    @endif
                    <span class="qm-group__meta">
                        @if ($g['avg'] !== null)
                            media {{ $dur($g['avg']) }} ·
                        @endif
                        {{ $when($g['last']) }}
                    </span>
                </div>

                @if ($g['open'])
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

                        @foreach ($g['subs'] as $s)
                            @php([$icon, $text, $tone] = $status[$s['status']] ?? ['fa-question', $s['status'], 'wait'])
                            @php($subKey = $g['name'].'|'.$s['key'])
                            <div wire:key="s-{{ md5($subKey) }}" class="qm-sub">
                                {{-- Nivel 2: una línea por cosa distinta, con su contador --}}
                                <div
                                    class="qm-sub__head qm-line--{{ $tone }}"
                                    wire:click="toggle(@js($subKey))"
                                    @if ($s['status'] === 'failed') data-fail @endif
                                >
                                    <i @class(['fa-caret-right', 'qm-caret', 'qm-caret--open' => $s['open'], $fa])></i>
                                    <i class="{{ $fa }} {{ $icon }} qm-line__icon"></i>
                                    <span class="qm-line__desc">
                                        @if ($s['text'] !== '')
                                            <span class="qm-line__err">{{ $s['text'] }}</span>
                                        @else
                                            {{ $text }}
                                        @endif
                                    </span>
                                    @if ($s['new'] > 0)
                                        <span class="qm-pill qm-pill--new">{{ $s['new'] }} nuevos</span>
                                    @endif
                                    <span class="qm-count">×{{ count($s['entries']) }}</span>
                                    <span class="qm-line__meta">{{ $range($s['first'], $s['last']) }}</span>
                                </div>

                                @if ($s['open'])
                                    @if ($s['stored'] > 1)
                                        <div class="qm-group__actions qm-group__actions--sub">
                                            {{ $s['stored'] }} con este error en failed_jobs:
                                            <button
                                                type="button"
                                                class="qm-act"
                                                wire:click="retryGroup(@js($g['name']), @js($s['key']))"
                                                wire:confirm="¿Devolver a la cola estos {{ $s['stored'] }} fallidos?"
                                            ><i class="{{ $fa }} fa-redo"></i> reintentar estos</button>
                                            <button
                                                type="button"
                                                class="qm-act qm-act--danger"
                                                wire:click="forgetGroup(@js($g['name']), @js($s['key']))"
                                                wire:confirm="¿Descartar estos {{ $s['stored'] }} fallidos? Se borran de failed_jobs."
                                            ><i class="{{ $fa }} fa-trash"></i> descartar estos</button>
                                        </div>
                                    @endif

                                    {{-- Nivel 3: los jobs sueltos --}}
                                    @foreach (array_slice($s['entries'], 0, $s['limit']) as $e)
                                        <div wire:key="e-{{ $e['status'] }}-{{ $e['id'] }}-{{ $loop->index }}" @class(['qm-line', 'qm-line--'.$tone, 'qm-line--new' => $e['new']])>
                                            <span class="qm-line__time">{{ $when($e['at']) }}</span>
                                            <span class="qm-line__desc">
                                                {{ $e['desc'] !== '' ? $e['desc'] : ($s['status'] === 'failed' ? '' : $text) }}
                                                @if ($e['error'] && $e['error'] !== $s['text'])
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

                                    @if (count($s['entries']) > $s['limit'])
                                        <button type="button" class="qm-more" wire:click="more(@js($subKey))">
                                            mostrar 100 más ({{ count($s['entries']) - $s['limit'] }} sin pintar)
                                        </button>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="qm-empty">Nada en esta cola con este filtro.</div>
        @endforelse
    </div>
</div>
