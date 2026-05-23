<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Metadata\Support\Candidate;
use App\Services\Metadata\Support\Normalize;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TMDB provider for the consensus resolver: title search, external-id fill,
 * and by-IMDB-id verification.
 *
 * Distinct from App\Services\Tmdb (full metadata population) — this only
 * issues the three lookups identification needs. Search and find are sent
 * locale-neutral on purpose: the resolver matches against release titles
 * (usually original-language), so a localised TMDB title list would degrade
 * scoring. es_ES applies to metadata *population*, not id *identification*.
 */
final class TmdbClient
{
    private const BASE = 'https://api.themoviedb.org/3';

    private const TIMEOUT = 12;

    private string $key;

    public function __construct()
    {
        $this->key = (string) config('api-keys.tmdb', '');
    }

    public function isEnabled(): bool
    {
        return $this->key !== '';
    }

    /**
     * Fuzzy title search.
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

        $out = [];

        try {
            if ($category === 'MOVIE') {
                $params = ['query' => $title];

                if ($year) {
                    $params['year'] = $year;
                }

                $results = $this->get('/search/movie', $params)['results'] ?? [];
                $kind = 'MOVIE';
            } else {
                $params = ['query' => $title];

                if ($year) {
                    $params['first_air_date_year'] = $year;
                }

                $results = $this->get('/search/tv', $params)['results'] ?? [];
                $kind = 'TV';
            }

            foreach (\array_slice($results, 0, 5) as $r) {
                $ttl = $r['title'] ?? $r['name'] ?? '';
                $date = $r['release_date'] ?? $r['first_air_date'] ?? '';
                $yr = Normalize::toInt(substr((string) $date, 0, 4)) ?: null;
                $poster = !empty($r['poster_path'])
                    ? 'https://image.tmdb.org/t/p/original'.$r['poster_path']
                    : '';
                $out[] = new Candidate('tmdb', $ttl, $yr, $kind, tmdb: $r['id'] ?? 0, posterUrl: $poster);
            }
        } catch (Throwable $e) {
            Log::warning('metadata.tmdb search failed: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * Back-fill imdb/tvdb ids on a TMDB candidate via /external_ids.
     * Mutates the candidate in place.
     */
    public function fillExternal(Candidate $cand): void
    {
        if (!$this->isEnabled() || !$cand->tmdbId) {
            return;
        }

        try {
            $seg = $cand->category === 'MOVIE' ? 'movie' : 'tv';
            $ext = $this->get("/{$seg}/{$cand->tmdbId}/external_ids");

            $cand->imdbId = Normalize::imdbId($ext['imdb_id'] ?? null) ?: $cand->imdbId;
            $cand->tvdbId = Normalize::toInt($ext['tvdb_id'] ?? null) ?: $cand->tvdbId;
        } catch (Throwable $e) {
            Log::warning('metadata.tmdb external_ids failed: '.$e->getMessage());
        }
    }

    /**
     * By-IMDB-id lookup. Returns a record when TMDB carries this id.
     *
     * @return array{title:string,year:?int,category:'MOVIE'|'TV',tmdb:int,tvdb:int}|null
     */
    public function verifyImdb(string $imdb): ?array
    {
        if (!$this->isEnabled() || $imdb === '') {
            return null;
        }

        try {
            $res = $this->get("/find/{$imdb}", ['external_source' => 'imdb_id']);

            foreach (['tv_results' => 'TV', 'movie_results' => 'MOVIE'] as $field => $cat) {
                $arr = $res[$field] ?? [];

                if ($arr !== []) {
                    $r = $arr[0];
                    $ttl = $r['name'] ?? $r['title'] ?? '';
                    $date = $r['first_air_date'] ?? $r['release_date'] ?? '';

                    return [
                        'title'    => $ttl,
                        'year'     => Normalize::toInt(substr((string) $date, 0, 4)) ?: null,
                        'category' => $cat,
                        'tmdb'     => Normalize::toInt($r['id'] ?? 0),
                        'tvdb'     => 0,
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::warning('metadata.tmdb verify failed: '.$e->getMessage());
        }

        return null;
    }

    /**
     * @param array<string,mixed> $params
     *
     * @return array<mixed>
     */
    private function get(string $path, array $params = []): array
    {
        $params['api_key'] = $this->key;

        return Http::timeout(self::TIMEOUT)
            ->get(self::BASE.$path, $params)
            ->throw()
            ->json() ?? [];
    }
}
