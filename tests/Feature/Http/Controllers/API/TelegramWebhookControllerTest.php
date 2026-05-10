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

test('webhook links the bot but keeps group membership pending until telegram confirms it', function (): void {
    config()->set('services.telegram.token', 'test-token');
    config()->set('services.telegram.chat_id', '-100987654321');
    config()->set('services.telegram.group_invite_link', 'https://t.me/joinchat/example');

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), '/getChatMember')) {
            return Http::response([
                'ok' => true,
                'result' => [
                    'status' => 'left',
                ],
            ], 200);
        }

        return Http::response(['ok' => true, 'result' => true], 200);
    });

    $token = 'TRK-prodMembershipPending123456789';

    $user = User::factory()->create([
        'telegram_chat_id' => null,
        'telegram_token' => $token,
        'telegram_group_joined_at' => null,
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
        ->and($user->telegram_token)->toBeNull()
        ->and($user->telegram_group_joined_at)->toBeNull();
});

test('webhook membership updates mark linked users as joined', function (): void {
    config()->set('services.telegram.chat_id', '-100987654321');

    Http::fake();

    $user = User::factory()->create([
        'telegram_chat_id' => 8576519633,
        'telegram_group_joined_at' => null,
    ]);

    $response = $this->postJson(route('api.telegram.webhook'), [
        'chat_member' => [
            'chat' => [
                'id' => '-100987654321',
                'type' => 'supergroup',
            ],
            'new_chat_member' => [
                'status' => 'member',
                'user' => [
                    'id' => 8576519633,
                ],
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $user->refresh();

    expect($user->telegram_group_joined_at)->not->toBeNull();
});
