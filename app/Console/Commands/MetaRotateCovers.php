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

namespace App\Console\Commands;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\MetadataArtwork;
use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use App\Models\Torrent;
use App\Services\Metadata\CoverLadder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Rotate each title's active cover from the metadata_artwork pool.
 *
 * Each run advances every title's cover one step through its artwork pool:
 * it finds the current tmdb_movies.poster / tmdb_tv.poster in the pool and
 * writes the next URL. Scheduled daily (steps once per day) and runnable on
 * demand from the staff panel (each press steps once more). Titles with
 * fewer than two covers are skipped — nothing to rotate between.
 *
 * Non-TMDB poster URLs pass through tmdb_image() unchanged (it only rewrites
 * the '/original/' segment, which foreign URLs do not contain), so no display
 * code needs to change.
 *
 * After rotation, invalidates the Redis cache families that snapshot poster
 * URLs into JSON / HTML responses: the `/api/torrents/filter` flexible cache
 * (5min fresh / 6min stale, one entry per filter-query combination), the
 * `torrent-api-index` cache, and the `also-downloaded` block (12h / 14d).
 * Without this, listing and torrent-show pages keep serving stale poster
 * URLs for hours after the DB has already been rotated.
 */
class MetaRotateCovers extends Command
{
    protected $signature = 'meta:rotate-covers';

    protected $description = "Rotate each title's active cover from the metadata_artwork pool";

    final public function handle(): int
    {
        $startedAt = microtime(true);
        $rotated = 0;
        $eligible = 0;
        $skipped = 0;

        $groups = MetadataArtwork::query()
            ->where('type', 'poster')
            ->orderBy('category')
            ->orderBy('tmdb_id')
            ->orderBy('source')
            ->get()
            ->groupBy(fn (MetadataArtwork $a): string => $a->category.':'.$a->tmdb_id);

        Log::info('meta:rotate-covers starting', [
            'artwork_groups' => $groups->count(),
        ]);

        foreach ($groups as $group) {
            $first = $group->first();
            $urls = $group->pluck('url')->values()->all();
            $count = \count($urls);

            if ($count < 2) {
                $skipped++;
                continue; // nothing to rotate between
            }

            $eligible++;
            $model = $first->category === 'MOVIE' ? TmdbMovie::class : TmdbTv::class;
            $current = $model::query()->whereKey($first->tmdb_id)->value('poster');

            $index = array_search($current, $urls, true);
            $next = $index === false ? 0 : ((int) $index + 1) % $count;
            $pick = $urls[$next];

            if ($pick !== $current) {
                $model::query()->whereKey($first->tmdb_id)->update(['poster' => $pick]);
                $rotated++;

                // Meilisearch bakes tmdb_movies.poster / tmdb_tv.poster into
                // its document via Torrent::toSearchableArray(). The poster
                // column update does not touch torrents.updated_at, so the
                // every-15min AutoSyncTorrentsToMeilisearch run never picks
                // this torrent up — Meilisearch (and any consumer reading
                // from it: QuickSearch, /api/torrents/filter) keeps serving
                // the old poster URL forever. Force a re-index here.
                $column = $first->category === 'MOVIE' ? 'tmdb_movie_id' : 'tmdb_tv_id';
                Torrent::query()->where($column, $first->tmdb_id)->searchable();
            }
        }

        $this->info("Advanced covers for {$rotated} titles (eligible={$eligible}, single-source skipped={$skipped}).");

        // Books keep their pool in a json column rather than in
        // metadata_artwork: that table is keyed (category, tmdb_id) and a book
        // has no TMDB id, so a second pass handles them on their own terms.
        //
        // OJO, no es una errata: este comando ROTA vídeo y CLAVA libros. Los
        // libros se sacaron de la rotación el 2026-08-25 a propósito — su pool
        // es una escalera de calidades del mismo dibujo, no portadas distintas.
        // El porqué entero, con las medidas, en pinBookCovers() y en el vault:
        // Knowledge/Infrastructure/UNIT3D/rotacion-de-portadas-libros.md
        $rotated += $this->pinBookCovers();

        $cacheDeleted = 0;
        if ($rotated > 0) {
            $cacheDeleted = $this->forgetTorrentCaches();
        }

        Log::info('meta:rotate-covers finished', [
            'artwork_groups'  => $groups->count(),
            'eligible'        => $eligible,
            'rotated'         => $rotated,
            'single_source'   => $skipped,
            'cache_keys_dropped' => $cacheDeleted,
            'duration_secs'   => round(microtime(true) - $startedAt, 1),
        ]);

        return self::SUCCESS;
    }

    /**
     * Clava la portada activa de cada libro y audiolibro en la mejor del pool.
     *
     * Aquí no se rota, y es a propósito. El pool de un libro no son portadas
     * distintas: es la MISMA imagen en varias calidades (xl/l/m/s), que es
     * justo para lo que existe CoverLadder. Rotar por esa escalera servía el
     * cover de 128 px como activo tres días de cada cinco; y en un ISBN
     * español el último peldaño es el respaldo de OpenLibrary, que devuelve
     * 404 — que es exactamente lo que `?default=false` está puesto para
     * provocar. Ver `Knowledge/Infrastructure/UNIT3D/portadas-de-libro-intel.md`.
     *
     * El paso diario se mantiene porque sigue siendo autocurativo: si el
     * proveedor añade una calidad mejor, la siguiente vuelta la recoge.
     * Idempotente: si ya está clavada, no escribe.
     */
    private function pinBookCovers(): int
    {
        $pinned = 0;

        /** @var array<class-string<Book|Audiobook>, string> $sets */
        $sets = [
            Book::class      => 'isbn13',
            Audiobook::class => 'asin',
        ];

        foreach ($sets as $model => $torrentColumn) {
            foreach ($model::query()->whereNotNull('cover_urls')->cursor() as $row) {
                $pool = $row->cover_urls ?? [];

                if ($pool === []) {
                    continue;
                }

                // Mismo criterio con el que ProcessBookJob escribe una fila
                // nueva, para que un refetch no contradiga a este paso.
                // CoverLadder acepta las dos formas del pool: la lista plana
                // de cadenas de las filas viejas y la de entradas con tamaño.
                $pick = CoverLadder::pick($pool, 800)
                    ?? (\is_array($pool[0]) ? ($pool[0]['url'] ?? null) : $pool[0]);

                if ($pick === null || $pick === $row->cover_url) {
                    continue;
                }

                $row->forceFill(['cover_url' => $pick])->saveQuietly();
                $pinned++;

                // Same reindex reason as above: the cover lives on the meta
                // row, so touching it never bumps torrents.updated_at and the
                // periodic Meilisearch sync would not notice.
                Torrent::query()->where($torrentColumn, $row->getKey())->searchable();
            }
        }

        if ($pinned > 0) {
            $this->info("Pinned covers for {$pinned} book/audiobook edition(s).");
        }

        return $pinned;
    }

    /**
     * Drop the caches that snapshot poster URLs.
     *
     * `cache()->flexible()` uses raw keys (no tags), so we can't target by tag.
     * We scan by key substring against the Redis cache connection and DEL the
     * matching keys. Patterns cover the three known cached read-paths that
     * embed poster URLs from `tmdb_movies.poster` / `tmdb_tv.poster`:
     *   - `*api/torrents/filter*`   — listing API, one entry per query combo
     *   - `*torrent-api-index*`     — top-list API
     *   - `*also-downloaded*`       — per-torrent sidebar (12h / 14d TTL)
     */
    private function forgetTorrentCaches(): int
    {
        $redis = Redis::connection('cache');
        $patterns = [
            '*api/torrents/filter*',
            '*torrent-api-index*',
            '*also-downloaded*',
        ];

        $total = 0;
        foreach ($patterns as $pattern) {
            $keys = $redis->keys($pattern);
            if ($keys === []) {
                continue;
            }
            $redis->del(...$keys);
            $total += \count($keys);
        }

        $this->info("Invalidated {$total} cached entries (listing / also-downloaded).");

        return $total;
    }
}
