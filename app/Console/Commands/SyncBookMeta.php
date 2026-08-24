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

namespace App\Console\Commands;

use App\Jobs\ProcessAudiobookJob;
use App\Jobs\ProcessBookJob;
use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Torrent;
use App\Services\Audiobooks\AudibleClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill the metadata rows behind e-book and audiobook torrents.
 *
 * Missing-only by default: a torrent counts as pending when it carries an id
 * but has no matching row yet. --force re-fetches every id instead, which is
 * how a provider correction gets picked up.
 *
 * Paced deliberately. Google Books allows 1000 requests a day on the free
 * tier, and OMDb taught this codebase what happens when a backfill ignores a
 * quota: it burns the day in about 250 torrents and every later lookup fails.
 */
class SyncBookMeta extends Command
{
    protected $signature = 'books:sync
                            {--limit=100 : How many torrents of each kind to process}
                            {--force : Re-fetch ids that already have a row}
                            {--queue : Dispatch to the queue instead of running inline}';

    protected $description = 'Backfill book and audiobook metadata for torrents that carry an ISBN or ASIN';

    final public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $queued = (bool) $this->option('queue');

        $books = $this->pending('isbn13', Book::class, 'isbn13', $limit, $force);
        $audiobooks = $this->pending('asin', Audiobook::class, 'asin', $limit, $force);

        $this->info(sprintf('%d book(s) and %d audiobook(s) to process.', \count($books), \count($audiobooks)));

        $region = AudibleClient::defaultRegion();
        $ok = 0;
        $failed = 0;

        foreach ($books as $isbn13) {
            $this->dispatchOne(new ProcessBookJob($isbn13), $queued, $force, "book-scraper:{$isbn13}", $isbn13, $ok, $failed);
        }

        foreach ($audiobooks as $asin) {
            $this->dispatchOne(new ProcessAudiobookJob($asin, $region), $queued, $force, "audiobook-scraper:{$asin}", $asin, $ok, $failed);
        }

        $this->info(sprintf('Done. %d ok, %d failed.', $ok, $failed));

        return self::SUCCESS;
    }

    /**
     * Ids present on a torrent but missing from the metadata table.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     *
     * @return list<string>
     */
    private function pending(string $column, string $model, string $key, int $limit, bool $force): array
    {
        // Torrent uses SoftDeletes: without this the pool counts rows that are
        // already gone. That mistake once turned 11 pending items into "672".
        $query = Torrent::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->whereNull('deleted_at');

        if (!$force) {
            $query->whereNotIn($column, $model::query()->select($key));
        }

        /** @var list<string> $ids */
        $ids = $query->distinct()->limit($limit)->pluck($column)->all();

        return $ids;
    }

    private function dispatchOne(object $job, bool $queued, bool $force, string $cacheKey, string $id, int &$ok, int &$failed): void
    {
        if ($force) {
            cache()->forget($cacheKey);
        }

        try {
            if ($queued) {
                dispatch($job);
            } else {
                dispatch_sync($job);
            }

            $ok++;
            $this->line("  ok   {$id}");
        } catch (Throwable $e) {
            $failed++;
            $this->warn("  fail {$id}: ".$e->getMessage());
        }

        // ~4 requests a second, the same pace SyncMissingTrailers uses.
        usleep(250_000);
    }
}
