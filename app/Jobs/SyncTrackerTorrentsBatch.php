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

use App\Models\Torrent;
use App\Services\Unit3dAnnounce;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Un lote de `tracker:sync-torrents`: empuja estos torrents al announce.
 *
 * El comando hacía el catálogo entero dentro de la petición web del panel y
 * en prod ya pasaba del límite de 30 s de PHP. Ahora reparte en lotes; el
 * último pasa el comprobador de contadores y deja todo en el log (visible en
 * /dashboard/laravel-log).
 */
class SyncTrackerTorrentsBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** ~1–2 s por lote: lejos del límite del worker (60 s). */
    public const SIZE = 500;

    public int $timeout = 55;

    public int $tries = 1;

    /**
     * @param array<int> $ids
     */
    public function __construct(public array $ids, public int $batch, public int $batches)
    {
    }

    public function handle(): void
    {
        $result = self::process($this->ids);

        Log::info(\sprintf(
            'tracker:sync-torrents lote %d/%d: %d/%d torrents empujados al announce%s',
            $this->batch,
            $this->batches,
            $result['success'],
            \count($this->ids),
            $result['failed'] > 0 ? " ({$result['failed']} fallidos)" : '',
        ));

        if ($this->batch === $this->batches) {
            TrackerCountersCheck::dispatch('tracker:sync-torrents');
        }
    }

    /**
     * También lo usa `tracker:sync-torrents --inline`.
     *
     * @param  array<int>                         $ids
     * @return array{success: int, failed: int}
     */
    public static function process(array $ids): array
    {
        $success = 0;
        $failed = 0;

        foreach (Torrent::withoutGlobalScopes()->whereIn('id', $ids)->get() as $torrent) {
            Unit3dAnnounce::addTorrent($torrent) ? $success++ : $failed++;
        }

        return ['success' => $success, 'failed' => $failed];
    }
}
