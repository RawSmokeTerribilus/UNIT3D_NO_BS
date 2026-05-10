<?php

declare(strict_types=1);

use App\Models\User;

/**
 * @see App\Console\Commands\GenerateTelegramTokens
 */
it('only generates telegram tokens for users who are not linked', function (): void {
    $linkedUser = User::factory()->create([
        'telegram_chat_id' => 123456789,
        'telegram_token'   => null,
    ]);

    $unlinkedUser = User::factory()->create([
        'telegram_chat_id' => null,
        'telegram_token'   => null,
    ]);

    $this->artisan('telegram:generate-tokens', [
        '--force' => true,
    ])
        ->expectsOutputToContain('Generated tokens for 1 users')
        ->assertExitCode(0)
        ->run();

    expect($linkedUser->fresh()->telegram_token)->toBeNull()
        ->and($unlinkedUser->fresh()->telegram_token)->not->toBeNull();
});
