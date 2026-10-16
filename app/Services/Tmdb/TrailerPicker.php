<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Services\Tmdb;

/**
 * Criterio ÚNICO para elegir el tráiler de una ficha de TMDB.
 *
 * Lo usan el refresco normal de metadatos (Client\Movie y Client\TV) y el
 * rehidratado (`tmdb:sync-trailers`). Antes cada uno elegía a su manera y el
 * refresco, que sólo veía vídeos en castellano, borraba cada mes lo que el
 * rehidratado encontraba en inglés.
 *
 * Quien pida los vídeos tiene que pedirlos con VIDEO_LANGUAGES
 * (`include_video_language`), o TMDB sólo devuelve los del idioma de la ficha.
 */
final class TrailerPicker
{
    public const VIDEO_LANGUAGES = 'es,en,null';

    /**
     * Prioridad: oficial en castellano → oficial en inglés → oficial en
     * cualquier idioma → no oficial en castellano → cualquiera. Los tráilers
     * en castellano los suben canales españoles/europeos y no suelen tener
     * bloqueo geográfico para ES/UE.
     *
     * @param array<int, array<string, mixed>> $results `videos.results` de TMDB
     */
    public static function pick(array $results): ?string
    {
        $trailers = collect($results)
            ->filter(fn ($v) => ($v['type'] ?? '') === 'Trailer' && ($v['site'] ?? '') === 'YouTube');

        foreach (['es', 'en'] as $lang) {
            $key = $trailers
                ->filter(fn ($v) => ($v['iso_639_1'] ?? '') === $lang && ($v['official'] ?? false))
                ->sortByDesc('published_at')
                ->first()['key'] ?? null;

            if ($key !== null) {
                return $key;
            }
        }

        $key = $trailers->filter(fn ($v) => $v['official'] ?? false)->sortByDesc('published_at')->first()['key'] ?? null;

        if ($key !== null) {
            return $key;
        }

        return $trailers
            ->sortByDesc(fn ($v) => ($v['iso_639_1'] ?? '') === 'es' ? 1 : 0)
            ->first()['key'] ?? null;
    }
}
