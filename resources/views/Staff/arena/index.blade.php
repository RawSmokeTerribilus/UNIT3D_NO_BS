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
                                        <td>
                                            {{ $s['nombre'] }}
                                            <br><small>{{ $s['mapa'] ?? '' }} · {{ $s['preset'] }}
                                                @isset($s['resumen']) · {{ $s['resumen']['humanos'] }} humanos{{ $s['resumen']['ias'] ? ', '.$s['resumen']['ias'].' IA' : '' }}@endisset</small>
                                            <form method="POST" action="{{ route('staff.arena.renombrar', ['slug' => $slug, 'sala' => $s['id']]) }}" class="arena-st__renombrar">
                                                @csrf
                                                <input type="text" name="nombre" maxlength="60" class="form__text" placeholder="Nuevo nombre" required>
                                                <button type="submit" class="form__button form__button--text">Renombrar</button>
                                            </form>
                                        </td>
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
                            <form method="POST" action="{{ route('staff.arena.apagar_todo', ['slug' => $slug]) }}"
                                data-confirm="⚠️ ¿Cerrar TODAS las salas de {{ $m['nombre'] }}? Se acaban todas las partidas en curso.">
                                @csrf
                                <button type="submit" class="form__button form__button--outlined">Apagar todas las salas</button>
                            </form>
                        </div>

                        <h3 class="arena-st__sub">Crear sala desde un preset</h3>
                        <form method="POST" action="{{ route('staff.arena.crear', ['slug' => $slug]) }}" class="arena-st__crear">
                            @csrf
                            <input type="hidden" name="modo" value="preset">
                            <select name="preset" class="form__select" required>
                                @foreach ($m['presets'] as $p)
                                    <option value="{{ $p['slug'] }}">{{ $p['nombre'] }} — {{ $p['mapa'] }}, {{ $p['humanos'] }} humanos{{ $p['ias'] ? ', '.$p['ias'].' IA' : '' }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="nombre" maxlength="60" class="form__text" placeholder="Nombre de la sala (opcional)">
                            <button type="submit" class="form__button form__button--filled">Crear sala</button>
                        </form>

                        @if ($m['catalogo'])
                            <h3 class="arena-st__sub">Crear sala a medida</h3>
                            <form method="POST" action="{{ route('staff.arena.crear', ['slug' => $slug]) }}" class="arena-st__medida" data-arena-medida>
                                @csrf
                                <input type="hidden" name="modo" value="medida">
                                <input type="hidden" name="plan" data-arena-plan>
                                <div class="arena-st__rejilla">
                                    <label>Nombre <input type="text" data-campo="nombre" maxlength="60" class="form__text" required placeholder="p. ej. Viernes de tanques"></label>
                                    <label>Mapa
                                        <select data-campo="mapa" class="form__select" data-arena-mapa required>
                                            @foreach (collect($m['catalogo']['mapas'])->groupBy('jugadores') as $n => $grupo)
                                                <optgroup label="{{ $n }} jugadores">
                                                    @foreach ($grupo as $mapa)
                                                        <option value="{{ $mapa['nombre'] }}" data-jugadores="{{ $mapa['jugadores'] }}">{{ $mapa['nombre'] }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Alianzas
                                        <select data-campo="alianzas" class="form__select">
                                            <option value="0">Todos contra todos</option>
                                            <option value="1">Alianzas libres</option>
                                            <option value="2" selected>Equipos fijos</option>
                                            <option value="3">Equipos fijos sin compartir investigación</option>
                                        </select>
                                    </label>
                                    <label>Energía
                                        <select data-campo="energia" class="form__select">
                                            <option value="0">Baja</option><option value="1" selected>Media</option><option value="2">Alta</option>
                                        </select>
                                    </label>
                                    <label>Bases
                                        <select data-campo="bases" class="form__select">
                                            <option value="1">Sin base</option><option value="2" selected>Base</option><option value="3">Base avanzada</option>
                                        </select>
                                    </label>
                                    <label>Tecnología
                                        <select data-campo="tecnologia" class="form__select">
                                            <option value="1" selected>Nivel 1</option><option value="2">Nivel 2</option><option value="3">Nivel 3</option><option value="4">Nivel 4</option>
                                        </select>
                                    </label>
                                    <label>Chatarreros
                                        <select data-campo="chatarreros" class="form__select">
                                            <option value="0" selected>No</option><option value="1">Sí</option><option value="2">Definitivos</option>
                                        </select>
                                    </label>
                                    <label>Huecos de espectador <input type="number" data-campo="espectadores" min="0" max="4" value="1" class="form__text"></label>
                                    <label>Contraseña (opcional) <input type="text" data-campo="contrasena" maxlength="30" class="form__text" autocomplete="off"></label>
                                    <label class="arena-st__check"><input type="checkbox" data-campo="bloquear" checked> Bloquear ajustes en el lobby</label>
                                </div>
                                <table class="data-table arena-st__huecos">
                                    <thead><tr><th>Hueco</th><th>Tipo</th><th>IA</th><th>Dificultad</th><th>Facción</th><th>Equipo</th></tr></thead>
                                    <tbody data-arena-huecos></tbody>
                                </table>
                                <div class="arena-st__crear">
                                    <label class="arena-st__check"><input type="checkbox" data-arena-guardar> Guardar también como preset:</label>
                                    <input type="text" name="guardar_como" maxlength="40" pattern="[a-z0-9][a-z0-9-]*" class="form__text" placeholder="slug-del-preset" data-arena-slug disabled>
                                    <button type="submit" class="form__button form__button--filled">Crear sala a medida</button>
                                </div>
                                <script type="application/json" data-arena-catalogo>@json($m['catalogo'])</script>
                            </form>
                        @endif

                        <h3 class="arena-st__sub">Presets guardados</h3>
                        <ul class="arena-st__presets">
                            @foreach ($m['presets'] as $p)
                                <li>
                                    <strong>{{ $p['nombre'] }}</strong> <code>{{ $p['slug'] }}</code>
                                    <small>{{ $p['mapa'] }} · {{ $p['huecos'] }} huecos · {{ $p['humanos'] }} humanos{{ $p['ias'] ? ' · '.$p['ias'].' IA' : '' }}</small>
                                    <form method="POST" action="{{ route('staff.arena.borrar_preset', ['slug' => $slug, 'preset' => $p['slug']]) }}"
                                        data-confirm="¿Borrar el preset {{ $p['slug'] }}? Las salas que ya lo usan no cambian.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="form__button form__button--text">Borrar</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
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
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}" src="{{ asset('js/arena-panel.js') }}?v={{ filemtime(public_path('js/arena-panel.js')) }}"></script>

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
        .arena-st__renombrar { display: flex; gap: 4px; margin-top: 4px; }
        .arena-st__renombrar .form__text { max-width: 180px; padding: 2px 6px; font-size: 12px; }
        .arena-st__sub { margin: 22px 0 10px; font-size: 15px; }
        .arena-st__medida { border: 1px solid var(--panel_border); border-radius: 6px; padding: 12px; }
        .arena-st__rejilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px 14px; margin-bottom: 12px; }
        .arena-st__rejilla label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
        .arena-st__check { flex-direction: row !important; align-items: center; gap: 6px !important; }
        .arena-st__huecos select { min-width: 110px; }
        .arena-st__huecos input[type=number] { width: 60px; }
        .arena-st__presets { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; }
        .arena-st__presets li { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .arena-st__presets form { margin: 0; }
        .arena-st__log { background: #0a0a0a; color: #c8ffa0; font-size: 11px; line-height: 1.45; padding: 8px 10px; border-radius: 4px; max-height: 480px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
    </style>
@endsection
