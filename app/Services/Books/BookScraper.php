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

namespace App\Services\Books;

use App\Jobs\ProcessBookJob;
use App\Services\Books\Support\Isbn;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Entry point for populating book metadata, mirroring IgdbScraper so callers
 * do not have to know which job does the work.
 */
class BookScraper implements ShouldQueue
{
    use SerializesModels;

    public function book(string $isbn, bool $force = false): void
    {
        $isbn13 = Isbn::toIsbn13($isbn);

        if ($isbn13 === '') {
            return;
        }

        if ($force) {
            cache()->forget("book-scraper:{$isbn13}");
        }

        ProcessBookJob::dispatch($isbn13);
    }
}
