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

/**
 * MODIFICADO PARA NOBS
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
 * Se distribuye bajo la misma licencia, GNU AGPL v3.0.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;

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
     * Rebuild bootstrap/cache/config.php from config/*.php and the .env, and
     * restart the queue workers so they load it too.
     *
     * Until 2026-10-16 this dumped config()->all() of the running request: the
     * old cache it had booted from. A change in the .env or in config/*.php
     * never got in, and whatever the request had set at runtime (the locale
     * of whoever pressed) was baked into the cache. Now a separate
     * `php artisan config:cache` builds a fresh file under another name
     * (APP_CONFIG_CACHE) and rename() swaps it in, which is atomic: other
     * requests see the old file or the new one, never none. If anything
     * fails, the live file is left as it was.
     *
     * The workers are long-running processes that keep the config they
     * started with; queue:restart makes them exit after their current job
     * and Docker brings them back up (restart: unless-stopped). Without it a
     * changed token reaches the web but not the jobs.
     *
     * @param callable|null $between runs after the fresh file is built and
     *                               before it is swapped in (optimize:clear
     *                               deletes config.php; this keeps that gap
     *                               to milliseconds)
     * @return list<string>
     */
    private function rebuildConfig(?callable $between = null): array
    {
        $live = base_path('bootstrap/cache/config.php');
        $fresh = base_path('bootstrap/cache/config.fresh-'.getmypid().'.php');
        $lines = [];

        try {
            $result = Process::path(base_path())
                ->env(['APP_CONFIG_CACHE' => $fresh])
                ->timeout(25)
                ->run([(new PhpExecutableFinder())->find(false) ?: 'php', 'artisan', 'config:cache']);

            if (!$result->successful() || !is_file($fresh)) {
                @unlink($fresh);

                return ['❌ config:cache falló; la config en uso no se ha tocado: '.trim($result->errorOutput() ?: $result->output())];
            }

            $config = require $fresh;

            if (!\is_array($config) || empty($config['app']['key'])) {
                @unlink($fresh);

                return ['❌ La config nueva sale sin APP_KEY (¿.env ilegible?); la config en uso no se ha tocado.'];
            }

            if ($between !== null) {
                $lines = array_merge($lines, (array) $between());
            }

            if (!rename($fresh, $live)) {
                @unlink($fresh);

                return [...$lines, '❌ No se pudo poner la config nueva en '.$live];
            }
        } catch (\Throwable $e) {
            @unlink($fresh);

            return [...$lines, '❌ config:cache falló: '.$e->getMessage()];
        }

        $lines[] = '✓ Config reconstruida desde config/ y .env.';

        try {
            Artisan::call('queue:restart');
            $lines[] = '✓ Workers avisados: cargan la config nueva al acabar su job en curso.';
        } catch (\Throwable $e) {
            $lines[] = '❌ queue:restart: '.$e->getMessage();
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function report(array $lines): \Illuminate\Http\RedirectResponse
    {
        return to_route('staff.commands.index')->with('info', trim(implode("\n", array_filter($lines))));
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
     * No config:clear: it would leave the site without config.php until the
     * rebuild, and without it the site cannot read APP_KEY. The atomic swap
     * replaces it in one step.
     */
    public function clearConfig(): \Illuminate\Http\RedirectResponse
    {
        return $this->report($this->rebuildConfig());
    }

    /**
     * Apply .env changes (tokens, keys, hosts): fresh config + worker restart.
     *
     * Replaces «Vaciar cola Redis» (queue:flush), whose tip said it was
     * critical after token changes. queue:flush only deletes failed_jobs:
     * it neither emptied Redis nor applied anything. See the queues page
     * (/dashboard/queues) for failed jobs.
     */
    public function applyEnv(): \Illuminate\Http\RedirectResponse
    {
        return $this->report($this->rebuildConfig());
    }

    /**
     * Clear All Site Cache At Once.
     *
     * Views and routes are cleared; config is rebuilt with the atomic swap
     * instead of config:clear.
     */
    public function clearAllCache(): \Illuminate\Http\RedirectResponse
    {
        $lines = [];

        foreach (['view:clear', 'route:clear'] as $cmd) {
            try {
                Artisan::call($cmd);
                $lines[] = trim(Artisan::output());
            } catch (\Throwable $e) {
                $lines[] = "❌ {$cmd}: {$e->getMessage()}";
            }
        }

        return $this->report([...$lines, ...$this->rebuildConfig()]);
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

        return $this->report([...$lines, ...$this->rebuildConfig()]);
    }

    /**
     * Clear Optimization Cache.
     *
     * optimize:clear deletes config.php among other things; the fresh one is
     * built first and swapped in right after, so the gap is milliseconds.
     */
    public function optimizeClear(): \Illuminate\Http\RedirectResponse
    {
        return $this->report($this->rebuildConfig(function (): array {
            try {
                Artisan::call('optimize:clear');

                return [trim(Artisan::output())];
            } catch (\Throwable $e) {
                return ["❌ optimize:clear failed: {$e->getMessage()}"];
            }
        }));
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
        // Walks the whole catalog with a throttled TMDB call per title — far too
        // long for an inline HTTP request (504). Dispatch to the dedicated
        // metadata worker (meta-refresh queue).
        Artisan::queue('tmdb:sync-trailers')->onQueue('meta-refresh');

        return to_route('staff.commands.index')
            ->with('info', '✅ Sync de trailers encolado en el worker de metadata. Revisa el catálogo en unos minutos.');
    }

    /**
     * Force re-fetch all trailers from TMDB, even already-set ones.
     */
    public function syncMissingTrailersForce(): \Illuminate\Http\RedirectResponse
    {
        Artisan::queue('tmdb:sync-trailers', ['--force' => true])->onQueue('meta-refresh');

        return to_route('staff.commands.index')
            ->with('info', '✅ Sync forzado de trailers encolado en el worker de metadata.');
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
     * Comprobador de contadores: seeders/leechers (columna, peers, announce) y
     * balance (columna, history). Sólo lee.
     */
    public function checkTrackerCounters(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('tracker:counters');
    }

    /**
     * Reescribe seeders/leechers desde la memoria del announce (quien manda).
     */
    public function fixTrackerPeerCounters(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('tracker:counters', ['--fix-peers' => true]);
    }

    /**
     * Reescribe torrents.balance desde el tráfico de history.
     */
    public function fixTrackerBalance(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('tracker:counters', ['--fix-balance' => true]);
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

    /**
     * Backfill book and audiobook metadata for torrents that carry an ISBN or
     * an ASIN but have no metadata row yet.
     */
    public function booksSync(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('books:sync', ['--limit' => 15]);
    }

    /**
     * Re-fetch the metadata of a bounded batch of book ids that already have a
     * row, so a provider correction upstream reaches this catalogue.
     */
    public function booksSyncForce(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('books:sync', ['--force' => true, '--limit' => 15]);
    }

    /**
     * Cachea las fichas de autor (foto, biografia, fechas) que faltan.
     */
    public function booksSyncAuthors(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('books:sync-authors', ['--limit' => 10]);
    }

    /**
     * Rellena la ficha de IGDB de los torrents de juego que no la tienen.
     */
    public function gamesSync(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('games:sync', ['--limit' => 15]);
    }

    /**
     * Vuelve a pedir a IGDB la ficha de un lote de juegos que ya la tienen, por
     * si el proveedor la ha corregido.
     */
    public function gamesSyncForce(): \Illuminate\Http\RedirectResponse
    {
        return $this->executeArtisanSafely('games:sync', ['--force' => true, '--limit' => 15]);
    }

    /**
     * Pre-warm the art-proxy image cache — full backfill, detached background process.
     *
     * The full sweep runs far past the queue worker's 300s timeout, so it can't
     * be queued or run synchronously here. We spawn a detached CLI process
     * (`setsid` + `nohup`) that outlives this request. The command is idempotent
     * (skips already-cached); the pgrep guard blocks a second concurrent run.
     * Steady-state warming is handled automatically by the scheduled budgeted run.
     */
    public function warmArtCache(): \Illuminate\Http\RedirectResponse
    {
        exec('pgrep -f "artisan art:warm" > /dev/null 2>&1', $out, $running);

        if ($running === 0) {
            return to_route('staff.commands.index')->with('info', 'ℹ️ art:warm ya está en ejecución — progreso en storage/logs/art-warm.log');
        }

        $cmd = sprintf(
            'cd %s && nohup setsid php artisan art:warm --rate=8 >> %s 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellarg(storage_path('logs/art-warm.log'))
        );
        exec($cmd);

        return to_route('staff.commands.index')->with('info', '🔥 Art cache warm iniciado en segundo plano. Progreso: storage/logs/art-warm.log');
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
