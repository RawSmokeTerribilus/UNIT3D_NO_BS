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

use App\Jobs\ProcessIgdbGameJob;
use App\Models\IgdbGame;
use App\Models\Torrent;
use Illuminate\Console\Command;
use Throwable;

/**
 * Rellena las fichas de juego que faltan detrás de los torrents con IGDB.
 *
 * El gemelo de `books:sync` para la otra categoría nueva. Hasta ahora los juegos
 * no tenían red: `meta:sync` sólo mira películas y series, y `FetchMeta` --que sí
 * tiene rama de IGDB-- ni está en el planificador ni tiene botón, y además
 * re-pide TODOS los ids en vez de sólo los que faltan.
 *
 * Sólo los que faltan por defecto: un torrent cuenta como pendiente cuando lleva
 * un id de IGDB y no hay fila suya en `igdb_games`. `--force` vuelve a pedir cada
 * id, que es como se recoge una corrección del proveedor.
 */
class SyncGameMeta extends Command
{
    protected $signature = 'games:sync
                            {--limit=100 : Cuántos torrents procesar}
                            {--force : Volver a pedir los ids que ya tienen ficha}
                            {--queue : Encolar en vez de ejecutar en línea}';

    protected $description = 'Rellena la ficha de IGDB de los torrents de juego que no la tienen';

    final public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $queued = (bool) $this->option('queue');

        $games = $this->pending($limit, $force);

        $this->info(\sprintf('%d juego(s) por procesar.', \count($games)));

        $ok = 0;
        $failed = 0;

        foreach ($games as $id) {
            if ($force) {
                cache()->forget("igdb-game-scraper:{$id}");
            }

            try {
                $job = new ProcessIgdbGameJob($id);

                if ($queued) {
                    dispatch($job);
                } else {
                    dispatch_sync($job);
                }

                $ok++;
                $this->line("  ok   {$id}");
            } catch (Throwable $e) {
                $failed++;
                $this->warn("  fail {$id}: ".$e->getMessage());
            }

            // ~4 peticiones por segundo, el mismo paso que `books:sync`.
            usleep(250_000);
        }

        $this->info(\sprintf('Hecho. %d ok, %d fallidos.', $ok, $failed));

        return self::SUCCESS;
    }

    /**
     * Ids de IGDB que están en un torrent pero no en `igdb_games`.
     *
     * @return list<int>
     */
    private function pending(int $limit, bool $force): array
    {
        // `Torrent` usa SoftDeletes: sin esto el conjunto cuenta filas que ya no
        // están, el mismo tropiezo que documenta `books:sync`.
        $query = Torrent::query()
            ->whereNotNull('igdb')
            ->where('igdb', '>', 0)
            ->whereNull('deleted_at');

        if (!$force) {
            $query->whereNotIn('igdb', IgdbGame::query()->select('id'));
        }

        /** @var list<int> $ids */
        $ids = array_map(static fn ($id): int => (int) $id, $query->distinct()->limit($limit)->pluck('igdb')->all());

        return $ids;
    }
}
