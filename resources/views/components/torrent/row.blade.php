@props([
    'torrent',
    'meta',
    'personalFreeleech',
])

@php
    // IGDB llama al suyo `first_video_video_id` pero es el mismo id de YouTube
    // que TMDB guarda en `trailer`, asi que el hover del nombre lo reproduce
    // igual: un juego SI tiene trailer, solo que en otra columna.
    $trailerKey = $meta?->trailer ?? $meta?->first_video_video_id;

    if (!$trailerKey && !empty($torrent->description)) {
        if (preg_match('/\[(?:youtube|video(?:="youtube")?)\]([a-zA-Z0-9_-]{11})\[\/(?:youtube|video)\]/i', $torrent->description, $m)) {
            $trailerKey = $m[1];
        }
    }

    $quickMI = $torrent->mediainfo ? (new App\Helpers\MediaInfo)->parse($torrent->mediainfo) : null;

    // Un libro no tiene mediainfo ni trailer, así que el hover del nombre —
    // que en vídeo es la vista rápida del FICHERO, no de la obra — se llena
    // con lo que sí describe la edición concreta. La obra sigue en el hover
    // de la portada. Cuando bookinfo entre por la descripción, este bloque
    // es donde se cuelga.
    $isBookRow = $torrent->category->book_meta || $torrent->category->audiobook_meta;
    $quickBook = $isBookRow && $meta !== null ? $meta : null;

    $hasQuickView = $trailerKey || $quickMI !== null || $quickBook !== null;
@endphp

<tr
    @class([
        'torrent-search--list__row' => auth()->user()->settings->show_poster,
        'torrent-search--list__no-poster-row' => ! auth()->user()->settings->show_poster,
        'torrent-search--list__sticky-row' => $torrent->sticky,
    ])
    data-torrent-id="{{ $torrent->id }}"
    data-igdb-id="{{ $torrent->igdb }}"
    data-imdb-id="{{ $torrent->imdb }}"
    data-tmdb-id="{{ $torrent->tmdb }}"
    data-tvdb-id="{{ $torrent->tvdb }}"
    data-mal-id="{{ $torrent->mal }}"
    data-category-id="{{ $torrent->category_id }}"
    data-type-id="{{ $torrent->type_id }}"
    data-resolution-id="{{ $torrent->resolution_id }}"
    wire:key="torrent-search-row-{{ $torrent->id }}"
>
    @if (auth()->user()->settings->show_poster)
        <td
            class="torrent-search--list__poster"
            x-data="{ metaPopup: false }"
            @mouseenter="metaPopup = true"
            @mouseleave="metaPopup = false"
        >
            <a
                href="{{
                    match (true) {
                        $torrent->tmdb_movie_id !== null => route('torrents.similar', ['category_id' => $torrent->category_id, 'tmdb' => $torrent->tmdb_movie_id]),
                        $torrent->tmdb_tv_id !== null => route('torrents.similar', ['category_id' => $torrent->category_id, 'tmdb' => $torrent->tmdb_tv_id]),
                        $torrent->igdb !== null => route('torrents.similar', ['category_id' => $torrent->category_id, 'tmdb' => $torrent->igdb]),
                        default => '#',
                    }
                }}"
            >
                @if ($torrent->category->movie_meta || $torrent->category->tv_meta)
                    <img
                        src="{{ isset($meta->poster) ? tmdb_image('poster_small', $meta->poster) : url('img/sin-imagen.svg') }}"
                        class="torrent-search--list__poster-img"
                        loading="lazy"
                        alt="{{ __('torrent.similar') }}"
                    />
                    @include('torrent.partials.meta-popup', ['meta' => $meta])
                @endif

                @if ($torrent->category->game_meta)
                    <img
                        style="height: 80px"
                        src="{{ isset($meta->cover_image_id) ? 'https://images.igdb.com/igdb/image/upload/t_cover_small_2x/' . $meta->cover_image_id . '.png' : url('img/sin-imagen.svg') }}"
                        class="torrent-search--list__poster-img"
                        loading="lazy"
                        alt="{{ __('torrent.similar') }}"
                    />
                    @if ($meta instanceof \App\Models\IgdbGame)
                        @include('torrent.partials.game-meta-popup', ['meta' => $meta])
                    @endif
                @endif

                @if ($torrent->category->book_meta || $torrent->category->audiobook_meta)
                    <img
                        {{-- Se pide un tamaño, no "la portada": esto pinta 90x135
                             y estaba trayendo la de 2177 px. `coverAtLeast` elige
                             la más pequeña que llegue al ancho pedido. --}}
                        src="{{ $meta?->cover_url ? tmdb_image('poster_small', method_exists($meta, 'coverAtLeast') ? ($meta->coverAtLeast(300) ?? $meta->cover_url) : $meta->cover_url) : url('img/sin-imagen.svg') }}"
                        class="torrent-search--list__poster-img"
                        loading="lazy"
                        alt="{{ __('torrent.similar') }}"
                    />
                    @if ($meta !== null)
                        @include('torrent.partials.book-meta-popup', ['meta' => $meta])
                    @endif
                @endif

                @if ($torrent->category->music_meta)
                    <img
                        src="{{ url('img/sin-imagen.svg') }}"
                        class="torrent-search--list__poster-img"
                        loading="lazy"
                        alt="{{ __('torrent.similar') }}"
                    />
                @endif

                @if ($torrent->category->no_meta)
                    @if (Storage::disk('torrent-covers')->exists("torrent-cover_$torrent->id.jpg"))
                        <img
                            src="{{ route('authenticated_images.torrent_cover', ['id' => $torrent->id]) }}"
                            class="torrent-search--list__poster-img"
                            loading="lazy"
                            alt="{{ __('torrent.similar') }}"
                        />
                    @else
                        <img
                            src="{{ url('img/sin-imagen.svg') }}"
                            class="torrent-search--list__poster-img"
                            loading="lazy"
                            alt="{{ __('torrent.similar') }}"
                        />
                    @endif
                @endif
            </a>
        </td>
    @endif

    <td class="torrent-search--list__format">
        <div>
            <div class="torrent-search--list__category">
                @if ($torrent->category->image !== null)
                    <img
                        src="{{ route('authenticated_images.category_image', ['category' => $torrent->category]) }}"
                        title="{{ $torrent->category->name }} {{ strtolower(__('torrent.torrent')) }}"
                        alt="{{ $torrent->category->name }}"
                        loading="lazy"
                        @style([
                            'height: 32px',
                            'padding-top: 1px' => $torrent->category->movie_meta || $torrent->category->tv_meta,
                            'padding-top: 12px' => ! ($torrent->category->movie_meta || $torrent->category->tv_meta),
                        ])
                    />
                @else
                    <i
                        class="{{ $torrent->category->icon }} category__icon"
                        @style([
                            'font-size: 24px',
                            'padding-top: 1px' => $torrent->category->movie_meta || $torrent->category->tv_meta,
                            'padding-top: 12px' => ! ($torrent->category->movie_meta || $torrent->category->tv_meta),
                        ])
                    ></i>
                @endif
            </div>
            <div class="torrent-search--list__resolution-and-type">
                @if ($torrent->category->movie_meta || $torrent->category->tv_meta)
                    <span class="torrent-search--list__resolution">
                        {{ $torrent->resolution->name ?? 'No res' }}
                    </span>
                @endif

                <span class="torrent-search--list__type">
                    {{ $torrent->type->name }}
                </span>
            </div>
        </div>
    </td>

    {{-- Overview: name + uploader + hover quick-view popup --}}
    <td
        class="torrent-search--list__overview"
        @if ($hasQuickView)
            x-data="{ namePopup: false, enterTimer: null, leaveTimer: null, trailerError: false }"
            x-init="window.addEventListener('message', e => {
                if (!$refs.trailerFrame || e.source !== $refs.trailerFrame.contentWindow) return;
                try { const d = JSON.parse(e.data); if (d.event === 'onError' && (d.info === 150 || d.info === 101)) trailerError = true; } catch {}
            })"
            x-on:mouseenter="clearTimeout(leaveTimer); enterTimer = setTimeout(() => namePopup = true, 400)"
            x-on:mouseleave="clearTimeout(enterTimer); leaveTimer = setTimeout(() => namePopup = false, 150)"
        @endif
    >
        <div>
            <a
                class="torrent-search--list__name"
                href="{{ route('torrents.show', ['id' => $torrent->id]) }}"
            >
                {{ $torrent->name }}
            </a>
            <x-user-tag
                class="torrent-search--list__uploader"
                :user="$torrent->user"
                :anon="$torrent->anon"
            />
            @include('components.partials._torrent-icons')
        </div>

        {{-- Hover popup: mirrors meta__poster-popup pattern (position: fixed, pointer-events: none) --}}
        @if ($hasQuickView)
            <div class="meta__poster-popup" x-show="namePopup" x-cloak>
                <div class="meta__poster-popup-card" style="width: 560px;">

                    {{-- Backdrop: muted autoplay trailer > movie backdrop > movie poster --}}
                    @if ($trailerKey)
                        <div
                            class="meta__poster-popup-backdrop"
                            @if (isset($meta->backdrop) || isset($meta->poster))
                                style="background: url('{{ isset($meta->backdrop) ? tmdb_image('back_mid', $meta->backdrop) : tmdb_image('poster_mid', $meta->poster) }}') center / cover no-repeat;"
                            @endif
                        >
                            {{-- Iframe: cleared on hide so nothing plays in background; enablejsapi fires error 150 on geo-block --}}
                            <iframe
                                x-ref="trailerFrame"
                                x-show="!trailerError"
                                style="width:100%; height:100%; border:0; display:block; position:absolute; top:0; left:0; z-index:1;"
                                :src="namePopup && !trailerError ? 'https://www.youtube-nocookie.com/embed/{{ $trailerKey }}?autoplay=1&mute=1&controls=0&modestbranding=1&loop=1&playlist={{ $trailerKey }}&rel=0&enablejsapi=1' : ''"
                                allow="autoplay; encrypted-media"
                                tabindex="-1"
                            ></iframe>
                            {{-- Fallback shown when geo-restricted (error 150/101) --}}
                            @if (isset($meta->backdrop) || isset($meta->poster))
                                <img
                                    x-show="trailerError"
                                    x-cloak
                                    src="{{ isset($meta->backdrop) ? tmdb_image('back_mid', $meta->backdrop) : tmdb_image('poster_mid', $meta->poster) }}"
                                    style="width:100%; height:100%; object-fit:cover; position:absolute; top:0; left:0; z-index:1;"
                                    alt=""
                                />
                            @endif
                            <div class="meta__poster-popup-backdrop-overlay" style="z-index:2;"></div>
                        </div>
                    @elseif ($quickBook?->cover_url)
                        <div class="meta__poster-popup-backdrop meta__poster-popup-backdrop--cover">
                            <span
                                class="meta__poster-popup-backdrop-blur"
                                style="background-image: url('{{ tmdb_image('poster_mid', $quickBook->cover_url) }}')"
                            ></span>
                            <img src="{{ tmdb_image('poster_big', $quickBook->cover_url) }}" alt="{{ $quickBook->title }}" />
                            <div class="meta__poster-popup-backdrop-overlay"></div>
                        </div>
                    @elseif (isset($meta->backdrop) || isset($meta->poster))
                        <div class="meta__poster-popup-backdrop">
                            <img
                                src="{{ isset($meta->backdrop) ? tmdb_image('back_mid', $meta->backdrop) : tmdb_image('poster_mid', $meta->poster) }}"
                                alt="{{ $meta->title ?? ($meta->name ?? '') }}"
                            />
                            <div class="meta__poster-popup-backdrop-overlay"></div>
                        </div>
                    @endif

                    {{-- Compact technical specs --}}
                    @if ($quickMI !== null)
                        <div class="meta__poster-popup-content" style="padding: 12px 20px;">
                            <dl style="display: grid; grid-template-columns: auto 1fr; column-gap: 12px; row-gap: 5px; font-size: 13px; margin: 0;">
                                @if ($quickMI['general']['format'] ?? null)
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">Format</dt>
                                    <dd style="margin: 0;">{{ $quickMI['general']['format'] }}</dd>
                                @endif
                                @if (($quickMI['video'][0]['width'] ?? null) && ($quickMI['video'][0]['height'] ?? null))
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">Video</dt>
                                    <dd style="margin: 0;">
                                        {{ $quickMI['video'][0]['format'] ?? '' }}
                                        {{ $quickMI['video'][0]['width'] }}×{{ $quickMI['video'][0]['height'] }}
                                        @if ($quickMI['video'][0]['bit_depth'] ?? null)
                                            · {{ $quickMI['video'][0]['bit_depth'] }}bit
                                        @endif
                                    </dd>
                                @endif
                                @if (!empty($quickMI['audio']))
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">Audio</dt>
                                    <dd style="margin: 0; display: flex; gap: 5px; flex-wrap: wrap; align-items: center;">
                                        @foreach ($quickMI['audio'] as $a)
                                            @php $flagSrc = language_flag($a['language'] ?? null); @endphp
                                            @if ($flagSrc !== null)
                                                <img
                                                    src="{{ $flagSrc }}"
                                                    width="18" height="12"
                                                    alt="{{ $a['language'] }}"
                                                    title="{{ $a['language'] }} · {{ $a['format'] ?? '—' }} · {{ $a['channels'] ?? '—' }}"
                                                />
                                            @endif
                                        @endforeach
                                    </dd>
                                @endif
                                @if (!empty($quickMI['text']))
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">Subs</dt>
                                    <dd style="margin: 0; display: flex; gap: 5px; flex-wrap: wrap; align-items: center;">
                                        @foreach ($quickMI['text'] as $t)
                                            @php $flagSrc = language_flag($t['language'] ?? null); @endphp
                                            @if ($flagSrc !== null)
                                                <img
                                                    src="{{ $flagSrc }}"
                                                    width="18" height="12"
                                                    alt="{{ $t['language'] }}"
                                                    title="{{ $t['language'] }}"
                                                />
                                            @endif
                                        @endforeach
                                    </dd>
                                @endif
                                @if ($quickMI['general']['duration'] ?? null)
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">Duration</dt>
                                    <dd style="margin: 0;">{{ $quickMI['general']['duration'] }}</dd>
                                @endif
                                @if ($quickMI['general']['file_size'] ?? null)
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">Size</dt>
                                    <dd style="margin: 0;">{{ App\Helpers\StringHelper::formatBytes($quickMI['general']['file_size'], 2) }}</dd>
                                @endif
                            </dl>
                        </div>
                    @endif

                    {{-- Mismas rejilla, cuerpo y tipografía que el bloque de
                         mediainfo de arriba: lo que cambia son las filas, no
                         la forma. --}}
                    @if ($quickBook !== null)
                        <div class="meta__poster-popup-content" style="padding: 12px 20px;">
                            <dl style="display: grid; grid-template-columns: auto 1fr; column-gap: 12px; row-gap: 5px; font-size: 13px; margin: 0;">
                                <dt style="color: #bbb; text-align: right; white-space: nowrap;">{{ __('torrent.author') }}</dt>
                                <dd style="margin: 0;">{{ $quickBook->authorLine() ?: '—' }}</dd>

                                @if ($quickBook instanceof \App\Models\Audiobook && $quickBook->narrators)
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">{{ __('torrent.narrator') }}</dt>
                                    <dd style="margin: 0;">{{ $quickBook->narratorLine() }}</dd>
                                @endif

                                <dt style="color: #bbb; text-align: right; white-space: nowrap;">Formato</dt>
                                <dd style="margin: 0;">{{ $torrent->type->name }}</dd>

                                @if ($quickBook instanceof \App\Models\Audiobook)
                                    @if ($quickBook->runtimeForHumans())
                                        <dt style="color: #bbb; text-align: right; white-space: nowrap;">{{ __('torrent.runtime') }}</dt>
                                        <dd style="margin: 0;">{{ $quickBook->runtimeForHumans() }}</dd>
                                    @endif
                                @elseif ($quickBook->page_count)
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">{{ ucfirst(__('torrent.pages')) }}</dt>
                                    <dd style="margin: 0;">{{ $quickBook->page_count }}</dd>
                                @endif

                                @php
                                    $quickLang = $quickBook instanceof \App\Models\Audiobook
                                        ? $quickBook->language
                                        : implode(', ', $quickBook->languages ?? []);
                                @endphp
                                @if ($quickLang)
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">Idioma</dt>
                                    <dd style="margin: 0;">{{ strtoupper($quickLang) }}</dd>
                                @endif

                                @if ($quickBook instanceof \App\Models\Audiobook)
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">ASIN</dt>
                                    <dd style="margin: 0;">{{ $quickBook->asin }}</dd>
                                @else
                                    <dt style="color: #bbb; text-align: right; white-space: nowrap;">ISBN-13</dt>
                                    <dd style="margin: 0;">{{ $quickBook->isbn13 }}</dd>
                                @endif

                                <dt style="color: #bbb; text-align: right; white-space: nowrap;">Tamaño</dt>
                                <dd style="margin: 0;">{{ App\Helpers\StringHelper::formatBytes($torrent->size, 2) }}</dd>
                            </dl>
                        </div>
                    @endif

                </div>
            </div>
        @endif
    </td>

    <td class="torrent-search--list__buttons">
        <div>
            @if (auth()->user()->group->is_editor || auth()->user()->group->is_modo || auth()->id() === $torrent->user_id)
                <a
                    class="torrent-search--list__edit form__standard-icon-button"
                    href="{{ route('torrents.edit', ['id' => $torrent->id]) }}"
                    title="{{ __('common.edit') }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-pencil-alt"></i>
                </a>
            @endif

            <button
                class="form__standard-icon-button"
                x-data="bookmark({{ $torrent->id }}, {{ Js::from($torrent->bookmarks_exists) }})"
                x-bind="button"
            >
                <i class="{{ config('other.font-awesome') }}" x-bind="icon"></i>
            </button>

            @if (config('torrent.download_check_page'))
                <a
                    class="torrent-search--list__file form__standard-icon-button"
                    href="{{ route('download_check', ['id' => $torrent->id]) }}"
                    title="{{ __('common.download') }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-download"></i>
                </a>
            @else
                <a
                    class="torrent-search--list__file form__standard-icon-button"
                    href="{{ route('download', ['id' => $torrent->id]) }}"
                    title="{{ __('common.download') }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-download"></i>
                </a>
            @endif
            @if (config('torrent.magnet'))
                <a
                    class="torrent-search--list__magnet form__contained-icon-button form__contained-icon-button--filled"
                    href="magnet:?dn={{ $torrent->name }}&xt=urn:btih:{{ bin2hex($torrent->info_hash) }}&as={{ route('torrent.download.rsskey', ['id' => $torrent->id, 'rsskey' => auth()->user()->rsskey]) }}&tr={{ route('announce', ['passkey' => auth()->user()->passkey]) }}&xl={{ $torrent->size }}"
                    download
                    title="{{ __('common.magnet') }}"
                >
                    <i class="{{ config('other.font-awesome') }} fa-magnet"></i>
                </a>
            @endif

            {{-- Quick View: film button + full dialog (lite-embed trailer) --}}
            @if ($hasQuickView)
                <div x-data="{ playing: false }" wire:ignore style="display: contents">
                    <button
                        class="form__standard-icon-button"
                        x-on:click.stop="$refs.quickview.showModal()"
                        title="Quick View"
                    >
                        <i class="{{ config('other.font-awesome') }} fa-film"></i>
                    </button>
                    <dialog
                        x-ref="quickview"
                        class="dialog"
                        style="width: min(90vw, 960px); max-width: none;"
                        x-on:click="if ($event.target === $el) $el.close()"
                        x-on:close="playing = false"
                    >
                        <div class="dialog__form">
                            <header class="dialog__header">
                                <h2 class="dialog__heading">{{ $torrent->name }}</h2>
                                <div class="dialog__actions">
                                    <button class="form__button form__button--text" x-on:click="$refs.quickview.close()">✕</button>
                                </div>
                            </header>
                            <div
                                class="dialog__body"
                                style="display: grid; grid-template-columns: {{ ($trailerKey && $quickMI) ? '1fr 1fr' : '1fr' }}; gap: 20px; align-items: start;"
                            >
                                @if ($trailerKey)
                                    <div>
                                        <h3 style="margin-bottom: 8px;">🎬 Trailer</h3>

                                        {{-- Thumbnail (shown until clicked) --}}
                                        <template x-if="!playing">
                                            <div
                                                style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; cursor: pointer; background: #000; border-radius: 4px;"
                                                x-on:click="playing = true"
                                            >
                                                <img
                                                    src="https://i.ytimg.com/vi/{{ $trailerKey }}/maxresdefault.jpg"
                                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;"
                                                    alt="Trailer thumbnail"
                                                    x-on:error="$el.src = 'https://i.ytimg.com/vi/{{ $trailerKey }}/hqdefault.jpg'"
                                                />
                                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none;">
                                                    <svg width="68" height="48" viewBox="0 0 68 48" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M66.52,7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13,34,0,34,0S12.21.13,6.9,1.55C3.97,2.33,2.27,4.81,1.48,7.74.06,13.05,0,24,0,24s.06,10.95,1.48,16.26c.78,2.93,2.49,5.41,5.42,6.19C12.21,47.87,34,48,34,48s21.79-.13,27.1-1.55c2.93-.78,4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74Z" fill="#f00" opacity=".9"/>
                                                        <path d="M 45,24 27,14 27,34" fill="#fff"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- iframe (created only after click, autoplay=1) --}}
                                        <template x-if="playing">
                                            <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 4px;">
                                                <iframe
                                                    src="https://www.youtube-nocookie.com/embed/{{ $trailerKey }}?autoplay=1"
                                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;"
                                                    allow="autoplay; encrypted-media"
                                                    allowfullscreen
                                                ></iframe>
                                            </div>
                                        </template>
                                    </div>
                                @endif

                                @if ($quickMI !== null)
                                    <div>
                                        <h3 style="margin-bottom: 8px;">
                                            <i class="{{ config('other.font-awesome') }} fa-info-square"></i>
                                            Ficha Técnica
                                        </h3>
                                        <section class="mediainfo">
                                            @isset($quickMI['general']['file_name'])
                                                <section class="mediainfo__filename">
                                                    <h3>Filename</h3>
                                                    {{ $quickMI['general']['file_name'] }}
                                                </section>
                                            @endisset
                                            <section class="mediainfo__general">
                                                <h3>General</h3>
                                                <dl>
                                                    <dt>Format</dt><dd>{{ $quickMI['general']['format'] ?? '—' }}</dd>
                                                    <dt>Duration</dt><dd>{{ $quickMI['general']['duration'] ?? '—' }}</dd>
                                                    <dt>Bitrate</dt><dd>{{ $quickMI['general']['bit_rate'] ?? '—' }}</dd>
                                                    <dt>Size</dt><dd>{{ App\Helpers\StringHelper::formatBytes($quickMI['general']['file_size'] ?? 0, 2) }}</dd>
                                                </dl>
                                            </section>
                                            @isset($quickMI['video'])
                                                <section class="mediainfo__video">
                                                    <h3>Video</h3>
                                                    @foreach ($quickMI['video'] as $v)
                                                        <dl>
                                                            <dt>Format</dt><dd>{{ ($v['format'] ?? '—') }} ({{ $v['bit_depth'] ?? '—' }} bits)</dd>
                                                            <dt>Resolution</dt><dd>{{ ($v['width'] ?? '—') }} × {{ ($v['height'] ?? '—') }}</dd>
                                                            <dt>Aspect ratio</dt><dd>{{ $v['aspect_ratio'] ?? '—' }}</dd>
                                                            <dt>Frame rate</dt>
                                                            <dd>{{ (isset($v['framerate_mode']) && $v['framerate_mode'] === 'Variable') ? 'VFR' : ($v['frame_rate'] ?? '—') }}</dd>
                                                            <dt>Bit rate</dt><dd>{{ $v['bit_rate'] ?? '—' }}</dd>
                                                        </dl>
                                                    @endforeach
                                                </section>
                                            @endisset
                                            @isset($quickMI['audio'])
                                                <section class="mediainfo__audio">
                                                    <h3>Audio</h3>
                                                    <dl>
                                                        @foreach ($quickMI['audio'] as $a)
                                                            <dt>{{ $loop->iteration }}.</dt>
                                                            <dd>
                                                                @php $flagSrc = language_flag($a['language'] ?? null); @endphp
                                                                @if ($flagSrc !== null)
                                                                    <img src="{{ $flagSrc }}" width="20" height="13" alt="{{ $a['language'] }}" />
                                                                @endif
                                                                {{ $a['language'] ?? '—' }} / {{ $a['format'] ?? '—' }} / {{ $a['channels'] ?? '—' }} / {{ $a['bit_rate'] ?? '—' }}
                                                            </dd>
                                                        @endforeach
                                                    </dl>
                                                </section>
                                            @endisset
                                            @isset($quickMI['text'])
                                                <section class="mediainfo__subtitles">
                                                    <h3>Subtitles</h3>
                                                    <ul>
                                                        @foreach ($quickMI['text'] as $t)
                                                            <li>
                                                                @php $flagSrc = language_flag($t['language'] ?? null); @endphp
                                                                @if ($flagSrc !== null)
                                                                    <img src="{{ $flagSrc }}" width="20" height="13" alt="{{ $t['language'] }}" title="{{ $t['language'] }}" />
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </section>
                                            @endisset
                                        </section>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </dialog>
                </div>
            @endif
        </div>
    </td>

    @if ($torrent->category->game_meta)
        <td
            class="torrent-search--list__rating {{ rating_color($meta->rating ?? 0) ?? 'text-white' }}"
        >
            <span>{{ round($meta->rating ?? 0) }}%</span>
        </td>
    @elseif ($torrent->category->movie_meta || $torrent->category->tv_meta)
        <td class="torrent-search--list__rating" title="{{ $meta->vote_count ?? 0 }} votes">
            <span class="{{ rating_color($meta->vote_average ?? 0) ?? 'text-white' }}">
                {{ round(($meta->vote_average ?? 0) * 10) }}%
            </span>
        </td>
    @else
        <td class="torrent-search--list__rating">N/A</td>
    @endif
    <td class="torrent-search--list__size">
        <span>{{ $torrent->getSize() }}</span>
    </td>
    <td
        @class([
            'torrent-search--list__seeders',
            'torrent-activity-indicator--seeding' => $torrent->seeding,
        ])
        @if ($torrent->seeding)
            title="{{ __('torrent.currently-seeding') }}"
        @endif
    >
        <a class="torrent__seeder-count" href="{{ route('peers', ['id' => $torrent->id]) }}">
            {{ $torrent->seeds_count ?? $torrent->seeders }}
        </a>
    </td>
    <td
        @class([
            'torrent-search--list__leechers',
            'torrent-activity-indicator--leeching' => $torrent->leeching,
        ])
        @if ($torrent->leeching)
            title="{{ __('torrent.currently-leeching') }}"
        @endif
    >
        <a class="torrent__leecher-count" href="{{ route('peers', ['id' => $torrent->id]) }}">
            {{ $torrent->leeches_count ?? $torrent->leechers }}
        </a>
    </td>
    <td
        @class([
            'torrent-search--list__completed',
            'torrent-activity-indicator--completed' => $torrent->completed,
        ])
        @if ($torrent->completed)
            title="{{ __('torrent.completed') }}"
        @endif
    >
        <a
            class="torrent__times-completed-count"
            href="{{ route('history', ['id' => $torrent->id]) }}"
        >
            {{ $torrent->times_completed }}
        </a>
    </td>
    <td class="torrent-search--list__age">
        <time datetime="{{ $torrent->created_at }}" title="{{ $torrent->created_at }}">
            {{ $torrent->created_at->diffForHumans() }}
        </time>
    </td>
</tr>
