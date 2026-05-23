<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Metadata\Support\Candidate;
use App\Services\Metadata\Support\Normalize;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * IMDB provider for the consensus resolver, backed by OMDb (omdbapi.com) —
 * IMDB itself has no public API, so OMDb is the practical source of the
 * IMDB id. Degrades to a no-op provider when no key is configured.
 */
final class ImdbClient
{
    private const BASE = 'http://www.omdbapi.com/';

    private const TIMEOUT = 12;

    /** Consecutive request failures after which OMDb is dropped for the run. */
    private const FAILURE_THRESHOLD = 8;

    private string $key;

    private int $consecutiveFailures = 0;

    private bool $tripped = false;

    public function __construct()
    {
        $this->key = (string) config('api-keys.omdb', '');
    }

    /**
     * False once the run-scoped circuit breaker has tripped — OMDb is then
     * skipped for the rest of the process (e.g. after hitting a daily quota
     * wall), instead of firing thousands of doomed requests.
     */
    public function isEnabled(): bool
    {
        return $this->key !== '' && !$this->tripped;
    }

    /**
     * Title search. An exact title+year miss falls back to OMDb's broader
     * search list so a near-match can still join the vote.
     *
     * @param 'MOVIE'|'TV' $category
     *
     * @return list<Candidate>
     */
    public function search(string $title, ?int $year, string $category): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $type = $category === 'MOVIE' ? 'movie' : 'series';
        $out = [];

        try {
            $params = ['apikey' => $this->key, 't' => $title, 'type' => $type];

            if ($year) {
                $params['y'] = $year;
            }

            $d = Http::timeout(self::TIMEOUT)->get(self::BASE, $params)->throw()->json() ?? [];

            if (($d['Response'] ?? '') === 'True') {
                $yr = Normalize::toInt(substr((string) ($d['Year'] ?? ''), 0, 4)) ?: null;
                $out[] = new Candidate('omdb', $d['Title'] ?? '', $yr, $category, imdb: $d['imdbID'] ?? '', posterUrl: $this->poster($d['Poster'] ?? ''));
            } else {
                $list = Http::timeout(self::TIMEOUT)
                    ->get(self::BASE, ['apikey' => $this->key, 's' => $title, 'type' => $type])
                    ->throw()
                    ->json() ?? [];

                foreach (\array_slice($list['Search'] ?? [], 0, 5) as $hit) {
                    $yr = Normalize::toInt(substr((string) ($hit['Year'] ?? ''), 0, 4)) ?: null;
                    $out[] = new Candidate('omdb', $hit['Title'] ?? '', $yr, $category, imdb: $hit['imdbID'] ?? '', posterUrl: $this->poster($hit['Poster'] ?? ''));
                }
            }

            $this->consecutiveFailures = 0;
        } catch (Throwable $e) {
            $this->recordFailure();
            Log::warning('metadata.omdb search failed: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * Direct OMDb lookup by IMDB id — exact, no fuzzy search ranking.
     *
     * @return array{title:string,year:?int,tmdb:int,tvdb:int}|null
     */
    public function byId(string $imdb): ?array
    {
        if (!$this->isEnabled() || $imdb === '') {
            return null;
        }

        try {
            $d = Http::timeout(self::TIMEOUT)
                ->get(self::BASE, ['apikey' => $this->key, 'i' => $imdb])
                ->throw()
                ->json() ?? [];

            $this->consecutiveFailures = 0;

            if (($d['Response'] ?? '') === 'True') {
                return [
                    'title' => $d['Title'] ?? '',
                    'year'  => Normalize::toInt(substr((string) ($d['Year'] ?? ''), 0, 4)) ?: null,
                    'tmdb'  => 0,
                    'tvdb'  => 0,
                ];
            }
        } catch (Throwable $e) {
            $this->recordFailure();
            Log::warning('metadata.omdb by-id failed: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Count an OMDb request failure; trip the run-scoped breaker once the
     * failures pile up so the rest of the run stops calling OMDb.
     */
    private function recordFailure(): void
    {
        if (++$this->consecutiveFailures >= self::FAILURE_THRESHOLD) {
            $this->tripped = true;
            Log::warning('metadata.omdb circuit-breaker tripped — OMDb skipped for the rest of this run');
        }
    }

    /**
     * OMDb returns the string "N/A" when a poster is missing.
     */
    private function poster(string $value): string
    {
        return ($value === '' || $value === 'N/A') ? '' : $value;
    }
}
