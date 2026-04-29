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

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

/**
 * @see \Tests\Feature\Http\Controllers\Staff\CommandControllerTest
 */
class CommandController extends Controller
{
    /**
     * Display All Commands.
     */
    public function index(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('Staff.command.index');
    }

    private function executeArtisanSafely(string $command, array $parameters = []): \Illuminate\Http\RedirectResponse
    {
        try {
            Artisan::call($command, $parameters);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $output = "❌ Error: {$e->getMessage()}";
        }

        return to_route('staff.commands.index')->with('info', $output);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // MAINTENANCE & SITE CONTROL
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Bring Site Into Maintenance Mode.
     * 
     * NOTE: Laravel 12 doesn't support --allow option.
     * Staff can still access /dashboard/commands/* via custom middleware.
     */
    public function maintenanceEnable(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('down');
    }

    /**
     * Bring Site Out Of Maintenance Mode.
     */
    public function maintenanceDisable(): \Illuminate\Http\RedirectResponse
    {
        Artisan::call('up');
        $output = trim(Artisan::output());
        
        // Extra safety: try to remove the down file directly if Artisan fails
        $downFile = storage_path('framework/down');
        if (file_exists($downFile) && empty($output)) {
            @unlink($downFile);
            $output = '✅ Maintenance mode disabled (emergency direct removal)';
        }

        return to_route('staff.commands.index')
            ->with('info', $output ?: '✅ Maintenance mode disabled');
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // CACHING & PERFORMANCE
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Clear Site Cache.
     */
    public function clearCache(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('cache:clear');
    }

    /**
     * Clear Site View Cache.
     */
    public function clearView(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('view:clear');
    }

    /**
     * Clear Site Routes Cache.
     */
    public function clearRoute(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('route:clear');
    }

    /**
     * Clear Site Config Cache.
     */
    public function clearConfig(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('config:clear');
    }

    /**
     * Clear All Site Cache At Once.
     */
    public function clearAllCache(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('clear:all_cache');
    }

    /**
     * Set All Site Cache At Once.
     */
    public function setAllCache(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('set:all_cache');
    }

    /**
     * Clear Redis Queue (Critical after token changes).
     */
    public function flushQueue(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('queue:flush');
    }

    /**
     * Clear Optimization Cache.
     */
    public function optimizeClear(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('optimize:clear');
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // CRITICAL DATA OPERATIONS
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Update Email Blacklist From Remote Source.
     */
    public function updateEmailBlacklist(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('auto:email-blacklist-update');
    }

    /**
     * Register Telegram Webhook With API.
     */
    public function setTelegramWebhook(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('telegram:set-webhook', ['--force' => true]);
    }

    /**
     * Repair & Flush Meilisearch Torrents Index.
     */
    public function fixMeilisearch(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('auto:sync_torrents_to_meilisearch', ['--wipe' => true]);
    }

    /**
     * Reindex All Torrents In Meilisearch.
     */
    public function reindexScout(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('auto:sync_torrents_to_meilisearch');
    }

    /**
     * Full Meilisearch Repair (equivalent to NO_BS_meilisearch.sh steps 1-5).
     * Health check → create indices → sync settings → reindex torrents + people → validate.
     */
    public function meilisearchFullRepair(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely("meilisearch:full-repair", ["--force" => true]);
    }

    /**
     * Clean Failed Login Attempts (Manual cleanup, DB only).
     */
    public function cleanFailedLogins(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('clean:failed_login_attempts', [
            '--all' => true,
            '--no-interaction' => true,
        ]);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // PEER & TORRENT MANAGEMENT
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Auto Flush Old Peers From Database.
     */
    public function flushOldPeers(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('auto:flush_peers');
    }

    /**
     * Reset User's Daily Peer Flush Quota.
     */
    public function resetUserFlushes(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('auto:reset_user_flushes');
    }

    /**
     * Sync Peer Data & Consistency.
     */
    public function syncPeers(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('auto:sync_peers');
    }

    /**
     * Sync Torrents To Meilisearch.
     */
    public function syncTorrents(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('auto:sync_torrents_to_meilisearch');
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // USER & CLEANUP
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Ban Users With Disposable Email Addresses.
     */
    public function banDisposableUsers(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('auto:ban_disposable_users');
    }

    /**
     * Deactivate Expired User Warnings.
     */
    public function deactivateWarnings(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('auto:deactivate_warning');
    }

    /**
     * Generate Telegram Verification Tokens.
     */
    public function generateTelegramTokens(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('telegram:generate-tokens', ['--force' => true]);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // TESTING & UTILITIES
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Send Test Email To Test Email Configuration.
     */
    public function testEmail(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('test:email', ['--force' => true]);
    }

    /**
     * Create Storage Symlink For Public Access.
     */
    public function createStorageLink(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('storage:link');
    }
}
