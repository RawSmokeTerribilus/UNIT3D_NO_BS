<?php

declare(strict_types=1);

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
