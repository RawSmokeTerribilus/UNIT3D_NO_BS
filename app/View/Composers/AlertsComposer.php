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

namespace App\View\Composers;

use App\Models\Torrent;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AlertsComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        // Las URLs de abajo van firmadas contra el host de la peticion en
        // curso, y el resultado se guarda en una cache GLOBAL de 15/30 min.
        // Basta con que una sola peticion llegue con otro Host -- un
        // healthcheck, un cron, una consola -- para que todos los usuarios se
        // coman durante media hora un banner que apunta a un dominio que no es
        // el suyo: cross-site, sin cookie de sesion, 401.
        //
        // Ocurrio el 2026-08-21 en staging. Anclar la raiz al APP_URL hace que
        // la cache sea correcta la genere quien la genere.
        URL::forceRootUrl(config('app.url'));

        $backdrops = cache()->flexible(
            'cached-alert-backdrops',
            [900, 1800],
            fn (): array => Torrent::query()
                ->select(['id', 'tmdb_movie_id', 'tmdb_tv_id', 'seeders', 'times_completed'])
                ->with([
                    'movie:id,backdrop',
                    'tv:id,backdrop',
                ])
                ->where(fn ($query) => $query
                    ->whereNotNull('tmdb_movie_id')
                    ->orWhereNotNull('tmdb_tv_id')
                )
                ->orderByDesc('seeders')
                ->orderByDesc('times_completed')
                ->limit(100)
                ->get()
                ->map(fn (Torrent $torrent): ?string => $torrent->movie?->backdrop ?? $torrent->tv?->backdrop)
                ->filter()
                ->map(fn (string $backdrop): string => tmdb_image('back_small', $backdrop))
                ->unique()
                ->take(24)
                ->values()
                ->all(),
        );

        $view->with([
            'backdrops' => $backdrops,
        ]);
    }
}
