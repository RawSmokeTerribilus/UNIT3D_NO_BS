<?php

declare(strict_types=1);

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
