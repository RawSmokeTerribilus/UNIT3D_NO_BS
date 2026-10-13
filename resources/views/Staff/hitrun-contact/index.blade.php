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
    <title>Mensajes de H&amp;R - {{ __('staff.staff-dashboard') }} - {{ config('other.title') }}</title>
@endsection

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('staff.dashboard.index') }}" class="breadcrumb__link">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumbV2">
        <a href="{{ route('staff.commands.index') }}" class="breadcrumb__link">Comandos</a>
    </li>
    <li class="breadcrumb--active">Mensajes de H&amp;R</li>
@endsection

@section('page', 'page__staff-hitrun-contact--index')

@section('main')
    @if (session('info'))
        <div class="hrc-output">{{ session('info') }}</div>
    @endif

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-filter"></i>
            A quién
        </h2>
        <div class="panel__body">
            <form method="GET" action="{{ route('staff.commands.hitrun_contacts.index') }}" class="hrc-filtros">
                <p class="form__group">
                    <select id="filtro" name="filtro" class="form__select">
                        <option value="limite" @selected($filtro === 'limite')>Van a pasar de {{ $max }} avisos o ya han pasado</option>
                        <option value="todos" @selected($filtro === 'todos')>Todos con H&amp;R activo</option>
                    </select>
                    <label class="form__label form__label--floating" for="filtro">Filtro</label>
                </p>
                <p class="form__group">
                    <select id="canal" name="canal" class="form__select">
                        <option value="pm+email" @selected($canal === 'pm+email')>Privado del tracker + correo</option>
                        <option value="pm" @selected($canal === 'pm')>Solo privado del tracker</option>
                        <option value="email" @selected($canal === 'email')>Solo correo</option>
                    </select>
                    <label class="form__label form__label--floating" for="canal">Canal</label>
                </p>
                <p class="form__group">
                    <input id="plazo" name="plazo" type="number" min="1" max="90" value="{{ $plazo }}" class="form__text" />
                    <label class="form__label form__label--floating" for="plazo">Plazo (días sin volver a escribirle)</label>
                </p>
                <p class="form__group">
                    <button type="submit" class="form__button form__button--outlined">Ver lista</button>
                </p>
            </form>
            <p class="hrc-hint">
                Avisos activos = los que penalizan ahora (no el contador histórico). «En camino» = descargas
                sin sembrar que el sistema convertirá en aviso. Quedan fuera, con su motivo: staff, cuentas
                baneadas o sin validar, cortes manuales del staff, quien está en otro proceso (plazo abierto
                en el registro) y quien ya lleva {{ App\Services\HitRunContacts::CONTACTOS_PARA_DECIDIR }}
                contactos de H&amp;R: a esos ya no se les escribe, se decide.
            </p>
        </div>
    </section>

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-envelope"></i>
            Recibirán el mensaje: {{ $incluidos->count() }}
        </h2>
        @if ($incluidos->isNotEmpty())
            <form
                method="POST"
                action="{{ route('staff.commands.hitrun_contacts.store') }}"
                data-confirm="Se enviará el mensaje a los socios marcados ({{ $canal }}) y quedará anotado, con {{ $plazo }} días sin volver a escribirles. ¿Enviar?"
            >
                @csrf
                <input type="hidden" name="filtro" value="{{ $filtro }}" />
                <input type="hidden" name="canal" value="{{ $canal }}" />
                <input type="hidden" name="plazo" value="{{ $plazo }}" />
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Socio</th>
                                <th>Grupo</th>
                                <th>Activos</th>
                                <th>En camino</th>
                                <th>Descarga</th>
                                <th>Correo / TG</th>
                                <th>Última visita</th>
                                <th>Contactos de H&amp;R</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($incluidos as $u)
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="{{ $u['id'] }}" checked /></td>
                                    <td><a href="{{ route('users.show', ['user' => $u['username']]) }}">{{ $u['username'] }}</a></td>
                                    <td>{{ $u['grupo'] }}</td>
                                    <td>{{ $u['activos'] }}</td>
                                    <td>{{ $u['en_camino'] }}</td>
                                    <td>{{ $u['can_download'] ? 'sí' : 'cortada' }}</td>
                                    <td>{{ $u['tiene_email'] ? '✉' : '—' }} {{ $u['telegram'] ? 'TG' : '' }}</td>
                                    <td>{{ $u['last_action'] ? Illuminate\Support\Carbon::parse($u['last_action'])->timezone('Europe/Madrid')->format('d/m/Y') : 'nunca' }}</td>
                                    <td>{{ $u['contactos'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel__body">
                    <button type="submit" class="form__button form__button--filled">Enviar a los marcados</button>
                </div>
            </form>
        @endif
    </section>

    @if ($muestra !== null)
        <section class="panelV2">
            <h2 class="panel__heading">
                <i class="{{ config('other.font-awesome') }} fa-eye"></i>
                Muestra — lo que recibiría {{ $muestra['para'] }}
            </h2>
            <div class="panel__body">
                <p><strong>{{ $muestra['asunto'] }}</strong></p>
                <pre class="hrc-muestra">{{ $muestra['texto'] }}</pre>
            </div>
        </section>
    @endif

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-user-clock"></i>
            Fuera de esta tanda: {{ $excluidos->count() }}
        </h2>
        @if ($excluidos->isNotEmpty())
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Socio</th>
                            <th>Activos</th>
                            <th>En camino</th>
                            <th>Por qué</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($excluidos as $u)
                            <tr>
                                <td><a href="{{ route('users.show', ['user' => $u['username']]) }}">{{ $u['username'] }}</a></td>
                                <td>{{ $u['activos'] }}</td>
                                <td>{{ $u['en_camino'] }}</td>
                                <td>{{ $u['motivo'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-book"></i>
            Registro de contactos (últimos 100)
        </h2>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Socio</th>
                        <th>Campaña</th>
                        <th>Canal</th>
                        <th>Activos / camino</th>
                        <th>Horas</th>
                        <th>Plazo</th>
                        <th>Por</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registro as $c)
                        <tr>
                            <td>{{ $c->contacted_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</td>
                            <td>{{ $c->user?->username ?? '#'.$c->user_id }}</td>
                            <td title="{{ $c->nota }}">{{ $c->campana }}</td>
                            <td>
                                {{ $c->canal }}
                                @if ($c->conversation_id)
                                    (conv. {{ $c->conversation_id }})
                                @endif
                            </td>
                            <td>{{ $c->avisos_activos ?? '—' }} / {{ $c->avisos_en_camino ?? '—' }}</td>
                            <td>{{ $c->horas_pendientes ?? '—' }}</td>
                            <td>{{ $c->plazo_hasta?->timezone('Europe/Madrid')->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $c->staff?->username ?? $c->origen }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Vacío.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <style>
        .hrc-output {
            background: #0d1117;
            border: 1px solid #30363d;
            border-left: 3px solid #3fb950;
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            margin-bottom: 1.5rem;
            color: #c9d1d9;
            white-space: pre-wrap;
        }

        .hrc-filtros {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .hrc-hint {
            opacity: 0.7;
            font-size: 1.2rem;
        }

        .hrc-muestra {
            white-space: pre-wrap;
            font-size: 1.25rem;
            max-height: 40rem;
            overflow-y: auto;
        }
    </style>
@endsection

@section('scripts')
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}" src="{{ asset('js/staff-command-confirm.js') }}"></script>
@endsection
