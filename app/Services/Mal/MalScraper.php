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

namespace App\Services\Mal;

use App\Jobs\ProcessMalJob;

class MalScraper
{
    public function anime(int $id, bool $force = false): void
    {
        if ($force) {
            cache()->forget("mal-anime-scraper:{$id}");
        }

        ProcessMalJob::dispatch($id, $force);
    }
}
