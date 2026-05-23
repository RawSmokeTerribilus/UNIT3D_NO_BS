<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Metadata\Support\Candidate;
use App\Services\Metadata\Support\Normalize;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TVmaze provider for the consensus resolver. Keyless, TV only. Its real
 * value is the free TVDB number it exposes via a show's `externals` block —
 * TVDB's own v4 API moved to a paid per-user PIN model and is not used.
 */
final class TvmazeClient
{
    private const SEARCH = 'https://api.tvmaze.com/search/shows';

    private const LOOKUP = 'https://api.tvmaze.com/lookup/shows';

    private const TIMEOUT = 12;

    /**
     * Title search. TVmaze has no movies — returns nothing for MOVIE.
     *
     * @param 'MOVIE'|'TV' $category
     *
     * @return list<Candidate>
     */
    public function search(string $title, string $category): array
    {
        if ($category === 'MOVIE') {
            return [];
        }

        $out = [];

        try {
            $hits = Http::timeout(self::TIMEOUT)
                ->get(self::SEARCH, ['q' => $title])
                ->throw()
                ->json() ?? [];

            foreach (\array_slice($hits, 0, 5) as $hit) {
                $show = $hit['show'] ?? [];
                $ext = $show['externals'] ?? [];
                $img = $show['image'] ?? [];
                $yr = Normalize::toInt(substr((string) ($show['premiered'] ?? ''), 0, 4)) ?: null;
                $out[] = new Candidate(
                    'tvmaze',
                    $show['name'] ?? '',
                    $yr,
                    'TV',
                    tvdb: $ext['thetvdb'] ?? 0,
                    imdb: $ext['imdb'] ?? '',
                    posterUrl: $img['original'] ?? $img['medium'] ?? '',
                );
            }
        } catch (Throwable $e) {
            Log::warning('metadata.tvmaze search failed: '.$e->getMessage());
        }

        return $out;
    }

    /**
     * Direct TVmaze lookup by IMDB id.
     *
     * @return array{title:string,year:?int,tmdb:int,tvdb:int}|null
     */
    public function byImdb(string $imdb): ?array
    {
        if ($imdb === '') {
            return null;
        }

        try {
            $resp = Http::timeout(self::TIMEOUT)->get(self::LOOKUP, ['imdb' => $imdb]);

            if ($resp->status() === 404) {
                return null;
            }

            $show = $resp->throw()->json();

            if (!\is_array($show) || empty($show['id'])) {
                return null;
            }

            $ext = $show['externals'] ?? [];

            return [
                'title' => $show['name'] ?? '',
                'year'  => Normalize::toInt(substr((string) ($show['premiered'] ?? ''), 0, 4)) ?: null,
                'tmdb'  => 0,
                'tvdb'  => Normalize::toInt($ext['thetvdb'] ?? 0),
            ];
        } catch (Throwable $e) {
            Log::warning('metadata.tvmaze by-id failed: '.$e->getMessage());
        }

        return null;
    }
}
