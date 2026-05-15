<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Torrent;
use App\Services\Unit3dAnnounce;
use Illuminate\Console\Command;

class TrackerSyncTorrents extends Command
{
    protected $signature   = 'tracker:sync-torrents';
    protected $description = 'Push all torrents to the Rust tracker (force resync)';

    final public function handle(): int
    {
        if (!config('announce.external_tracker.is_enabled')) {
            $this->warn('External tracker not enabled — nothing to sync.');
            return self::SUCCESS;
        }

        $total   = 0;
        $success = 0;
        $failed  = 0;

        Torrent::withoutGlobalScopes()
            ->chunkById(100, function ($torrents) use (&$total, &$success, &$failed): void {
                foreach ($torrents as $torrent) {
                    $total++;
                    Unit3dAnnounce::addTorrent($torrent) ? $success++ : $failed++;
                }
            });

        $this->info("✓ Torrents synced to tracker: {$success}/{$total}" . ($failed > 0 ? " ({$failed} failed)" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
