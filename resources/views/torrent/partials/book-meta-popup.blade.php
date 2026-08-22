{{--
    Tarjeta flotante de libro / audiolibro para el listado de torrents.

    Calca meta-popup.blade.php clase por clase a propósito: mismo card de
    500px, mismas tipografías y el mismo bloque de detalles. Lo único propio
    es el tratamiento del arte, porque la portada de un libro es vertical y
    el hueco del backdrop es una banda de 180px: recortarla a lo ancho
    decapita la cubierta. Se rellena con la misma imagen difuminada y la
    portada nítida encima, contenida.

    $meta llega ya hidratado desde TorrentMeta::scopeMeta(); si es null el
    llamante no incluye este partial.
--}}
@php
    $isAudiobook = $meta instanceof \App\Models\Audiobook;

    $popupYear = $isAudiobook
        ? $meta->release_date?->format('Y')
        : $meta->first_publish_year;

    $popupCover = $meta->cover_url;

    $popupGenres = $isAudiobook
        ? implode(', ', $meta->genres ?? [])
        : ($meta->genres?->pluck('name')->join(', ') ?? '');
@endphp

<div x-cloak x-show="metaPopup" class="meta__poster-popup">
    <div class="meta__poster-popup-card">
        <div class="meta__poster-popup-backdrop meta__poster-popup-backdrop--cover">
            @if ($popupCover)
                <span
                    class="meta__poster-popup-backdrop-blur"
                    style="background-image: url('{{ tmdb_image('poster_mid', $popupCover) }}')"
                ></span>
                <img src="{{ tmdb_image('poster_big', $popupCover) }}" alt="{{ $meta->title }}" />
            @else
                <img src="https://via.placeholder.com/500x280" alt="" />
            @endif
            <div class="meta__poster-popup-backdrop-overlay"></div>
        </div>

        <div class="meta__poster-popup-content">
            <div class="meta__poster-popup-header">
                <h3 class="meta__poster-popup-title">
                    {{ $meta->title }}
                    @if ($popupYear)
                        <span class="meta__poster-popup-year">({{ $popupYear }})</span>
                    @endif
                </h3>
            </div>

            @if ($meta->subtitle)
                <p class="meta__poster-popup-overview" style="margin-bottom: 12px; font-style: italic;">
                    {{ $meta->subtitle }}
                </p>
            @endif

            <p class="meta__poster-popup-overview">
                {{ \Illuminate\Support\Str::limit($meta->description ?? '', 320) ?: __('common.no-meta-found') }}
            </p>

            <div class="meta__poster-popup-details">
                @if ($meta->authors)
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.author') }}</span>
                        <span class="detail-value">{{ $meta->authorLine() }}</span>
                    </div>
                @endif

                @if ($isAudiobook && $meta->narrators)
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.narrator') }}</span>
                        <span class="detail-value">{{ $meta->narratorLine() }}</span>
                    </div>
                @endif

                @if ($isAudiobook && $meta->runtime_length_min)
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.runtime') }}</span>
                        <span class="detail-value">{{ $meta->runtimeForHumans() }}</span>
                    </div>
                @endif

                @if (!$isAudiobook && $meta->page_count)
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.pages') }}</span>
                        <span class="detail-value">{{ $meta->page_count }}</span>
                    </div>
                @endif

                @if (!$isAudiobook && $meta->ratingPercent() !== null)
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.rating') }}</span>
                        <span class="detail-value">
                            {{ round((float) $meta->average_rating, 1) }}/5
                            ({{ $meta->ratings_count ?? 0 }} {{ strtolower(__('torrent.votes')) }})
                        </span>
                    </div>
                @endif

                @if ($popupGenres !== '')
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.genres') }}</span>
                        <span class="detail-value">{{ $popupGenres }}</span>
                    </div>
                @endif

                @if ($meta->series)
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.series') }}</span>
                        <span class="detail-value">
                            {{ $meta->series }}{{ $meta->series_position ? ' #'.$meta->series_position : '' }}
                        </span>
                    </div>
                @endif

                @if ($meta->publisher)
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.publisher') }}</span>
                        <span class="detail-value">{{ $meta->publisher }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
