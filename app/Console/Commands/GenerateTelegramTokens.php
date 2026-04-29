<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateTelegramTokens extends Command
{
    protected $signature = 'telegram:generate-tokens {--force : Skip confirmation}';
    protected $description = 'Generate telegram_token for all users with NULL or empty token';

    public function handle()
    {
        $count = User::whereNull('telegram_token')
            ->orWhere('telegram_token', '')
            ->count();

        if ($count === 0) {
            $this->info('✓ All users already have telegram tokens.');
            return;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("This will generate tokens for {$count} users. Continue?")) {
                $this->info('Cancelled.');
                return;
            }
        }

        $updated = User::whereNull('telegram_token')
            ->orWhere('telegram_token', '')
            ->each(function (User $user) {
                $user->update([
                    'telegram_token' => 'TRK-' . Str::random(32),
                ]);
                $this->line("  ✓ User {$user->username} (ID: {$user->id})");
            })
            ->count();

        $this->info("\n✓ Generated tokens for {$updated} users");
    }
}
