{{--
    Tarjeta flotante de juego para el listado de torrents.

    Misma estructura y mismas clases que meta-popup.blade.php, igual que la de
    libro: card de 500px, meta__poster-popup-* y detail-label/detail-value.

    A diferencia del libro, un juego SÍ tiene arte apaisado —el artwork de
    IGDB— así que cuando lo hay va tal cual en la banda. Si sólo hay portada,
    que es vertical, se cae a la variante difuminada.

    $meta llega hidratado desde TorrentMeta::scopeMeta(); si es null el llamante
    no incluye este partial.
--}}
@php
    $gameArtwork = $meta->first_artwork_image_id
        ? 'https://images.igdb.com/igdb/image/upload/t_screenshot_med/'.$meta->first_artwork_image_id.'.jpg'
        : null;

    $gameCover = $meta->cover_image_id
        ? 'https://images.igdb.com/igdb/image/upload/t_cover_big/'.$meta->cover_image_id.'.jpg'
        : null;
@endphp

<div x-cloak x-show="metaPopup" class="meta__poster-popup">
    <div class="meta__poster-popup-card">
        @if ($gameArtwork)
            <div class="meta__poster-popup-backdrop">
                <img src="{{ $gameArtwork }}" alt="{{ $meta->name }}" loading="lazy" />
                <div class="meta__poster-popup-backdrop-overlay"></div>
            </div>
        @elseif ($gameCover)
            <div class="meta__poster-popup-backdrop meta__poster-popup-backdrop--cover">
                <span class="meta__poster-popup-backdrop-blur" style="background-image: url('{{ $gameCover }}')"></span>
                <img src="{{ $gameCover }}" alt="{{ $meta->name }}" loading="lazy" />
                <div class="meta__poster-popup-backdrop-overlay"></div>
            </div>
        @endif

        <div class="meta__poster-popup-content">
            <div class="meta__poster-popup-header">
                <h3 class="meta__poster-popup-title">
                    {{ $meta->name }}
                    @if ($meta->first_release_date)
                        <span class="meta__poster-popup-year">({{ $meta->first_release_date->format('Y') }})</span>
                    @endif
                </h3>
            </div>

            <p class="meta__poster-popup-overview">
                {{ \Illuminate\Support\Str::limit($meta->summary ?? '', 320) ?: __('common.no-meta-found') }}
            </p>

            <div class="meta__poster-popup-details">
                @if ($meta->rating)
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.rating') }}</span>
                        <span class="detail-value">
                            {{ round((float) $meta->rating) }}%
                            ({{ $meta->rating_count ?? 0 }} {{ strtolower(__('torrent.votes')) }})
                        </span>
                    </div>
                @endif

                @if ($meta->platforms->isNotEmpty())
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('mediahub.platforms') }}</span>
                        <span class="detail-value">{{ $meta->platforms->pluck('name')->join(', ') }}</span>
                    </div>
                @endif

                @if ($meta->genres->isNotEmpty())
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('torrent.genres') }}</span>
                        <span class="detail-value">{{ $meta->genres->pluck('name')->join(', ') }}</span>
                    </div>
                @endif

                @if ($meta->companies->isNotEmpty())
                    <div class="meta__poster-popup-detail">
                        <span class="detail-label">{{ __('mediahub.game-companies') }}</span>
                        <span class="detail-value">{{ $meta->companies->take(4)->pluck('name')->join(', ') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
