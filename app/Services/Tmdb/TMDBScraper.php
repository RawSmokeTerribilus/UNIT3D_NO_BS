<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

/**
 * MODIFICADO PARA NOBS
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
 * Se distribuye bajo la misma licencia, GNU AGPL v3.0.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Services\Tmdb;

use App\Jobs\ProcessMovieJob;
use App\Jobs\ProcessTvJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class TMDBScraper implements ShouldQueue
{
    use SerializesModels;

    public function __construct()
    {
    }

    public function tv(int $id, bool $force = false): void
    {
        if ($force) {
            cache()->forget("tmdb-tv-scraper:{$id}");
        }

        ProcessTvJob::dispatch($id, $force);
    }

    public function movie(int $id, bool $force = false): void
    {
        if ($force) {
            cache()->forget("tmdb-movie-scraper:{$id}");
        }

        ProcessMovieJob::dispatch($id, $force);
    }
}
