<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
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
