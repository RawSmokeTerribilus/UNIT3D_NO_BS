<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Metadata\Support\Candidate;
use App\Services\Metadata\Support\Normalize;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MyAnimeList provider for the consensus resolver, backed by Jikan
 * (api.jikan.moe — keyless). Queried only when a release looks like anime;
 * votes on the MAL id.
 */
final class MalClient
{
    private const BASE = 'https://api.jikan.moe/v4/anime';

    private const TIMEOUT = 12;

    /**
     * @return list<Candidate>
     */
    public function search(string $title): array
    {
        $out = [];

        try {
            $data = Http::timeout(self::TIMEOUT)
                ->get(self::BASE, ['q' => $title, 'limit' => 5, 'sfw' => 'true'])
                ->throw()
                ->json()['data'] ?? [];

            foreach (\array_slice($data, 0, 5) as $hit) {
                $ttl = $hit['title_english'] ?? $hit['title'] ?? '';
                $yr = Normalize::toInt($hit['year'] ?? 0) ?: null;
                $jpg = $hit['images']['jpg'] ?? [];
                $poster = $jpg['large_image_url'] ?? $jpg['image_url'] ?? '';
                $out[] = new Candidate('jikan', $ttl, $yr, 'TV', mal: $hit['mal_id'] ?? 0, posterUrl: $poster);
            }
        } catch (Throwable $e) {
            Log::warning('metadata.jikan search failed: '.$e->getMessage());
        }

        return $out;
    }
}
