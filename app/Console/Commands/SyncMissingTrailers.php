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
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Console\Commands;

use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncMissingTrailers extends Command
{
    protected $signature   = 'tmdb:sync-trailers {--force : Re-fetch even when trailer is already set}';
    protected $description = 'Backfill missing YouTube trailers from TMDB (movies + TV)';

    final public function handle(): void
    {
        $this->processMedia(TmdbMovie::class, 'movie');
        $this->processMedia(TmdbTv::class, 'tv');
        $this->info('Done.');
    }

    /**
     * Pick the best trailer key from TMDB results.
     *
     * Priority: official Spanish → official English → official any → unofficial Spanish → any trailer.
     * Spanish trailers are hosted by Spanish/EU channels and are generally not geo-restricted for ES/EU.
     */
    private function pickTrailer(array $results): ?string
    {
        $trailers = collect($results)
            ->filter(fn ($v) => ($v['type'] ?? '') === 'Trailer' && ($v['site'] ?? '') === 'YouTube');

        foreach (['es', 'en'] as $lang) {
            $key = $trailers
                ->filter(fn ($v) => ($v['iso_639_1'] ?? '') === $lang && ($v['official'] ?? false))
                ->sortByDesc('published_at')
                ->first()['key'] ?? null;
            if ($key !== null) {
                return $key;
            }
        }

        // Any official trailer regardless of language
        $key = $trailers->filter(fn ($v) => $v['official'] ?? false)->sortByDesc('published_at')->first()['key'] ?? null;
        if ($key !== null) {
            return $key;
        }

        // Last resort: unofficial Spanish, then anything
        return $trailers
            ->sortByDesc(fn ($v) => ($v['iso_639_1'] ?? '') === 'es' ? 1 : 0)
            ->first()['key'] ?? null;
    }

    private function processMedia(string $modelClass, string $type): void
    {
        $query = $modelClass::query()->select('id', 'trailer');

        if (!$this->option('force')) {
            $query->whereNull('trailer');
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info("No {$type} entries need trailer backfill.");

            return;
        }

        $this->info("Fetching trailers for {$count} {$type} entries...");
        $bar = $this->output->createProgressBar($count);

        $query->each(function ($record) use ($type, $bar): void {
            usleep(250_000); // ~4 req/s, safely under TMDB 40/10s limit

            try {
                $response = Http::timeout(15)->get(
                    "https://api.TheMovieDB.org/3/{$type}/{$record->id}/videos",
                    ['api_key' => config('api-keys.tmdb'), 'include_video_language' => 'es,en,null'],
                );

                if ($response->successful()) {
                    $key = $this->pickTrailer($response->json('results', []));

                    if ($key !== null || $this->option('force')) {
                        $record->update(['trailer' => $key]);
                    }
                } else {
                    $this->warn("\nTMDB {$type} {$record->id}: HTTP {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->warn("\nTMDB {$type} {$record->id}: {$e->getMessage()}");
            }

            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
    }
}
