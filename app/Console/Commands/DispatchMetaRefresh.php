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

use App\Jobs\ProcessMovieJob;
use App\Jobs\ProcessTvJob;
use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class DispatchMetaRefresh extends Command
{
    protected $signature = 'meta:refresh-dispatch
                            {--limit=5 : Maximum items to queue in this batch}
                            {--stale-hours=720 : Only refresh metadata older than this many hours}
                            {--queue=meta-refresh : Queue name for dispatched jobs}
                            {--dispatch-ttl-minutes=10 : Prevent the same ID from being re-queued too quickly}
                            {--dry-run : Show what would be queued without dispatching jobs}';

    protected $description = 'Queues a paced TMDB metadata refresh batch for stale movies and TV series';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $staleHours = max(1, (int) $this->option('stale-hours'));
        $dispatchTtlMinutes = max(1, (int) $this->option('dispatch-ttl-minutes'));
        $queue = (string) $this->option('queue');
        $dryRun = (bool) $this->option('dry-run');
        $threshold = now()->subHours($staleHours);

        $movieTarget = (int) ceil($limit / 2);
        $tvTarget = $limit - $movieTarget;

        $movieIds = $this->dispatchMovieRefreshes($movieTarget, $threshold, $queue, $dispatchTtlMinutes, $dryRun);
        $tvIds = $this->dispatchTvRefreshes($tvTarget, $threshold, $queue, $dispatchTtlMinutes, $dryRun);

        $remaining = $limit - count($movieIds) - count($tvIds);

        if ($remaining > 0) {
            $movieIds = [...$movieIds, ...$this->dispatchMovieRefreshes($remaining, $threshold, $queue, $dispatchTtlMinutes, $dryRun)];
            $remaining = $limit - count($movieIds) - count($tvIds);
        }

        if ($remaining > 0) {
            $tvIds = [...$tvIds, ...$this->dispatchTvRefreshes($remaining, $threshold, $queue, $dispatchTtlMinutes, $dryRun)];
        }

        $total = count($movieIds) + count($tvIds);

        if ($total === 0) {
            $this->info('No stale TMDB metadata needed dispatching.');

            return self::SUCCESS;
        }

        $verb = $dryRun ? 'Would queue' : 'Queued';

        $this->info(sprintf(
            '%s %d metadata refresh job(s) on [%s]: %d movie(s), %d TV series.',
            $verb,
            $total,
            $queue,
            count($movieIds),
            count($tvIds)
        ));

        $this->line('Movies: '.implode(', ', $movieIds));
        $this->line('TV: '.implode(', ', $tvIds));

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function dispatchMovieRefreshes(int $limit, \Illuminate\Support\Carbon $threshold, string $queue, int $dispatchTtlMinutes, bool $dryRun): array
    {
        if ($limit < 1) {
            return [];
        }

        return $this->dispatchRefreshes(
            type: 'movie',
            limit: $limit,
            queue: $queue,
            dispatchTtlMinutes: $dispatchTtlMinutes,
            dryRun: $dryRun,
            query: TmdbMovie::query()
                ->select('id')
                ->where(fn (Builder $query) => $query
                    ->whereNull('updated_at')
                    ->orWhere('updated_at', '<', $threshold)
                )
                ->orderByRaw('updated_at IS NULL DESC')
                ->orderBy('updated_at')
        );
    }

    /**
     * @return list<int>
     */
    private function dispatchTvRefreshes(int $limit, \Illuminate\Support\Carbon $threshold, string $queue, int $dispatchTtlMinutes, bool $dryRun): array
    {
        if ($limit < 1) {
            return [];
        }

        return $this->dispatchRefreshes(
            type: 'tv',
            limit: $limit,
            queue: $queue,
            dispatchTtlMinutes: $dispatchTtlMinutes,
            dryRun: $dryRun,
            query: TmdbTv::query()
                ->select('id')
                ->where(fn (Builder $query) => $query
                    ->whereNull('updated_at')
                    ->orWhere('updated_at', '<', $threshold)
                )
                ->orderByRaw('updated_at IS NULL DESC')
                ->orderBy('updated_at')
        );
    }

    /**
     * @return list<int>
     */
    private function dispatchRefreshes(string $type, int $limit, string $queue, int $dispatchTtlMinutes, bool $dryRun, Builder $query): array
    {
        $dispatchedIds = [];
        $candidateIds = $query
            ->limit($limit * 10)
            ->pluck('id');

        foreach ($candidateIds as $id) {
            if (count($dispatchedIds) >= $limit) {
                break;
            }

            $cacheKey = sprintf('meta-refresh-dispatch:%s:%d', $type, $id);

            if (Cache::has($cacheKey)) {
                continue;
            }

            if (!$dryRun) {
                Cache::put($cacheKey, true, now()->addMinutes($dispatchTtlMinutes));

                match ($type) {
                    'movie' => ProcessMovieJob::dispatch((int) $id, true)->onQueue($queue),
                    'tv'    => ProcessTvJob::dispatch((int) $id, true)->onQueue($queue),
                };
            }

            $dispatchedIds[] = (int) $id;
        }

        return $dispatchedIds;
    }
}
