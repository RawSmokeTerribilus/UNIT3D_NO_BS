<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Group;
use App\Services\Unit3dAnnounce;
use Illuminate\Console\Command;

class TrackerSyncGroups extends Command
{
    protected $signature   = 'tracker:sync-groups';
    protected $description = 'Push all groups to the Rust tracker (force resync)';

    final public function handle(): int
    {
        if (!config('announce.external_tracker.is_enabled')) {
            $this->warn('External tracker not enabled — nothing to sync.');
            return self::SUCCESS;
        }

        $total   = 0;
        $success = 0;
        $failed  = 0;

        Group::all()->each(function ($group) use (&$total, &$success, &$failed): void {
            $total++;
            Unit3dAnnounce::addGroup($group) ? $success++ : $failed++;
        });

        $this->info("✓ Groups synced to tracker: {$success}/{$total}" . ($failed > 0 ? " ({$failed} failed)" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
