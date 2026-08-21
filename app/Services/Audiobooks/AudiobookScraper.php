<?php

declare(strict_types=1);

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
