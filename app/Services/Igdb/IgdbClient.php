<?php

declare(strict_types=1);

namespace App\Services\Igdb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * IGDB v4 client.
 *
 * Replaces marcreichel/igdb-laravel, which was abandoned in June 2025 and was
 * the single dependency pinning the project to Laravel 12. IGDB's API is a
 * client-credentials token from Twitch plus a POST carrying an Apicalypse
 * query, so a third-party package buys nothing a few Http:: calls don't.
 *
 * Follows the same shape as App\Services\Metadata\{TmdbClient,AnilistClient,
 * TvmazeClient}: final class, isEnabled() guard, Http:: facade, failures
 * logged rather than thrown unless the caller needs the data to proceed.
 */
final class IgdbClient
{
    private const TOKEN_URL = 'https://id.twitch.tv/oauth2/token';

    private const BASE = 'https://api.igdb.com/v4';

    private const TIMEOUT = 12;

    private const TOKEN_CACHE_KEY = 'igdb:client-token';

    /**
     * Fields the torrent meta pipeline needs. Mirrors the select/with tree the
     * abandoned package was given in ProcessIgdbGameJob.
     */
    private const GAME_FIELDS = 'id,name,summary,first_release_date,url,rating,rating_count,'
        .'cover.image_id,artworks.image_id,genres.id,genres.name,videos.video_id,videos.name,'
        .'involved_companies.company.id,involved_companies.company.name,'
        .'involved_companies.company.url,involved_companies.company.logo.image_id,'
        .'platforms.id,platforms.name,platforms.platform_logo.image_id';

    private string $clientId;

    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = (string) config('igdb.credentials.client_id', '');
        $this->clientSecret = (string) config('igdb.credentials.client_secret', '');
    }

    public function isEnabled(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /**
     * Full record for one game id, or null when IGDB has no such game.
     *
     * @return array<string, mixed>|null
     */
    public function game(int $id): ?array
    {
        $rows = $this->query('games', \sprintf('fields %s; where id = %d;', self::GAME_FIELDS, $id));

        return $rows[0] ?? null;
    }

    /**
     * Title search for the games category. Returns the same field tree as
     * game() so a picked result needs no second round trip.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $title, int $limit = 10): array
    {
        $title = trim($title);

        if ($title === '') {
            return [];
        }

        return $this->query('games', \sprintf(
            'search "%s"; fields %s; limit %d;',
            addcslashes($title, '"\\'),
            self::GAME_FIELDS,
            max(1, min($limit, 50)),
        ));
    }

    /**
     * Build a cover/artwork/logo URL from the image_id the API returns.
     *
     * @param string $size an IGDB size slug, e.g. cover_big, cover_small, 1080p, thumb
     */
    public static function imageUrl(string $imageId, string $size = 'cover_big'): string
    {
        if ($imageId === '') {
            return '';
        }

        return \sprintf('https://images.igdb.com/igdb/image/upload/t_%s/%s.jpg', $size, $imageId);
    }

    /**
     * POST an Apicalypse query to an IGDB endpoint.
     *
     * A 401 means the cached token was revoked or rotated early, so it is
     * dropped and the request retried once with a fresh one.
     *
     * @return list<array<string, mixed>>
     */
    private function query(string $endpoint, string $body, bool $retryOnUnauthorized = true): array
    {
        if (!$this->isEnabled()) {
            Log::warning('igdb: TWITCH_CLIENT_ID/TWITCH_CLIENT_SECRET are not configured');

            return [];
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Client-ID'     => $this->clientId,
                    'Authorization' => 'Bearer '.$this->token(),
                    'Accept'        => 'application/json',
                ])
                ->withBody($body, 'text/plain')
                ->post(self::BASE.'/'.$endpoint);

            if ($response->status() === 401 && $retryOnUnauthorized) {
                Cache::forget(self::TOKEN_CACHE_KEY);

                return $this->query($endpoint, $body, false);
            }

            $rows = $response->throw()->json();

            return \is_array($rows) ? array_values($rows) : [];
        } catch (Throwable $e) {
            Log::warning('igdb: query to '.$endpoint.' failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Cached client-credentials token from Twitch.
     *
     * IGDB issues app tokens valid for about 60 days; the cache entry expires a
     * minute early so a token is never used on the edge of its lifetime.
     */
    private function token(): string
    {
        $token = Cache::get(self::TOKEN_CACHE_KEY);

        if (\is_string($token) && $token !== '') {
            return $token;
        }

        $payload = Http::timeout(self::TIMEOUT)
            ->asForm()
            ->post(self::TOKEN_URL, [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'client_credentials',
            ])
            ->throw()
            ->json();

        $token = $payload['access_token'] ?? null;

        if (!\is_string($token) || $token === '') {
            throw new RuntimeException('igdb: Twitch returned no access_token');
        }

        $ttl = max(60, (int) ($payload['expires_in'] ?? 3600) - 60);

        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }
}
