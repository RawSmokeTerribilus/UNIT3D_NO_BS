<?php

declare(strict_types=1);

namespace App\Services\Audiobooks;

use App\Services\Books\Support\Isbn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Audnexus — the audiobook metadata this tracker actually shows.
 *
 * It is the community mirror of Audible's catalogue, the same source
 * Audiobookshelf uses. No API key, roughly 100 requests a minute per origin.
 * It is addressable by ASIN only, so AudibleClient does the title lookup
 * first.
 *
 * The reason it is worth two round trips: it is the only free source that
 * reports the narrator and the runtime, and those are exactly what separates
 * two recordings of the same book. A cover comes back on m.media-amazon.com,
 * which the art proxy already allows, so audiobook covers work with no
 * change to that allowlist.
 */
final class AudnexusClient
{
    private const BASE = 'https://api.audnex.us';

    private const TIMEOUT = 12;

    private const FAILURE_THRESHOLD = 8;

    private int $failures = 0;

    public function isEnabled(): bool
    {
        return $this->failures < self::FAILURE_THRESHOLD;
    }

    /**
     * Full record for one ASIN, normalised to the audiobooks table's shape.
     *
     * @return array<string, mixed>|null
     */
    public function book(string $asin, string $region = 'es'): ?array
    {
        $asin = strtoupper(trim($asin));

        if (!$this->isEnabled() || $asin === '') {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->get(self::BASE.'/books/'.$asin, ['region' => strtolower($region)]);

            // Audnexus simply does not have every ASIN, especially outside
            // the US marketplace. That is a miss, not a provider failure.
            if ($response->notFound()) {
                return null;
            }

            $json = $response->throw()->json();
            $this->failures = 0;
        } catch (Throwable $e) {
            $this->failures++;
            Log::warning('audnexus.book failed: '.$e->getMessage());

            return null;
        }

        if (!\is_array($json) || ($json['title'] ?? '') === '') {
            return null;
        }

        $cover = (string) ($json['image'] ?? '');

        return [
            'asin'               => (string) ($json['asin'] ?? $asin),
            'region'             => strtolower($region),
            'title'              => (string) $json['title'],
            'subtitle'           => (string) ($json['subtitle'] ?? ''),
            'authors'            => $this->names($json['authors'] ?? []),
            'narrators'          => $this->names($json['narrators'] ?? []),
            'series'             => (string) ($json['seriesPrimary']['name'] ?? ''),
            'series_position'    => (string) ($json['seriesPrimary']['position'] ?? ''),
            'runtime_length_min' => isset($json['runtimeLengthMin']) ? (int) $json['runtimeLengthMin'] : null,
            'release_date'       => $this->date($json['releaseDate'] ?? null),
            'publisher'          => (string) ($json['publisherName'] ?? ''),
            'language'           => (string) ($json['language'] ?? ''),
            'genres'             => $this->names($json['genres'] ?? []),
            'isbn13'             => Isbn::toIsbn13((string) ($json['isbn'] ?? '')) ?: null,
            'description'        => trim(strip_tags((string) ($json['summary'] ?? $json['description'] ?? ''))),
            'cover_url'          => $cover,
            // Amazon sirve la misma imagen en cualquier tamano con un sufijo
            // en la URL. Comprobado contra la real: 200x200, 500x500,
            // 1200x1200 y 2400x2400, sin una peticion de mas. Guardar solo la
            // original obligaba a Telegram a mandar 526 KiB para una miniatura.
            'cover_urls'         => \App\Services\Metadata\CoverLadder::merge(
                \App\Services\Metadata\CoverLadder::amazon($cover)
            ),
        ];
    }

    private function date(mixed $raw): ?string
    {
        if (!\is_string($raw) || $raw === '') {
            return null;
        }

        return substr($raw, 0, 10);
    }

    /**
     * @return list<string>
     */
    private function names(mixed $items): array
    {
        if (!\is_array($items)) {
            return [];
        }

        $names = [];

        foreach ($items as $item) {
            $name = \is_array($item) ? ($item['name'] ?? null) : $item;

            if (\is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
