<?php

declare(strict_types=1);

namespace App\Services\Mal\Client;

use Illuminate\Support\Facades\Http;

class Anime
{
    private const API_BASE = 'https://api.myanimelist.net/v2/anime';

    private const FIELDS = 'id,title,alternative_titles,start_date,end_date,synopsis,mean,rank,num_episodes,main_picture,media_type,status,genres,nsfw';

    public function __construct(private int $id)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getData(): ?array
    {
        $response = Http::withHeaders([
            'X-MAL-CLIENT-ID' => config('services.mal.client_id'),
        ])->get(self::API_BASE.'/'.$this->id, [
            'fields' => self::FIELDS,
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $data Raw MAL API response
     * @return array<string, mixed> Normalized for MalAnime::updateOrCreate
     */
    public function normalize(array $data): array
    {
        $picture = $data['main_picture'] ?? [];
        $altTitles = $data['alternative_titles'] ?? [];

        return [
            'id'              => $data['id'],
            'title'           => $data['title'],
            'title_english'   => $altTitles['en'] ?? null,
            'title_japanese'  => $altTitles['ja'] ?? null,
            'synopsis'        => $data['synopsis'] ?? null,
            'mean'            => $data['mean'] ?? null,
            'rank'            => $data['rank'] ?? null,
            'num_episodes'    => $data['num_episodes'] ?? null,
            'start_date'      => $data['start_date'] ?? null,
            'end_date'        => ($data['end_date'] ?? null) ?: null,
            'media_type'      => $data['media_type'] ?? null,
            'status'          => $data['status'] ?? null,
            'nsfw'            => $data['nsfw'] ?? null,
            'poster'          => $picture['large'] ?? $picture['medium'] ?? null,
            'genres'          => array_map(
                fn ($g) => ['id' => $g['id'], 'name' => $g['name']],
                $data['genres'] ?? [],
            ),
        ];
    }
}
