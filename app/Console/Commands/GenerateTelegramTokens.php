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
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateTelegramTokens extends Command
{
    protected $signature = 'telegram:generate-tokens {--force : Skip confirmation}';
    protected $description = 'Generate telegram_token only for users who are not linked and still need one';

    public function handle(): int
    {
        $users = User::whereNull('telegram_chat_id')
            ->where(function ($query): void {
                $query->whereNull('telegram_token')
                    ->orWhere('telegram_token', '');
            })
            ->get();

        $count = $users->count();

        if ($count === 0) {
            $this->info('✓ All users already have telegram tokens.');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("This will generate tokens for {$count} users. Continue?")) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        $users->each(function (User $user): void {
                $user->update([
                    'telegram_token' => 'TRK-'.Str::random(32),
                ]);
                $this->line("  ✓ User {$user->username} (ID: {$user->id})");
            });

        $this->info("\n✓ Generated tokens for {$count} users");

        return self::SUCCESS;
    }
}
