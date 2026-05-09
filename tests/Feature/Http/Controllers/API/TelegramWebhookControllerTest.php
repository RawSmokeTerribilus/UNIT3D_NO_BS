<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('webhook ignores commands from non private chats', function (): void {
    Http::fake();

    $response = $this->postJson(route('api.telegram.webhook'), [
        'message' => [
            'text' => '/start@NobsBot =TRK-abc123',
            'chat' => [
                'id' => '-1001234567890',
                'type' => 'supergroup',
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    Http::assertNothingSent();
});

test('webhook accepts windows style start payloads in private chat', function (): void {
    config()->set('services.telegram.token', 'test-token');
    config()->set('services.telegram.group_invite_link', null);

    Http::fake();

    $token = 'TRK-testWindowsStartPayload123456789';

    $user = User::factory()->create([
        'telegram_chat_id' => null,
        'telegram_token' => $token,
    ]);

    $response = $this->postJson(route('api.telegram.webhook'), [
        'message' => [
            'text' => "/start={$token}",
            'chat' => [
                'id' => 8576519633,
                'type' => 'private',
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $user->refresh();

    expect($user->telegram_chat_id)->toBe(8576519633)
        ->and($user->telegram_token)->toBeNull();

    Http::assertSentCount(1);
});

test('webhook throttles repeated invalid token replies in private chat', function (): void {
    config()->set('services.telegram.token', 'test-token');

    Http::fake();

    $payload = [
        'message' => [
            'text' => '/start nope',
            'chat' => [
                'id' => 424242,
                'type' => 'private',
            ],
        ],
    ];

    $this->postJson(route('api.telegram.webhook'), $payload)->assertOk();
    $this->postJson(route('api.telegram.webhook'), $payload)->assertOk();

    Http::assertSentCount(1);
});

test('webhook refuses to relink an account already linked to another chat', function (): void {
    config()->set('services.telegram.token', 'test-token');

    Http::fake();

    $token = 'TRK-staleTokenAlreadyLinked123456789';

    $user = User::factory()->create([
        'telegram_chat_id' => 111111,
        'telegram_token' => $token,
    ]);

    $response = $this->postJson(route('api.telegram.webhook'), [
        'message' => [
            'text' => "/start {$token}",
            'chat' => [
                'id' => 222222,
                'type' => 'private',
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $user->refresh();

    expect($user->telegram_chat_id)->toBe(111111)
        ->and($user->telegram_token)->toBeNull();

    Http::assertSentCount(1);
});
