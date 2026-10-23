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
    <title>Arena - {{ __('staff.staff-dashboard') }} - {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('staff.dashboard.index') }}" class="breadcrumb__link">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumb--active">Arena</li>
@endsection

@section('page', 'page__staff-arena')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-chess-board"></i>
            Arena
            <span class="arena-st__estado {{ $abierta ? 'arena-st__estado--on' : 'arena-st__estado--off' }}">
                {{ $abierta ? 'Abierta a los socios' : 'Cerrada (solo owners/admins)' }}
            </span>
        </h2>
        <div class="panel__body">
            <p class="arena-st__nota">
                El interruptor general y la duración del pase están en
                <a href="{{ route('staff.config.index') }}">Configuración → Arena</a>.
                Aquí se gestionan las salas: el agente de cada juego lanza y para procesos dentro de su
                contenedor (nunca toca Docker). Reiniciar el stack entero es cosa de Portainer.
            </p>
        </div>
    </section>

    @foreach ($modulos as $slug => $m)
        <section class="panelV2">
            <h2 class="panel__heading">{{ $m['nombre'] }}</h2>
            <div class="panel__body">
                @if (! $m['estado']['ok'])
                    <p class="arena-st__error">
                        <i class="{{ config('other.font-awesome') }} fa-exclamation-triangle"></i>
                        {{ $m['estado']['error'] }}
                    </p>
                @else
                    @php($e = $m['estado']['data'])
                    <p class="arena-st__nota">
                        {{ count($e['salas'] ?? []) }}/{{ $e['max_salas'] ?? '?' }} salas ·
                        contenedor {{ $e['memoria_contenedor_mb'] ?? '?' }} MB ·
                        carga del host {{ implode(' / ', array_map(fn ($c) => number_format($c, 2), $e['carga'] ?? [])) }}
                        ({{ $e['cpus'] ?? '?' }} CPU)
                    </p>
                    <div class="data-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Sala</th>
                                    <th>Nombre / preset</th>
                                    <th>Fase</th>
                                    <th>Jugadores</th>
                                    <th>CPU</th>
                                    <th>RAM</th>
                                    <th>Arranques</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($e['salas'] ?? [] as $s)
                                    <tr>
                                        <td><code>{{ $s['id'] }}</code></td>
                                        <td>{{ $s['nombre'] }}<br><small>{{ $s['preset'] }}</small></td>
                                        <td>{{ $s['fase'] }}</td>
                                        <td>
                                            {{ $s['jugadores'] }}/{{ $s['huecos'] }}
                                            @if (! empty($s['nombres']))
                                                <br><small>{{ implode(', ', $s['nombres']) }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $s['cpu'] }} %</td>
                                        <td>{{ $s['rss_mb'] }} MB</td>
                                        <td>{{ $s['arranques'] }}</td>
                                        <td class="arena-st__acciones">
                                            <a class="form__button form__button--text" href="{{ route('staff.arena.index', ['modulo' => $slug, 'log' => $s['id']]) }}#log">Log</a>
                                            <form method="POST" action="{{ route('staff.arena.reiniciar', ['slug' => $slug, 'sala' => $s['id']]) }}"
                                                data-confirm="¿Reiniciar {{ $s['id'] }}? Quien esté dentro sale de la partida.">
                                                @csrf
                                                <button type="submit" class="form__button form__button--text">Reiniciar</button>
                                            </form>
                                            @if ($esAdmin)
                                                <form method="POST" action="{{ route('staff.arena.borrar', ['slug' => $slug, 'sala' => $s['id']]) }}"
                                                    data-confirm="¿Cerrar {{ $s['id'] }} del todo? Desaparece del lobby hasta que se cree otra.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="form__button form__button--text">Cerrar</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('staff.arena.aviso', ['slug' => $slug, 'sala' => $s['id']]) }}" class="arena-st__aviso">
                                                @csrf
                                                <input type="text" name="mensaje" maxlength="200" class="form__text" placeholder="Aviso al chat de la sala" required>
                                                <button type="submit" class="form__button form__button--text">Enviar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8">No hay salas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($esAdmin)
                        <div class="arena-st__admin">
                            <form method="POST" action="{{ route('staff.arena.crear', ['slug' => $slug]) }}" class="arena-st__crear">
                                @csrf
                                <select name="preset" class="form__select" required>
                                    @foreach ($m['presets'] as $p)
                                        <option value="{{ $p }}">{{ $p }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="form__button form__button--filled">Crear sala</button>
                            </form>
                            <form method="POST" action="{{ route('staff.arena.apagar_todo', ['slug' => $slug]) }}"
                                data-confirm="⚠️ ¿Cerrar TODAS las salas de {{ $m['nombre'] }}? Se acaban todas las partidas en curso.">
                                @csrf
                                <button type="submit" class="form__button form__button--outlined">Apagar todas las salas</button>
                            </form>
                        </div>
                    @endif
                @endif
            </div>
        </section>
    @endforeach

    @if ($logSala)
        <section class="panelV2" id="log">
            <h2 class="panel__heading">Log de {{ $logSala['sala'] }} ({{ $logSala['modulo'] }})</h2>
            <div class="panel__body">
                @if ($logSala['datos']['ok'])
                    <pre class="arena-st__log">{{ implode("\n", $logSala['datos']['data']['lineas'] ?? []) }}</pre>
                @else
                    <p class="arena-st__error">{{ $logSala['datos']['error'] }}</p>
                @endif
            </div>
        </section>
    @endif

    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}" src="{{ asset('js/staff-command-confirm.js') }}"></script>

    <style>
        .arena-st__estado { margin-left: 8px; font-size: 12px; padding: 2px 8px; border-radius: 3px; border: 1px solid var(--panel_border); }
        .arena-st__estado--on { color: #6fcf7f; border-color: #6fcf7f; }
        .arena-st__estado--off { color: #e0a43a; border-color: #e0a43a; }
        .arena-st__nota { opacity: .85; margin: 0 0 12px; }
        .arena-st__error { color: #e06c6c; }
        .arena-st__acciones { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
        .arena-st__acciones form { margin: 0; }
        .arena-st__aviso { display: flex; gap: 4px; }
        .arena-st__aviso .form__text { min-width: 180px; }
        .arena-st__admin { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; align-items: center; }
        .arena-st__crear { display: flex; gap: 6px; align-items: center; }
        .arena-st__log { background: #0a0a0a; color: #c8ffa0; font-size: 11px; line-height: 1.45; padding: 8px 10px; border-radius: 4px; max-height: 480px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
    </style>
@endsection
