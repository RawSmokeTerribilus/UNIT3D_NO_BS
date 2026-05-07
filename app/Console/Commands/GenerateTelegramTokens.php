<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateTelegramTokens extends Command
{
    protected $signature = 'telegram:generate-tokens {--force : Skip confirmation}';
    protected $description = 'Generate telegram_token for all users with NULL or empty token';

    public function handle(): int
    {
        $users = User::whereNull('telegram_token')
            ->orWhere('telegram_token', '')
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
