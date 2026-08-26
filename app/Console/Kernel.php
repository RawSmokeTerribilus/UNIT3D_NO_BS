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

namespace App\Console;

use App\Console\Commands\AutoBonAllocation;
use App\Console\Commands\AutoCacheRandomMediaIds;
use App\Console\Commands\AutoCacheUserLeechCounts;
use App\Console\Commands\AutoCorrectHistory;
use App\Console\Commands\AutoDeactivateWarning;
use App\Console\Commands\AutoDeleteStoppedPeers;
use App\Console\Commands\AutoDisableInactiveUsers;
use App\Console\Commands\AutoFlushPeers;
use App\Console\Commands\AutoGroup;
use App\Console\Commands\AutoHighspeedTag;
use App\Console\Commands\AutoNerdStat;
use App\Console\Commands\AutoPreWarning;
use App\Console\Commands\AutoRecycleAudits;
use App\Console\Commands\AutoRecycleClaimedTorrentRequests;
use App\Console\Commands\AutoRecycleFailedLogins;
use App\Console\Commands\AutoRecycleInvites;
use App\Console\Commands\AutoRefundDownload;
use App\Console\Commands\AutoRemoveExpiredDonors;
use App\Console\Commands\AutoRemoveFeaturedTorrent;
use App\Console\Commands\AutoRemovePersonalFreeleech;
use App\Console\Commands\AutoRemoveReseeds;
use App\Console\Commands\AutoRemoveTimedTorrentBuffs;
use App\Console\Commands\AutoResetUserFlushes;
use App\Console\Commands\AutoRewardResurrection;
use App\Console\Commands\AutoSoftDeleteDisabledUsers;
use App\Console\Commands\AutoSyncPeopleToMeilisearch;
use App\Console\Commands\AutoSyncTorrentsToMeilisearch;
use App\Console\Commands\AutoTorrentBalance;
use App\Console\Commands\AutoUnbookmarkCompletedTorrents;
use App\Console\Commands\AutoUpdateUserLastActions;
use App\Console\Commands\AutoUpsertAnnounces;
use App\Console\Commands\AutoUpsertHistories;
use App\Console\Commands\AutoUpsertPeers;
use App\Console\Commands\AutoWarning;
use App\Console\Commands\DeleteUnparticipatedConversations;
use App\Console\Commands\DispatchMetaRefresh;
use App\Console\Commands\EmailBlacklistUpdate;
use App\Console\Commands\SyncDisposableEmailDomains;
use App\Console\Commands\SyncPeers;
use Illuminate\Auth\Console\ClearResetsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Spatie\Backup\Commands\BackupCommand;
use Spatie\Backup\Commands\CleanupCommand;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        if (! config('announce.external_tracker.is_enabled')) {
            $schedule->command(AutoUpsertPeers::class)->everyFiveSeconds()->withoutOverlapping(2);
            $schedule->command(AutoUpsertHistories::class)->everyFiveSeconds()->withoutOverlapping(2);
            $schedule->command(AutoUpsertAnnounces::class)->everyFiveSeconds()->withoutOverlapping(2);
            $schedule->command(AutoCacheUserLeechCounts::class)->everyThirtyMinutes();
            $schedule->command(SyncPeers::class)->everyFiveMinutes();
            $schedule->command(AutoTorrentBalance::class)->hourly();
        }

        $schedule->command(AutoUpdateUserLastActions::class)->everyFiveSeconds();
        $schedule->command(AutoDeleteStoppedPeers::class)->everyTwoMinutes();
        $schedule->command(AutoUnbookmarkCompletedTorrents::class)->everyFifteenMinutes();
        $schedule->command(AutoGroup::class)->daily();
        $schedule->command(AutoNerdStat::class)->hourly();
        $schedule->command(AutoCacheRandomMediaIds::class)->hourly();
        $schedule->command(AutoRewardResurrection::class)->daily();
        $schedule->command(AutoHighspeedTag::class)->hourly();
        $schedule->command(AutoPreWarning::class)->hourly();
        $schedule->command(AutoWarning::class)->daily();
        $schedule->command(AutoDeactivateWarning::class)->hourly();
        $schedule->command(AutoFlushPeers::class)->hourly();
        $schedule->command(AutoBonAllocation::class)->hourly();
        $schedule->command(AutoRemovePersonalFreeleech::class)->hourly();
        $schedule->command(AutoRemoveFeaturedTorrent::class)->hourly();
        $schedule->command(AutoRecycleInvites::class)->daily();
        $schedule->command(AutoRecycleAudits::class)->daily();
        $schedule->command(AutoRecycleFailedLogins::class)->daily();
        $schedule->command(AutoDisableInactiveUsers::class)->daily();
        $schedule->command(AutoSoftDeleteDisabledUsers::class)->daily();
        $schedule->command(AutoRecycleClaimedTorrentRequests::class)->daily();
        $schedule->command(DeleteUnparticipatedConversations::class)->daily();
        $schedule->command(AutoCorrectHistory::class)->daily();
        $schedule->command(EmailBlacklistUpdate::class)->weekends();
        $schedule->command(SyncDisposableEmailDomains::class)->hourly();
        $schedule->command(DispatchMetaRefresh::class, ['--limit' => 5, '--stale-hours' => 720, '--dispatch-ttl-minutes' => 10])->everyMinute()->withoutOverlapping();
        $schedule->command(AutoResetUserFlushes::class)->daily();
        $schedule->command(AutoRemoveTimedTorrentBuffs::class)->hourly();
        $schedule->command(AutoRefundDownload::class)->daily();
        $schedule->command(ClearResetsCommand::class)->daily();
        $schedule->command(AutoSyncTorrentsToMeilisearch::class)->everyFifteenMinutes();
        $schedule->command(AutoSyncPeopleToMeilisearch::class)->daily();
        $schedule->command(AutoRemoveExpiredDonors::class)->daily();
        $schedule->command(AutoRemoveReseeds::class)->daily();
        // withoutOverlapping(10) — auto-release the lock after 10 minutes.
        // Default TTL is 24h; if the 06:00 server-wide backup nukes a running
        // command mid-execution, the leaked lock would silently block the next
        // ~24h of scheduled runs (seen on 2026-05-23).
        // 2026-08-15: 25 cada 5 min = 300/hora, y con 680 pendientes eso son 2h20 de cola
        // para las subidas nuevas. El cuello no es TMDB (responde en ~0.16s) sino el
        // propio tope: el meta-worker estaba al 0% de CPU y la BD al 3%. Subido a 100
        // (1200/hora). Medir un tick antes de subir más; si se acerca a los 10 min de
        // withoutOverlapping, ese es el techo y hay que ampliar la ventana.
        $schedule->command('meta:sync', ['--limit' => 100])->everyFiveMinutes()->withoutOverlapping(10);
        $schedule->command('meta:rotate-covers')->daily()->withoutOverlapping(60);
        // Books and audiobooks: missing-only, so a quiet catalogue costs one
        // cheap query. The limit is deliberately small — Google Books allows
        // 1000 requests a day on the free tier and this must not eat the
        // budget an uploader needs for the upload form's own lookups.
        $schedule->command('books:sync', ['--limit' => 25])->everyFifteenMinutes()->withoutOverlapping(10);
        // Los autores van aparte y mas despacio: OpenLibrary deja de responder
        // si se le llama en bucle, y ademas cada libro son dos peticiones.
        $schedule->command('books:sync-authors', ['--limit' => 15])->hourly()->withoutOverlapping(30);
        // Los juegos no tenían red: `meta:sync` sólo mira pelis y series. IGDB no
        // impone una cuota diaria como Google Books, pero el limite se queda
        // pequeño igual: lo normal es que no haya nada pendiente y la consulta
        // salga barata.
        $schedule->command('games:sync', ['--limit' => 25])->everyFifteenMinutes()->withoutOverlapping(10);
        // Pre-warm the art-proxy cache so users never pay the first-hit fetch+resize.
        // Idempotent (skips cached) + time-budgeted so each run stays short; runs
        // often to keep up with daily cover rotation and newly-added titles.
        $schedule->command('art:warm', ['--max-seconds' => 240])->everyThirtyMinutes()->withoutOverlapping(10)->runInBackground();
        // $schedule->command(AutoBanDisposableUsers::class)->weekends();
        $schedule->command(CleanupCommand::class)->daily();
        $schedule->command(BackupCommand::class, ['--only-db'])->daily();
        $schedule->command(BackupCommand::class, ['--only-files'])->daily();

        // The scheduler and queue workers run as root, so anything they create --
        // most damagingly the daily storage/logs/laravel-*.log -- ends up root-owned
        // and php-fpm's www-data workers can no longer append to it (Monolog fails on
        // chmod with "Operation not permitted"). entrypoint.sh does this same chown at
        // container start; this repeats it while the stack is up. Runs as root, which
        // is exactly what makes the chown possible.
        $schedule->exec('chown -R www-data:www-data storage bootstrap/cache')
            ->everyThirtyMinutes()
            ->withoutOverlapping(10);
    }

    /**
     * Register the Closure based commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
