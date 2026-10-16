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

use App\Jobs\SyncTrackerTorrentsBatch;
use App\Models\Torrent;
use Illuminate\Console\Command;

/**
 * Empuja todos los torrents al announce (estado, borrado, factores). Los
 * contadores de los que ya están en memoria no se tocan: los lleva el propio
 * announce a partir de sus pares (`scheduler::recount`).
 *
 * Por defecto reparte en lotes encolados (SyncTrackerTorrentsBatch): hacerlo
 * todo en la petición del panel pasaba del límite de 30 s. --inline lo hace
 * aquí mismo (consola).
 */
class TrackerSyncTorrents extends Command
{
    protected $signature = 'tracker:sync-torrents
        {--inline : Lo hace todo aquí mismo en vez de encolar lotes (consola)}';

    protected $description = 'Empuja todos los torrents al tracker Rust (en lotes encolados)';

    final public function handle(): int
    {
        if (!config('announce.external_tracker.is_enabled')) {
            $this->warn('External tracker not enabled — nothing to sync.');

            return self::SUCCESS;
        }

        $ids = Torrent::withoutGlobalScopes()->orderBy('id')->pluck('id')->all();

        if ($this->option('inline')) {
            $result = SyncTrackerTorrentsBatch::process($ids);
            $this->info("✓ Torrents synced to tracker: {$result['success']}/".\count($ids).($result['failed'] > 0 ? " ({$result['failed']} failed)" : ''));

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $batches = array_chunk($ids, SyncTrackerTorrentsBatch::SIZE);
        $total = \count($batches);

        foreach ($batches as $i => $batch) {
            SyncTrackerTorrentsBatch::dispatch($batch, $i + 1, $total);
        }

        $this->info(\sprintf(
            '✓ %d torrents en %d lotes encolados. El worker los empuja al announce y el último pasa el comprobador de contadores: resultado en el log (Panel → Laravel log).',
            \count($ids),
            $total,
        ));

        return self::SUCCESS;
    }
}
