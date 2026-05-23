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

    /**
     * Write config.php directly from the current running app's config values.
     *
     * Using Artisan::call('config:cache') or a subprocess both require a window
     * where config.php is deleted first, during which concurrent requests 500.
     * Writing directly is atomic — the old config.php is never removed until
     * the new one is ready — and APP_KEY is guaranteed correct because it comes
     * from the already-booted app, not from a subprocess environment.
     */
    private function rebuildConfigCache(): string
    {
        $configPath = base_path('bootstrap/cache/config.php');

        try {
            $config = config()->all();
            $contents = '<?php return ' . var_export($config, true) . ';' . PHP_EOL;

            if (file_put_contents($configPath, $contents, LOCK_EX) === false) {
                return '❌ config:cache failed: could not write ' . $configPath;
            }
        } catch (\Throwable $e) {
            return '❌ config:cache failed: ' . $e->getMessage();
        }

        return 'Configuration cached successfully.';
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
     *
     * Always rebuilds immediately — clearing without rebuilding leaves the site unable to read APP_KEY.
     */
    public function clearConfig(): \Illuminate\Http\RedirectResponse
    {
        try {
            Artisan::call('config:clear');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            return to_route('staff.commands.index')->with('info', "❌ config:clear failed: {$e->getMessage()}");
        }

        $output .= "\n" . $this->rebuildConfigCache();

        return to_route('staff.commands.index')->with('info', trim($output));
    }

    /**
     * Clear All Site Cache At Once.
     *
     * Runs each step explicitly so config:cache uses a subprocess (safe from PHP-FPM context).
     */
    public function clearAllCache(): \Illuminate\Http\RedirectResponse
    {
        $lines = [];

        foreach (['view:clear', 'route:clear', 'config:clear'] as $cmd) {
            try {
                Artisan::call($cmd);
                $lines[] = trim(Artisan::output());
            } catch (\Throwable $e) {
                $lines[] = "❌ {$cmd}: {$e->getMessage()}";
            }
        }

        $lines[] = $this->rebuildConfigCache();

        return to_route('staff.commands.index')->with('info', trim(implode("\n", array_filter($lines))));
    }

    /**
     * Set All Site Cache At Once.
     *
     * Runs each step explicitly so config:cache uses a subprocess (safe from PHP-FPM context).
     */
    public function setAllCache(): \Illuminate\Http\RedirectResponse
    {
        $lines = [];

        foreach (['view:cache', 'route:cache'] as $cmd) {
            try {
                Artisan::call($cmd);
                $lines[] = trim(Artisan::output());
            } catch (\Throwable $e) {
                $lines[] = "❌ {$cmd}: {$e->getMessage()}";
            }
        }

        $lines[] = $this->rebuildConfigCache();

        return to_route('staff.commands.index')->with('info', trim(implode("\n", array_filter($lines))));
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
     *
     * Rebuilds config:cache after optimize:clear, because optimize:clear deletes config.php.
     */
    public function optimizeClear(): \Illuminate\Http\RedirectResponse
    {
        try {
            Artisan::call('optimize:clear');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            return to_route('staff.commands.index')->with('info', "❌ optimize:clear failed: {$e->getMessage()}");
        }

        $output .= "\n" . $this->rebuildConfigCache();

        return to_route('staff.commands.index')->with('info', trim($output));
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
    // TMDB
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Backfill missing YouTube trailers from TMDB (movies + TV).
     */
    public function syncMissingTrailers(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('tmdb:sync-trailers');
    }

    /**
     * Force re-fetch all trailers from TMDB, even already-set ones.
     */
    public function syncMissingTrailersForce(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('tmdb:sync-trailers', ['--force' => true]);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // RUST TRACKER SYNC
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Push all torrents to the Rust tracker.
     */
    public function syncTrackerTorrents(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('tracker:sync-torrents');
    }

    /**
     * Push all users to the Rust tracker.
     */
    public function syncTrackerUsers(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('tracker:sync-users');
    }

    /**
     * Push all groups to the Rust tracker.
     */
    public function syncTrackerGroups(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('tracker:sync-groups');
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
    // METADATA
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Resolve metadata ids for a bounded batch of unresolved torrents.
     * The full initial backfill is a CLI run; the scheduler drains the rest.
     */
    public function metaSync(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('meta:sync', ['--limit' => 15]);
    }

    /**
     * Re-resolve a bounded batch of the stalest-resolved torrents.
     */
    public function metaSyncForce(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('meta:sync', ['--force' => true, '--limit' => 15]);
    }

    /**
     * Rotate each title's active cover from the artwork pool.
     */
    public function metaRotateCovers(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('meta:rotate-covers');
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
