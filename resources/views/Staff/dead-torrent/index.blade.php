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
    <title>Torrents muertos - {{ __('staff.staff-dashboard') }} - {{ config('other.title') }}</title>
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
    <li class="breadcrumb--active">Torrents muertos</li>
@endsection

@section('page', 'page__staff-dead-torrent--index')

@section('main')
    @if (session('info'))
        <div class="dead-output">{{ session('info') }}</div>
    @endif

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-skull-crossbones"></i>
            Candidatos: {{ $candidatos->count() }}
        </h2>
        <div class="panel__body">
            <p>
                Torrents con más de {{ $horas }} h, sin ninguna semilla ahora y sin que nadie
                haya sembrado en las últimas {{ $horas }} h. Al confirmar, el que lo subió y quien
                lo completó reciben un privado pidiendo la resiembra, con la fecha de borrado.
                Si en {{ $horas }} h sigue sin semillas, se borra solo (con el aviso de borrado
                de siempre) y el grupo de staff recibe el resumen. Desmarca los que no quieras
                tocar.
            </p>
            <p class="dead-hint">
                El plazo sale de Configuración → Hit &amp; Run → «Plazo de torrents muertos»
                (0 = el tiempo mínimo de seed). Horas en hora peninsular.
            </p>
        </div>

        @if ($candidatos->isNotEmpty())
            <form
                method="POST"
                action="{{ route('staff.commands.dead_torrents.store') }}"
                data-confirm="Se pedirá la resiembra de los torrents marcados y se borrarán dentro de {{ $horas }} h si nadie vuelve a sembrarlos. ¿Continuar?"
            >
                @csrf
                <div class="data-table-wrapper">
                    <table class="data-table" data-tools>
                        <thead>
                            <tr>
                                <th></th>
                                <th>Torrent</th>
                                <th>Subidor</th>
                                <th>Tamaño</th>
                                <th>Completaron</th>
                                <th>Leechers</th>
                                <th>Subido</th>
                                <th>Última semilla</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($candidatos as $t)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="{{ $t->id }}" checked />
                                    </td>
                                    <td>
                                        <a href="{{ route('torrents.show', ['id' => $t->id]) }}">{{ $t->name }}</a>
                                    </td>
                                    <td>{{ $t->subidor }}</td>
                                    <td data-sort="{{ $t->size }}">{{ App\Helpers\StringHelper::formatBytes($t->size, 1) }}</td>
                                    <td>{{ $t->completaron }}</td>
                                    <td>{{ $t->leechers }}</td>
                                    <td data-sort="{{ $t->created_at }}">{{ Illuminate\Support\Carbon::parse($t->created_at)->timezone('Europe/Madrid')->format('d/m/Y') }}</td>
                                    <td data-sort="{{ $t->ultima_semilla ?? '0000' }}">
                                        @if ($t->ultima_semilla === null)
                                            <em>nunca</em>
                                        @else
                                            {{ Illuminate\Support\Carbon::parse($t->ultima_semilla)->timezone('Europe/Madrid')->format('d/m/Y H:i') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel__body">
                    <button type="submit" class="form__button form__button--filled">
                        Pedir resiembra de los marcados
                    </button>
                </div>
            </form>
        @endif
    </section>

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-hourglass-half"></i>
            Esperando resiembra: {{ $pendientes->count() }}
        </h2>
        @if ($pendientes->isEmpty())
            <div class="panel__body">Ninguno.</div>
        @else
            <div class="data-table-wrapper">
                <table class="data-table" data-tools>
                    <thead>
                        <tr>
                            <th>Torrent</th>
                            <th>Pedida</th>
                            <th>Por</th>
                            <th>Privados</th>
                            <th>Se borra</th>
                            <th>{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendientes as $n)
                            <tr>
                                <td>
                                    <a href="{{ route('torrents.show', ['id' => $n->torrent_id]) }}">{{ $n->nombre }}</a>
                                </td>
                                <td data-sort="{{ $n->avisado_at }}">{{ $n->avisado_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</td>
                                <td>{{ $n->staff?->username ?? '—' }}</td>
                                <td>{{ $n->destinatarios }}</td>
                                <td data-sort="{{ $n->borrar_at }}" title="{{ $n->borrar_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}">
                                    {{ $n->borrar_at->isPast() ? 'en la próxima pasada' : $n->borrar_at->diffForHumans() }}
                                </td>
                                <td>
                                    <form
                                        method="POST"
                                        action="{{ route('staff.commands.dead_torrents.cancel', ['notice' => $n->id]) }}"
                                        data-confirm="No se borrará «{{ $n->nombre }}» aunque siga sin semillas. Los privados ya enviados no se retiran. ¿Cancelar la petición?"
                                    >
                                        @csrf
                                        <button type="submit" class="form__button form__button--text">
                                            Cancelar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-history"></i>
            Cerradas (últimas 50)
        </h2>
        @if ($cerrados->isEmpty())
            <div class="panel__body">Ninguna todavía.</div>
        @else
            <div class="data-table-wrapper">
                <table class="data-table" data-tools>
                    <thead>
                        <tr>
                            <th>Torrent</th>
                            <th>Resultado</th>
                            <th>Pedida</th>
                            <th>Cerrada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cerrados as $n)
                            <tr>
                                <td>#{{ $n->torrent_id }} {{ $n->nombre }}</td>
                                <td>
                                    @switch ($n->estado)
                                        @case (App\Models\DeadTorrentNotice::BORRADO)
                                            🗑 borrado
                                            @break
                                        @case (App\Models\DeadTorrentNotice::SALVADO)
                                            🌱 salvado
                                            @break
                                        @case (App\Models\DeadTorrentNotice::DESAPARECIDO)
                                            👻 lo borró otro
                                            @break
                                        @default
                                            ✋ cancelado
                                    @endswitch
                                </td>
                                <td data-sort="{{ $n->avisado_at }}">{{ $n->avisado_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</td>
                                <td data-sort="{{ $n->resuelto_at }}">{{ $n->resuelto_at?->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <style>
        .dead-output {
            background: #0d1117;
            border: 1px solid #30363d;
            border-left: 3px solid #3fb950;
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            margin-bottom: 1.5rem;
            color: #c9d1d9;
            white-space: pre-wrap;
        }

        .dead-hint {
            opacity: 0.7;
            font-size: 1.2rem;
        }
    </style>
@endsection

@section('scripts')
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}" src="{{ asset('js/staff-command-confirm.js') }}"></script>
    @include('Staff.partials.table-tools')
@endsection
