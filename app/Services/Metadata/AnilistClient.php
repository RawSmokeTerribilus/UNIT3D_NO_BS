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

namespace App\Services\Metadata;

use App\Services\Metadata\Support\Candidate;
use App\Services\Metadata\Support\Normalize;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AniList provider for the consensus resolver (graphql.anilist.co — keyless).
 * Queried only for anime; exposes idMal so it can second the MAL-id vote.
 */
final class AnilistClient
{
    private const ENDPOINT = 'https://graphql.anilist.co';

    private const TIMEOUT = 12;

    private const QUERY = <<<'GQL'
        query ($s: String) {
          Page(perPage: 5) {
            media(search: $s, type: ANIME) {
              idMal
              title { romaji english }
              startDate { year }
              coverImage { large }
            }
          }
        }
        GQL;

    /**
     * @return list<Candidate>
     */
    public function search(string $title): array
    {
        $out = [];

        try {
            $media = Http::timeout(self::TIMEOUT)
                ->post(self::ENDPOINT, ['query' => self::QUERY, 'variables' => ['s' => $title]])
                ->throw()
                ->json()['data']['Page']['media'] ?? [];

            foreach (\array_slice($media, 0, 5) as $m) {
                $t = $m['title'] ?? [];
                $ttl = $t['english'] ?? $t['romaji'] ?? '';
                $yr = Normalize::toInt($m['startDate']['year'] ?? 0) ?: null;
                $poster = $m['coverImage']['large'] ?? '';
                $out[] = new Candidate('anilist', $ttl, $yr, 'TV', mal: $m['idMal'] ?? 0, posterUrl: $poster);
            }
        } catch (Throwable $e) {
            Log::warning('metadata.anilist search failed: '.$e->getMessage());
        }

        return $out;
    }
}
