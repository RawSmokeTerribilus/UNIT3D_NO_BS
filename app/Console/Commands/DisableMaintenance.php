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

use Illuminate\Console\Command;

class DisableMaintenance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintenance:off';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emergency: Disable maintenance mode immediately';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Remove the down file directly
        $downFile = storage_path('framework/down');
        
        if (file_exists($downFile)) {
            unlink($downFile);
            $this->info('✅ Maintenance mode disabled (emergency override)');
            return self::SUCCESS;
        }

        $this->warn('ℹ️  Maintenance mode is not active');
        return self::SUCCESS;
    }
}
