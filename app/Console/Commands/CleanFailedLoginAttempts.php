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

use App\Models\FailedLoginAttempt;
use Illuminate\Console\Command;
use Throwable;

class CleanFailedLoginAttempts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:failed_login_attempts {--all : Delete ALL records} {--days=30 : Delete records older than N days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean failed login attempts from database (manual operation)';

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    final public function handle(): void
    {
        try {
            if ($this->option('all')) {
                $count = FailedLoginAttempt::query()->count();
                FailedLoginAttempt::query()->truncate();
                $this->info("✅ All {$count} failed login attempts deleted.");
            } else {
                $days = (int) $this->option('days');
                $count = FailedLoginAttempt::query()
                    ->where('created_at', '<', now()->subDays($days))
                    ->count();

                if ($count === 0) {
                    $this->info("ℹ️  No records older than {$days} days found.");
                    return;
                }

                if ($this->confirm("Delete {$count} failed login records older than {$days} days?", true)) {
                    FailedLoginAttempt::query()
                        ->where('created_at', '<', now()->subDays($days))
                        ->delete();
                    $this->info("✅ Deleted {$count} failed login attempts older than {$days} days.");
                } else {
                    $this->warn("Operation cancelled.");
                }
            }
        } catch (Throwable $exception) {
            $this->error("Error: {$exception->getMessage()}");
            throw $exception;
        }
    }
}
