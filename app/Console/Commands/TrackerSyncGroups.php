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
