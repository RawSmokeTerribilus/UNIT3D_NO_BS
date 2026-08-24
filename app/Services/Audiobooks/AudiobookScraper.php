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

namespace App\Services\Audiobooks;

use App\Jobs\ProcessAudiobookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Entry point for populating audiobook metadata.
 */
class AudiobookScraper implements ShouldQueue
{
    use SerializesModels;

    public function audiobook(string $asin, string $region = 'es', bool $force = false): void
    {
        $asin = strtoupper(trim($asin));

        if ($asin === '') {
            return;
        }

        if ($force) {
            cache()->forget("audiobook-scraper:{$asin}");
        }

        ProcessAudiobookJob::dispatch($asin, $region);
    }
}
