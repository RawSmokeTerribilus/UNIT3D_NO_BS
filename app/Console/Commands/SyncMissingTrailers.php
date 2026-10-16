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

use App\Jobs\SyncTrailersBatch;
use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use Illuminate\Console\Command;

/**
 * Rehidrata los tráilers de las fichas que ya existen: lo que necesita un
 * tracker ya montado que estrena la función, o tras cambiar el criterio. El
 * día a día lo lleva el refresco normal de metadatos, que usa el MISMO
 * criterio (App\Services\Tmdb\TrailerPicker).
 *
 * Por defecto reparte las fichas en lotes (SyncTrailersBatch) en la cola
 * `meta-refresh`: el job único de antes recorría todo y el worker lo mataba a
 * los 300 s. Con --inline lo hace todo en este proceso (consola, sin límite).
 */
class SyncMissingTrailers extends Command
{
    protected $signature = 'tmdb:sync-trailers
        {--force : Repasa también las fichas que ya tienen tráiler}
        {--inline : Lo hace todo aquí mismo en vez de encolar lotes (consola)}';

    protected $description = 'Rehidrata los tráilers de YouTube desde TMDB (películas y series), en lotes encolados';

    final public function handle(): void
    {
        $force = (bool) $this->option('force');

        foreach (['movie' => TmdbMovie::class, 'tv' => TmdbTv::class] as $type => $model) {
            $ids = $model::query()
                ->when(!$force, fn ($query) => $query->whereNull('trailer'))
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if ($ids === []) {
                $this->info("{$type}: nada que rehidratar.");

                continue;
            }

            if ($this->option('inline')) {
                $bar = $this->output->createProgressBar(\count($ids));
                $result = SyncTrailersBatch::process($type, $ids, $force, fn () => $bar->advance());
                $bar->finish();
                $this->newLine();
                $this->info(\sprintf('%s: %d fichas, %d con tráiler, %d fallos.', $type, \count($ids), $result['found'], $result['failed']));

                continue;
            }

            $batches = array_chunk($ids, SyncTrailersBatch::SIZE);

            foreach ($batches as $batch) {
                SyncTrailersBatch::dispatch($type, $batch, $force)->onQueue('meta-refresh');
            }

            $this->info(\sprintf('%s: %d fichas en %d lotes encolados en meta-refresh.', $type, \count($ids), \count($batches)));
        }
    }
}
