<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Unit3dAnnounce;
use Illuminate\Console\Command;

class TrackerSyncUsers extends Command
{
    protected $signature   = 'tracker:sync-users';
    protected $description = 'Push all users to the Rust tracker (force resync)';

    final public function handle(): int
    {
        if (!config('announce.external_tracker.is_enabled')) {
            $this->warn('External tracker not enabled — nothing to sync.');
            return self::SUCCESS;
        }

        $total   = 0;
        $success = 0;
        $failed  = 0;

        User::whereNull('deleted_at')
            ->chunkById(100, function ($users) use (&$total, &$success, &$failed): void {
                foreach ($users as $user) {
                    $total++;
                    Unit3dAnnounce::addUser($user) ? $success++ : $failed++;
                }
            });

        $this->info("✓ Users synced to tracker: {$success}/{$total}" . ($failed > 0 ? " ({$failed} failed)" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
