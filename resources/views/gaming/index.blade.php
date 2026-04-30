@extends('layout.with-main')

@section('title')
    <title>Arcade — {{ config('other.title') }}</title>
@endsection

@section('meta')
    <meta name="description" content="Juega a clásicos de aventura gráfica directamente en el navegador." />
@endsection

@section('breadcrumbs')
    <li class="breadcrumb--active">
        Arcade
    </li>
@endsection

@section('page', 'page__gaming')

@section('main')
    <section class="panelV2">
        <h2 class="panel__heading">
            <i class="{{ config('other.font-awesome') }} fa-gamepad"></i>
            Retro Arcade
        </h2>
        <div class="panel__body">
            <p class="gaming-intro">
                Juega a las aventuras gráficas más míticas directamente en el navegador,
                sin instalar nada. El motor ScummVM corre completamente en tu máquina mediante
                WebAssembly. Las partidas se guardan automáticamente en tu perfil.
            </p>

            <ul class="gaming-card__list">
                @foreach ($juegos as $juego)
                    <li class="gaming-card__item">
                        <a class="gaming-card" href="{{ route('gaming.show', ['gameId' => $juego['id']]) }}">
                            <div class="gaming-card__cover">
                                <img
                                    src="{{ $juego['cover'] }}"
                                    alt="Portada de {{ $juego['titulo'] }}"
                                    loading="lazy"
                                    onerror="this.style.display='none'"
                                />
                            </div>
                            <div class="gaming-card__info">
                                <h3 class="gaming-card__title">{{ $juego['titulo'] }}</h3>
                                <p class="gaming-card__meta">
                                    <span>{{ $juego['desarrollador'] }}</span>
                                    <span>{{ $juego['año'] }}</span>
                                </p>
                                <p class="gaming-card__meta">
                                    <span class="gaming-card__badge">{{ $juego['version'] }}</span>
                                    <span class="gaming-card__badge gaming-card__badge--lang">🔊 {{ $juego['idioma'] }}</span>
                                </p>
                                <p class="gaming-card__description">{{ $juego['descripcion'] }}</p>
                                <span class="btn btn--filled gaming-card__play">
                                    <i class="{{ config('other.font-awesome') }} fa-play"></i>
                                    Jugar ahora
                                </span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="gaming-notice">
                <i class="{{ config('other.font-awesome') }} fa-circle-info"></i>
                Los juegos corren íntegramente en tu navegador. Necesitas una conexión estable
                para la carga inicial. Las partidas se sincronizan con tu cuenta automáticamente
                al guardar dentro del juego.
            </div>
        </div>
    </section>

    <style>
        .gaming-intro {
            font-size: 14px;
            color: var(--body_text);
            margin-bottom: 24px;
            line-height: 1.7;
        }

        .gaming-card__list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
            list-style: none;
            padding: 0;
            margin: 0 0 24px 0;
        }

        .gaming-card {
            display: flex;
            gap: 16px;
            background: var(--panel_inner_background);
            border: 1px solid var(--panel_border);
            border-radius: 8px;
            padding: 16px;
            text-decoration: none;
            color: var(--body_text);
            transition: border-color .2s, transform .15s;
        }

        .gaming-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .gaming-card__cover {
            flex-shrink: 0;
            width: 100px;
            height: 140px;
            border-radius: 4px;
            overflow: hidden;
            background: var(--panel_background);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .gaming-card__cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gaming-card__info {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }

        .gaming-card__title {
            font-size: 18px;
            font-weight: 700;
            color: var(--body_text);
            margin: 0;
            line-height: 1.3;
        }

        .gaming-card__meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 13px;
            color: var(--body_text);
            opacity: .8;
            margin: 0;
        }

        .gaming-card__badge {
            background: var(--panel_background);
            border: 1px solid var(--panel_border);
            border-radius: 3px;
            padding: 2px 8px;
            font-size: 13px;
        }

        .gaming-card__badge--lang {
            border-color: var(--primary);
            color: var(--primary);
        }

        .gaming-card__description {
            font-size: 14px;
            line-height: 1.6;
            color: var(--body_text);
            opacity: .85;
            margin: 0;
            flex: 1;
        }

        .gaming-card__play {
            align-self: flex-start;
            margin-top: auto;
            font-size: 14px;
            padding: 8px 18px;
            pointer-events: none;
        }

        .gaming-notice {
            background: var(--panel_inner_background);
            border: 1px solid var(--panel_border);
            border-left: 4px solid var(--primary);
            border-radius: 4px;
            padding: 12px 16px;
            font-size: 14px;
            color: var(--body_text);
            opacity: .85;
        }

        .gaming-notice i {
            margin-right: 6px;
            color: var(--primary);
        }
    </style>
@endsection
