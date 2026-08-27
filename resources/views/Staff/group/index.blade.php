{{--
    NOBS — Nuclear Order Bit Syndicate
    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra derivada de UNIT3D Community Edition (HDInnovations), de la que hereda
    la licencia GNU AGPL v3.0.

    La tabla original pintaba 34 columnas y una X roja por cada permiso apagado:
    con 23 grupos y 19 flags salían ~400 iconos rojos que no dicen nada y una
    tabla imposible de leer, capturar o imprimir. Aquí los flags se colapsan en
    una sola celda que muestra SÓLO lo que está activo, y se añade el recuento
    de miembros, que es el dato que hacía falta para auditar los grupos.
--}}
@extends('layout.with-main')

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('staff.dashboard.index') }}" class="breadcrumb__link">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumb--active">
        {{ __('staff.groups') }}
    </li>
@endsection

@section('page', 'page__staff-group--index')

@section('main')
    @php
        // Cada flag: columna en BD => [etiqueta corta, título largo, familia].
        // La familia da el color y separa "quién manda" de "qué puede hacer".
        $mando = [
            'is_owner'        => ['OWN', 'Owner'],
            'is_admin'        => ['ADM', 'Administrador'],
            'is_modo'         => ['MOD', 'Moderador'],
            'is_torrent_modo' => ['TMOD', 'Moderador de torrents'],
            'is_editor'       => ['EDI', 'Editor'],
            'is_internal'     => ['INT', 'Interno'],
        ];

        $poderes = [
            'is_trusted'       => ['SIN-COLA', 'Se salta la moderación de torrents'],
            'is_uploader'      => ['SUBE', 'Marcado como uploader'],
            'is_immune'        => ['INMUNE', 'Inmune a los avisos automáticos'],
            'is_freeleech'     => ['FL', 'Freeleech permanente'],
            'is_double_upload' => ['x2', 'Doble subida'],
            'is_refundable'    => ['REEMB', 'Descargas reembolsables'],
            'is_incognito'     => ['INCÓG', 'Incógnito'],
        ];

        $permisos = [
            'can_upload'  => ['subir', 'Puede subir torrents'],
            'can_chat'    => ['chat', 'Puede usar el chat'],
            'can_comment' => ['comentar', 'Puede comentar'],
            'can_invite'  => ['invitar', 'Puede invitar'],
            'can_request' => ['pedir', 'Puede hacer peticiones'],
        ];
    @endphp

    <style>
        /* Tabla de auditoría de grupos — compacta y, sobre todo, imprimible. */
        .grupos__tabla {
            font-size: 13px;
        }

        .grupos__tabla td,
        .grupos__tabla th {
            vertical-align: top;
            padding: 6px 8px;
        }

        .grupos__nombre {
            font-weight: 700;
            white-space: nowrap;
        }

        .grupos__slug {
            display: block;
            font-weight: 400;
            font-size: 11px;
            opacity: 0.6;
        }

        /* Las insignias sustituyen a 19 columnas de checks y cruces. */
        .grupos__flags {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            min-width: 240px;
        }

        .grupos__flag {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.6;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .grupos__flag--mando {
            background: #7b1fa2;
            color: #fff;
        }

        .grupos__flag--poder {
            background: #1565c0;
            color: #fff;
        }

        /* SIN-COLA se destaca: es el permiso que más cuesta detectar y el que
           más daño hace repartido de más. */
        .grupos__flag--aviso {
            background: #c62828;
            color: #fff;
        }

        .grupos__flag--permiso {
            background: transparent;
            border-color: currentColor;
            font-weight: 400;
            opacity: 0.75;
        }

        .grupos__num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .grupos__miembros {
            font-weight: 700;
        }

        .grupos__vacio {
            opacity: 0.35;
        }

        .grupos__req {
            min-width: 200px;
            font-size: 11.5px;
            line-height: 1.5;
        }

        .grupos__req dt {
            display: inline;
            opacity: 0.6;
        }

        .grupos__req dd {
            display: inline;
            margin: 0 8px 0 2px;
        }

        .grupos__leyenda {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            padding: 10px 14px;
            font-size: 12px;
        }

        .grupos__leyenda span {
            white-space: nowrap;
        }

        @media print {
            /* Que quepa y que no se lleve por delante la navegación. */
            .grupos__tabla {
                font-size: 9px;
            }

            .grupos__tabla td,
            .grupos__tabla th {
                padding: 2px 3px;
            }

            .data-table-wrapper {
                overflow-x: visible !important;
            }

            .grupos__flags {
                min-width: 0;
            }

            .grupos__acciones,
            .top-nav,
            .breadcrumbs {
                display: none !important;
            }

            .grupos__tabla tr {
                break-inside: avoid;
            }
        }
    </style>

    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">
                Grupos ({{ $groups->count() }})
            </h2>
            <div class="panel__actions">
                <a
                    href="{{ route('staff.groups.create') }}"
                    class="panel__action form__button form__button--text"
                >
                    {{ __('common.add') }}
                </a>
            </div>
        </header>

        <div class="grupos__leyenda">
            <span>
                <i class="grupos__flag grupos__flag--aviso">SIN-COLA</i>
                se salta la moderación
            </span>
            <span>
                <i class="grupos__flag grupos__flag--mando">MANDO</i>
                permisos de staff
            </span>
            <span>
                <i class="grupos__flag grupos__flag--poder">PODER</i>
                ventajas de tracker
            </span>
            <span>
                <i class="grupos__flag grupos__flag--permiso">permiso</i>
                lo que puede hacer
            </span>
            <span>Los flags apagados no se pintan.</span>
        </div>

        <div class="data-table-wrapper">
            <table class="data-table grupos__tabla">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th class="grupos__num">Miembros</th>
                        <th class="grupos__num">Pos.</th>
                        <th class="grupos__num">Nivel</th>
                        <th class="grupos__num">Slots</th>
                        <th>Aspecto</th>
                        <th>Permisos activos</th>
                        <th>Autogroup</th>
                        <th class="grupos__acciones">{{ __('common.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        <tr>
                            <td class="grupos__nombre">
                                <a href="{{ route('staff.groups.edit', ['group' => $group]) }}">
                                    <i class="{{ $group->icon }}" style="color: {{ $group->color }}"></i>
                                    {{ $group->name }}
                                </a>
                                <span class="grupos__slug">{{ $group->slug }} &middot; id {{ $group->id }}</span>
                            </td>

                            <td class="grupos__num grupos__miembros">
                                {{ number_format($group->users_count) }}
                            </td>

                            <td class="grupos__num">{{ $group->position }}</td>
                            <td class="grupos__num">{{ $group->level }}</td>

                            <td class="grupos__num">
                                @if ($group->download_slots === null)
                                    <span title="Sin límite de descargas simultáneas">∞</span>
                                @else
                                    {{ $group->download_slots }}
                                @endif
                            </td>

                            <td>
                                <span title="{{ $group->color }}">{{ $group->color }}</span>
                                @if ($group->effect !== '' && $group->effect !== 'none')
                                    <br />
                                    <span
                                        style="font-size: 11px"
                                        title="{{ $group->effect }}"
                                    >
                                        efecto: {{ \Illuminate\Support\Str::of($group->effect)->after('/img/')->before(')') }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                <div class="grupos__flags">
                                    @foreach ($mando as $campo => [$corto, $largo])
                                        @if ($group->$campo)
                                            <span
                                                class="grupos__flag grupos__flag--mando"
                                                title="{{ $largo }}"
                                            >
                                                {{ $corto }}
                                            </span>
                                        @endif
                                    @endforeach

                                    @foreach ($poderes as $campo => [$corto, $largo])
                                        @if ($group->$campo)
                                            <span
                                                class="grupos__flag {{ $campo === 'is_trusted' ? 'grupos__flag--aviso' : 'grupos__flag--poder' }}"
                                                title="{{ $largo }}"
                                            >
                                                {{ $corto }}
                                            </span>
                                        @endif
                                    @endforeach

                                    @foreach ($permisos as $campo => [$corto, $largo])
                                        @if ($group->$campo)
                                            <span
                                                class="grupos__flag grupos__flag--permiso"
                                                title="{{ $largo }}"
                                            >
                                                {{ $corto }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>

                            <td class="grupos__req">
                                @if (! $group->autogroup)
                                    <span class="grupos__vacio">manual</span>
                                @else
                                    <dl style="margin: 0">
                                        @if ($group->min_uploaded)
                                            <dt>subido</dt>
                                            <dd>{{ \App\Helpers\StringHelper::formatBytes($group->min_uploaded) }}</dd>
                                        @endif

                                        @if ($group->min_ratio !== null)
                                            <dt>ratio</dt>
                                            <dd>{{ $group->min_ratio }}</dd>
                                        @endif

                                        @if ($group->min_age)
                                            <dt>antigüedad</dt>
                                            <dd>{{ \App\Helpers\StringHelper::timeElapsed($group->min_age) }}</dd>
                                        @endif

                                        @if ($group->min_avg_seedtime)
                                            <dt>siembra media</dt>
                                            <dd>{{ \App\Helpers\StringHelper::timeElapsed($group->min_avg_seedtime) }}</dd>
                                        @endif

                                        @if ($group->min_seedsize)
                                            <dt>tamaño sembrado</dt>
                                            <dd>{{ \App\Helpers\StringHelper::formatBytes($group->min_seedsize) }}</dd>
                                        @endif

                                        @if ($group->min_uploads)
                                            <dt>subidas</dt>
                                            <dd>{{ $group->min_uploads }}</dd>
                                        @endif
                                    </dl>
                                @endif
                            </td>

                            <td class="grupos__acciones">
                                <menu class="data-table__actions">
                                    <li class="data-table__action">
                                        <a
                                            href="{{ route('staff.groups.edit', ['group' => $group]) }}"
                                            class="form__button form__button--text"
                                        >
                                            {{ __('common.edit') }}
                                        </a>
                                    </li>
                                    @unless ($group->system_required)
                                        <li class="data-table__action">
                                            <form
                                                action="{{ route('staff.groups.destroy', ['group' => $group]) }}"
                                                method="POST"
                                                x-data="confirmation"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    x-on:click.prevent="confirmAction"
                                                    data-b64-deletion-message="{{ base64_encode('¿Seguro que quieres borrar el grupo ' . $group->name . '? Sus ' . $group->users_count . ' usuarios se moverán al grupo que les corresponda.') }}"
                                                    class="form__button form__button--text"
                                                >
                                                    {{ __('common.delete') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endunless
                                </menu>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
