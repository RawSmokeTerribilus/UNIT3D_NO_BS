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
    <title>Baneo por H&amp;R - {{ __('staff.staff-dashboard') }} - {{ config('other.title') }}</title>
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
    <li class="breadcrumb--active">Baneo por H&amp;R abandonado</li>
@endsection

@section('page', 'page__staff-hitrun-ban--index')

@section('main')
    @if (session('info'))
        <div class="hrb-output">{{ session('info') }}</div>
    @endif

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-info-circle"></i>
            Cómo funciona
        </h2>
        <div class="panel__body">
            <p>
                Candidatos: {{ App\Services\HitRunContacts::CONTACTOS_PARA_DECIDIR }} o más contactos de H&amp;R
                en el registro, el último hace {{ $dias }} días o más, ningún plazo abierto, deuda pendiente,
                ningún aviso cumplido desde el primer contacto y su cliente sin anunciar nada desde el último.
                Quien solo ha entrado a la web sale <strong>desmarcado</strong>: decides tú.
            </p>
            <p>
                Al confirmar: invitación de un solo uso al grupo de Telegram «Baneados de NOBS» (privado del bot
                si tiene Telegram vinculado, y en el correo), baneo nativo (Castigados, fuera del grupo de
                miembros) y anotación en el registro. A los {{ $diasPoda }} días sale abajo para prunear; quien
                entró al grupo, desmarcado.
            </p>
            <p class="hrb-hint">
                Antes de banear a nadie que dudes, mira <code>Raw_Vault/Edge_Cases</code>: ahí están los informes
                de dónde fallan las medidas.
                @unless ($grupo)
                    <strong>Este entorno no tiene TELEGRAM_BANNED_GROUP_ID: no se creará invitación y el correo
                    pedirá que respondan por correo.</strong>
                @endunless
            </p>
            <form method="GET" action="{{ route('staff.commands.hitrun_bans.index') }}" class="hrb-filtros">
                <p class="form__group">
                    <input id="dias" name="dias" type="number" min="1" max="365" value="{{ $dias }}" class="form__text" />
                    <label class="form__label form__label--floating" for="dias">Días desde el último contacto</label>
                </p>
                <p class="form__group">
                    <button type="submit" class="form__button form__button--outlined">Ver lista</button>
                </p>
            </form>
        </div>
    </section>

    <section class="panelV2">
        <h2 class="panel__heading panel__heading--danger">
            <i class="{{ config('other.font-awesome') }} fa-user-slash"></i>
            Candidatos a baneo: {{ $candidatos->count() }}
        </h2>
        @if ($candidatos->isNotEmpty())
            <form
                method="POST"
                action="{{ route('staff.commands.hitrun_bans.store') }}"
                data-confirm="Se banearán los socios marcados, se les invitará al grupo de baneados y quedará anotado. ¿Banear?"
            >
                @csrf
                <input type="hidden" name="dias" value="{{ $dias }}" />
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Socio</th>
                                <th>Grupo</th>
                                <th>Contactos</th>
                                <th>Último contacto</th>
                                <th>Activos / camino</th>
                                <th>Último announce</th>
                                <th>Última web</th>
                                <th>TG / correo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($candidatos as $u)
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="{{ $u['id'] }}" @checked($u['marcado']) /></td>
                                    <td><a href="{{ route('users.show', ['user' => $u['username']]) }}">{{ $u['username'] }}</a></td>
                                    <td>{{ $u['grupo'] }}</td>
                                    <td>{{ $u['contactos'] }} (desde {{ $u['primer_contacto']->format('d/m') }})</td>
                                    <td>{{ $u['ultimo_contacto']->timezone('Europe/Madrid')->format('d/m/Y') }}</td>
                                    <td>{{ $u['activos'] }} / {{ $u['en_camino'] }}</td>
                                    <td>{{ $u['ultimo_announce']?->timezone('Europe/Madrid')->format('d/m/Y') ?? 'nunca' }}</td>
                                    <td>
                                        {{ $u['ultima_web']?->timezone('Europe/Madrid')->format('d/m/Y') ?? 'nunca' }}
                                        @unless ($u['marcado'])
                                            ⚠ entra a la web
                                        @endunless
                                    </td>
                                    <td>{{ $u['telegram'] ? 'TG' : '—' }} / {{ $u['tiene_email'] ? '✉' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel__body">
                    <button type="submit" class="form__button form__button--filled">Banear a los marcados</button>
                </div>
            </form>
        @endif
    </section>

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-user-clock"></i>
            Con 2+ contactos pero fuera: {{ $excluidos->count() }}
        </h2>
        @if ($excluidos->isNotEmpty())
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Socio</th>
                            <th>Contactos</th>
                            <th>Activos / camino</th>
                            <th>Por qué</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($excluidos as $u)
                            <tr>
                                <td><a href="{{ route('users.show', ['user' => $u['username']]) }}">{{ $u['username'] }}</a></td>
                                <td>{{ $u['contactos'] }}</td>
                                <td>{{ $u['activos'] }} / {{ $u['en_camino'] }}</td>
                                <td>{{ $u['motivo'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panelV2">
        <h2 class="panel__heading panel__heading--danger">
            <i class="{{ config('other.font-awesome') }} fa-skull"></i>
            Plazo vencido, para prunear: {{ $podables->count() }}
        </h2>
        @if ($podables->isNotEmpty())
            <form
                method="POST"
                action="{{ route('staff.commands.hitrun_bans.prune') }}"
                data-confirm="Se aniquilarán las cuentas marcadas (sus subidas y mensajes pasan a System). No se puede deshacer desde el panel. ¿Prunear?"
            >
                @csrf
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Socio</th>
                                <th>Baneado</th>
                                <th>Invitación</th>
                                <th>Entró al grupo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($podables as $b)
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="{{ $b->id }}" @checked($b->joined_at === null) /></td>
                                    <td>{{ $b->user->username }}</td>
                                    <td>{{ $b->banned_at->timezone('Europe/Madrid')->format('d/m/Y') }}</td>
                                    <td>{{ $b->invite_link === null ? 'no' : ($b->telegram_enviado ? 'TG + correo' : 'correo') }}</td>
                                    <td>{{ $b->joined_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') ?? 'no' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel__body">
                    <p class="hrb-hint">Quien entró al grupo sale desmarcado: lee lo que escribió antes de decidir.</p>
                    <button type="submit" class="form__button form__button--filled">Prunear a los marcados</button>
                </div>
            </form>
        @endif
    </section>

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-history"></i>
            Baneos por H&amp;R (últimos 50)
        </h2>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Socio</th>
                        <th>Baneado</th>
                        <th>Por</th>
                        <th>Invitación</th>
                        <th>Entró</th>
                        <th>Poda desde</th>
                        <th>Podado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($baneos as $b)
                        <tr>
                            <td>{{ $b->user?->username ?? '#'.$b->user_id }}</td>
                            <td>{{ $b->banned_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</td>
                            <td>{{ $b->staff?->username ?? '—' }}</td>
                            <td>{{ $b->invite_link === null ? 'no' : ($b->telegram_enviado ? 'TG + correo' : 'correo') }}</td>
                            <td>{{ $b->joined_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $b->prune_after->timezone('Europe/Madrid')->format('d/m/Y') }}</td>
                            <td>{{ $b->pruned_at?->timezone('Europe/Madrid')->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Ninguno todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <style>
        .hrb-output {
            background: #0d1117;
            border: 1px solid #30363d;
            border-left: 3px solid #f87171;
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            margin-bottom: 1.5rem;
            color: #c9d1d9;
            white-space: pre-wrap;
        }

        .hrb-filtros {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .hrb-hint {
            opacity: 0.75;
            font-size: 1.2rem;
        }

        .panel__heading--danger {
            background: linear-gradient(135deg, #7f1d1d 0%, #b91c1c 100%) !important;
        }
    </style>
@endsection

@section('scripts')
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}" src="{{ asset('js/staff-command-confirm.js') }}"></script>
@endsection
