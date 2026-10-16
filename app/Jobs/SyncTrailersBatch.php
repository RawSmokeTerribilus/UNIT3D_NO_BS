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

namespace App\Jobs;

use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use App\Services\Tmdb\TrailerPicker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Un lote de `tmdb:sync-trailers`: pide los vídeos de cada ficha a TMDB y
 * guarda el tráiler con el criterio común (TrailerPicker).
 *
 * El comando reparte el catálogo en lotes para que ninguno roce el
 * `--timeout=300` del worker de metadatos: el job único de antes recorría los
 * miles de fichas de una vez y moría siempre a medias.
 */
class SyncTrailersBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Lotes de 100 a ~4 peticiones/s: ~40 s, lejos del límite del worker. */
    public const SIZE = 100;

    public int $timeout = 280;

    public int $tries = 1;

    /**
     * @param 'movie'|'tv' $type
     * @param array<int>   $ids
     */
    public function __construct(public string $type, public array $ids, public bool $force = false)
    {
    }

    public function handle(): void
    {
        self::process($this->type, $this->ids, $this->force);
    }

    /**
     * También lo usa `tmdb:sync-trailers --inline` desde consola.
     *
     * @param  'movie'|'tv'                       $type
     * @param  array<int>                         $ids
     * @return array{found: int, failed: int}
     */
    public static function process(string $type, array $ids, bool $force, ?callable $tick = null): array
    {
        $model = $type === 'movie' ? TmdbMovie::class : TmdbTv::class;
        $found = 0;
        $failed = 0;

        foreach ($ids as $id) {
            usleep(250_000); // ~4 req/s, lejos del límite de TMDB (40/10 s)

            try {
                $response = Http::timeout(15)->get(
                    "https://api.TheMovieDB.org/3/{$type}/{$id}/videos",
                    ['api_key' => config('api-keys.tmdb'), 'include_video_language' => TrailerPicker::VIDEO_LANGUAGES],
                );

                if ($response->successful()) {
                    $key = TrailerPicker::pick($response->json('results', []));

                    // Sin --force no se pisa nada con NULL: sólo se rellena.
                    if ($key !== null || $force) {
                        $model::whereKey($id)->update(['trailer' => $key]);
                    }

                    $found += (int) ($key !== null);
                } else {
                    $failed++;
                    Log::notice("tmdb:sync-trailers: {$type} {$id} HTTP {$response->status()}");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::notice("tmdb:sync-trailers: {$type} {$id} {$e->getMessage()}");
            }

            if ($tick !== null) {
                $tick();
            }
        }

        return ['found' => $found, 'failed' => $failed];
    }
}
