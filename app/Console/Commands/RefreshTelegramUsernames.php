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

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Backfill and periodically refresh the cached Telegram @username for linked
 * users. The webhook keeps handles fresh in real time as users interact; this
 * command is the initial populate plus a safety net for accounts that have gone
 * quiet (handle changed without any bot interaction since).
 */
class RefreshTelegramUsernames extends Command
{
    protected $signature = 'telegram:refresh-usernames
        {--sleep=120 : Milliseconds to wait between API calls (Bot API rate-limit courtesy)}
        {--only-empty : Only fetch users whose telegram_username is currently NULL}';

    protected $description = 'Refresh cached Telegram @username for linked users via live getChatMember';

    public function handle(TelegramService $telegram): int
    {
        $query = User::withTrashed()->whereNotNull('telegram_chat_id');

        if ($this->option('only-empty')) {
            $query->whereNull('telegram_username');
        }

        $users = $query->get();
        $total = $users->count();

        if ($total === 0) {
            $this->info('✓ No linked users to refresh.');

            return self::SUCCESS;
        }

        $this->info("Refreshing Telegram handles for {$total} linked users…");

        $sleepUs  = max(0, (int) $this->option('sleep')) * 1000;
        $updated  = 0;
        $cleared  = 0;
        $failed   = 0;

        foreach ($users as $user) {
            $profile = $telegram->getGroupMemberProfile((string) $user->telegram_chat_id);

            if ($profile === null) {
                $failed++;
                $this->line("  ? {$user->username} — sin respuesta de Telegram");

                if ($sleepUs > 0) {
                    usleep($sleepUs);
                }

                continue;
            }

            $handle = $profile['username'] ?? null;

            if ((string) $user->telegram_username !== (string) $handle) {
                $user->forceFill(['telegram_username' => $handle])->saveQuietly();

                if ($handle === null) {
                    $cleared++;
                    $this->line("  - {$user->username} — handle eliminado");
                } else {
                    $updated++;
                    $this->line("  ✓ {$user->username} — @{$handle}");
                }
            }

            if ($sleepUs > 0) {
                usleep($sleepUs);
            }
        }

        $this->info("\n✓ Hecho. Actualizados: {$updated}, vaciados: {$cleared}, sin respuesta: {$failed}");

        return self::SUCCESS;
    }
}
