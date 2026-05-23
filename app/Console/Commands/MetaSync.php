<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MetadataArtwork;
use App\Models\MetadataResolution;
use App\Models\Torrent;
use App\Services\Metadata\ConsensusResolver;
use App\Services\Metadata\Support\Candidate;
use App\Services\Metadata\Support\ResolvedIds;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backfill verified metadata ids across the torrent catalog using the
 * multi-provider consensus resolver.
 *
 * Default run resolves only torrents with no resolution row yet; --force
 * re-resolves the whole catalog. --limit caps how many torrents a single
 * run processes — keeping a queued/scheduled run inside its time budget; a
 * bounded --force run rolls through the catalog stalest-resolution first.
 * Resolution is computed once per TMDB id per run (cached) and written as
 * one metadata_resolutions row per torrent; a trusted result also back-fills
 * the torrent's own empty id columns.
 *
 * Only torrents that already carry a TMDB id are processed — the resolver
 * needs a clean title/year, taken from the linked tmdb_movies/tmdb_tv row.
 * Torrents with no TMDB id are skipped and counted.
 */
class MetaSync extends Command
{
    protected $signature = 'meta:sync {--force : Re-resolve every torrent, not only unresolved ones} {--limit= : Max torrents to process this run (default: all)}';

    protected $description = 'Resolve torrent metadata ids via multi-provider consensus';

    final public function handle(ConsensusResolver $resolver): int
    {
        $force = (bool) $this->option('force');
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null ? max(1, (int) $limitOption) : null;
        $startedAt = microtime(true);

        // Progress snapshot before this run — useful in laravel.log for
        // tracking backfill completion without watching a TTY.
        $totalTarget = MetadataResolution::query()->count();
        $totalPool = Torrent::query()
            ->whereHas('category', fn ($q) => $q->where('movie_meta', true)->orWhere('tv_meta', true))
            ->where(fn ($q) => $q->where('tmdb_movie_id', '>', 0)->orWhere('tmdb_tv_id', '>', 0))
            ->count();
        Log::info('meta:sync starting', [
            'force'         => $force,
            'limit'         => $limit,
            'resolved_so_far' => $totalTarget,
            'pool_total'    => $totalPool,
            'progress_pct'  => $totalPool > 0 ? round($totalTarget / $totalPool * 100, 1) : 0,
        ]);

        $query = Torrent::query()
            ->with(['category', 'movie:id,title,release_date', 'tv:id,name,first_air_date'])
            ->whereHas('category', fn ($q) => $q->where('movie_meta', true)->orWhere('tv_meta', true))
            ->where(fn ($q) => $q->where('tmdb_movie_id', '>', 0)->orWhere('tmdb_tv_id', '>', 0));

        if (!$force) {
            $query->whereNotIn('id', MetadataResolution::query()->select('torrent_id'));
        }

        // A bounded --force run rolls through the catalog stalest-resolution
        // first, so repeated small runs eventually refresh everything.
        if ($limit !== null && $force) {
            $query->leftJoin('metadata_resolutions', 'metadata_resolutions.torrent_id', '=', 'torrents.id')
                ->select('torrents.*')
                ->orderByRaw('metadata_resolutions.updated_at IS NULL DESC')
                ->orderBy('metadata_resolutions.updated_at');
        }

        if ($limit !== null) {
            $torrents = $query->limit($limit)->get();
            $count = $torrents->count();
        } else {
            $torrents = null;
            $count = (clone $query)->count();
        }

        if ($count === 0) {
            $this->info('No torrents need metadata resolution.');

            return self::SUCCESS;
        }

        $this->info("Resolving metadata for {$count} torrents"
            .($force ? ' (forced)' : '')
            .($limit !== null ? " (limit {$limit})" : '').'...');
        $bar = $this->output->createProgressBar($count);

        /** @var array<string,ResolvedIds> $cache resolution per "CATEGORY:tmdbId", deduped within the run */
        $cache = [];
        /** @var array<string,true> $harvested artwork already harvested per "CATEGORY:tmdbId" */
        $harvested = [];
        $stats = ['high' => 0, 'low' => 0, 'none' => 0, 'skipped' => 0, 'backfilled' => 0, 'artwork' => 0, 'errors' => 0];

        $process = function (Torrent $torrent) use ($resolver, &$cache, &$harvested, &$stats, $bar): void {
            $job = $this->describe($torrent);

            if ($job === null) {
                $stats['skipped']++;
                $bar->advance();

                return;
            }

            [$category, $tmdbId, $title, $year, $malHint] = $job;
            $key = "{$category}:{$tmdbId}";

            try {
                if (!isset($cache[$key])) {
                    $cache[$key] = $resolver->resolve($title, $year, $category, [], $malHint);
                    usleep(250_000); // pace fresh resolves; cache hits cost nothing
                }

                $result = $cache[$key];

                MetadataResolution::updateOrCreate(
                    ['torrent_id' => $torrent->id],
                    [
                        'category'       => $result->category,
                        'tmdb_id'        => $result->tmdbId ?: null,
                        'tvdb_id'        => $result->tvdbId ?: null,
                        'imdb_id'        => $result->imdbId !== '' ? $result->imdbId : null,
                        'mal_id'         => $result->malId ?: null,
                        'resolved_title' => $result->title,
                        'resolved_year'  => $result->year,
                        'confidence'     => $result->confidence,
                        'votes'          => $result->votes,
                        'mal_votes'      => $result->malVotes,
                        'detail'         => $this->detailToArray($result->detail),
                    ],
                );

                $stats[$result->confidence]++;

                if ($result->isTrusted() && $this->backfillTorrent($torrent, $result)) {
                    $stats['backfilled']++;
                }

                if ($result->isTrusted() && $result->tmdbId > 0 && !isset($harvested[$key])) {
                    $stats['artwork'] += $this->harvestArtwork($result);
                    $harvested[$key] = true;
                }
            } catch (Throwable $e) {
                $this->warn("\ntorrent {$torrent->id}: {$e->getMessage()}");
                Log::warning('meta:sync torrent resolve failed', [
                    'torrent_id' => $torrent->id,
                    'tmdb_movie' => $torrent->tmdb_movie_id,
                    'tmdb_tv'    => $torrent->tmdb_tv_id,
                    'error'      => $e->getMessage(),
                    'exception'  => $e::class,
                ]);
                $stats['errors']++;
            }

            $bar->advance();
        };

        if ($torrents !== null) {
            $torrents->each($process);
        } else {
            $query->each($process);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf(
            'Done. high=%d low=%d none=%d skipped=%d — torrent ids back-filled=%d, artwork rows=%d, errors=%d',
            $stats['high'],
            $stats['low'],
            $stats['none'],
            $stats['skipped'],
            $stats['backfilled'],
            $stats['artwork'],
            $stats['errors'],
        ));

        Log::info('meta:sync finished', [
            'processed'     => $count,
            'high'          => $stats['high'],
            'low'           => $stats['low'],
            'none'          => $stats['none'],
            'skipped'       => $stats['skipped'],
            'backfilled'    => $stats['backfilled'],
            'artwork'       => $stats['artwork'],
            'errors'        => $stats['errors'],
            'duration_secs' => round(microtime(true) - $startedAt, 1),
        ]);

        return self::SUCCESS;
    }

    /**
     * Build the resolver input for a torrent, or null when it cannot be
     * resolved (no category meta, or no linked TMDB metadata record).
     *
     * @return array{0:'MOVIE'|'TV',1:int,2:string,3:?int,4:bool}|null
     */
    private function describe(Torrent $torrent): ?array
    {
        $category = $torrent->category;

        if ($category === null) {
            return null;
        }

        $malHint = $torrent->mal > 0
            || str_contains(strtolower((string) $category->name), 'anime');

        if ($category->movie_meta && $torrent->tmdb_movie_id > 0 && $torrent->movie !== null) {
            return [
                'MOVIE',
                $torrent->tmdb_movie_id,
                (string) $torrent->movie->title,
                $this->yearOf($torrent->movie->release_date),
                $malHint,
            ];
        }

        if ($category->tv_meta && $torrent->tmdb_tv_id > 0 && $torrent->tv !== null) {
            return [
                'TV',
                $torrent->tmdb_tv_id,
                (string) $torrent->tv->name,
                $this->yearOf($torrent->tv->first_air_date),
                $malHint,
            ];
        }

        return null;
    }

    /**
     * Fill the torrent's own empty id columns from a trusted resolution.
     * Existing non-zero ids are never overwritten.
     */
    private function backfillTorrent(Torrent $torrent, ResolvedIds $result): bool
    {
        $updates = [];

        if (!$torrent->imdb && $result->imdbId !== '') {
            $updates['imdb'] = (int) preg_replace('/\D/', '', $result->imdbId);
        }

        if (!$torrent->tvdb && $result->tvdbId) {
            $updates['tvdb'] = $result->tvdbId;
        }

        if (!$torrent->mal && $result->malId) {
            $updates['mal'] = $result->malId;
        }

        if ($updates === []) {
            return false;
        }

        $torrent->update($updates);

        return true;
    }

    /**
     * Harvest the winning title's cover from every provider that carried it
     * into the metadata_artwork pool. Returns the number of rows upserted.
     */
    private function harvestArtwork(ResolvedIds $result): int
    {
        $byProvider = [];

        foreach ($result->detail as $c) {
            if ($c->posterUrl === '') {
                continue;
            }

            $matchesWinner = ($result->imdbId !== '' && $c->imdbId === $result->imdbId)
                || ($result->tmdbId !== 0 && $c->tmdbId === $result->tmdbId)
                || ($result->malId !== 0 && $c->malId === $result->malId);

            if ($matchesWinner) {
                $byProvider[$c->provider] ??= $c->posterUrl;
            }
        }

        foreach ($byProvider as $source => $url) {
            MetadataArtwork::updateOrCreate(
                [
                    'category' => $result->category,
                    'tmdb_id'  => $result->tmdbId,
                    'source'   => $source,
                    'type'     => 'poster',
                ],
                ['url' => $url],
            );
        }

        return \count($byProvider);
    }

    private function yearOf(mixed $date): ?int
    {
        $year = (int) substr((string) $date, 0, 4);

        return $year > 0 ? $year : null;
    }

    /**
     * @param list<Candidate> $detail
     *
     * @return list<array<string,mixed>>
     */
    private function detailToArray(array $detail): array
    {
        return array_map(static fn (Candidate $c): array => [
            'provider' => $c->provider,
            'title'    => $c->title,
            'year'     => $c->year,
            'category' => $c->category,
            'score'    => round($c->score, 3),
            'tmdb'     => $c->tmdbId,
            'tvdb'     => $c->tvdbId,
            'imdb'     => $c->imdbId,
            'mal'      => $c->malId,
        ], $detail);
    }
}
