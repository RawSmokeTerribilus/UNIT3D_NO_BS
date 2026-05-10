<?php

declare(strict_types=1);

use App\Enums\AuthGuard;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-05-20 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('new web users missing two factor are redirected to two factor settings', function (): void {
    $user = User::factory()->create([
        'created_at'        => Carbon::parse('2026-05-10 00:00:00'),
        'two_factor_secret' => null,
        'telegram_chat_id'  => null,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('home.index'));

    $response->assertRedirect(route('users.two_factor_auth.edit', ['user' => $user]));
    $response->assertSessionHasErrors();
});

test('new api users missing security requirements receive a json 403 response', function (): void {
    $user = User::factory()->create([
        'created_at'                => Carbon::parse('2026-05-10 00:00:00'),
        'two_factor_secret'         => 'encrypted-secret',
        'telegram_chat_id'          => 123456789,
        'telegram_group_joined_at'  => null,
    ]);

    $response = $this->actingAs($user, AuthGuard::API->value)->getJson('/api/user');

    $response
        ->assertForbidden()
        ->assertJson([
            'error'   => 'Security Restriction',
            'message' => 'Pendiente activar 2FA o completar la verificación de Telegram en la web del tracker.',
        ]);
});

test('new web users with bot linked but no group membership are redirected to notification settings', function (): void {
    $user = User::factory()->create([
        'created_at'                => Carbon::parse('2026-05-10 00:00:00'),
        'two_factor_secret'         => 'encrypted-secret',
        'telegram_chat_id'          => 123456789,
        'telegram_group_joined_at'  => null,
        'email_verified_at'         => now(),
    ]);

    $response = $this->actingAs($user)->get(route('home.index'));

    $response->assertRedirect(route('users.notification_settings.edit', ['user' => $user]));
    $response->assertSessionHasErrors();
});

test('new web users with two factor and confirmed telegram group access are allowed', function (): void {
    $user = User::factory()->create([
        'created_at'                => Carbon::parse('2026-05-10 00:00:00'),
        'two_factor_secret'         => 'encrypted-secret',
        'telegram_chat_id'          => 123456789,
        'telegram_group_joined_at'  => now(),
        'email_verified_at'         => now(),
    ]);

    $response = $this->actingAs($user)->get(route('home.index'));

    $response->assertOk();
});

test('veteran users remain allowed during the amnesty window', function (): void {
    $user = User::factory()->create([
        'created_at'        => Carbon::parse('2026-05-01 00:00:00'),
        'two_factor_secret' => null,
        'telegram_chat_id'  => null,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('home.index'));

    $response->assertOk();
    $response->assertSessionHas('info');
});

test('notification settings remain reachable while the user is non compliant', function (): void {
    $user = User::factory()->create([
        'created_at'        => Carbon::parse('2026-05-10 00:00:00'),
        'two_factor_secret' => null,
        'telegram_chat_id'  => null,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('users.notification_settings.edit', ['user' => $user]));

    $response->assertOk();
});

test('owner group users are exempt from the zero trust restriction', function (): void {
    $ownerGroup = Group::query()->where('is_owner', '=', true)->sole();
    $user = User::factory()->create([
        'group_id'          => $ownerGroup->id,
        'created_at'        => Carbon::parse('2026-05-10 00:00:00'),
        'two_factor_secret' => null,
        'telegram_chat_id'  => null,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('home.index'));

    $response->assertOk();
});

test('the cunyat account is exempt from the zero trust restriction', function (): void {
    $user = User::factory()->create([
        'username'          => 'CUNYAT',
        'created_at'        => Carbon::parse('2026-05-10 00:00:00'),
        'two_factor_secret' => null,
        'telegram_chat_id'  => null,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('home.index'));

    $response->assertOk();
});
