# UNIT3D Telegram Integration — Complete Implementation Guide

> **Community contribution**: Full Telegram integration for UNIT3D trackers.  
> Deep-link handshake, torrent notifications, ban-to-kick, group invite links.  
> Tested on RHEL 9 / Docker (PHP 8.4) / Laravel 11.x.

---

## Features

- **Deep-link handshake** with `TRK-` prefixed tokens to link tracker accounts ↔ Telegram
- **Torrent notifications** with poster images, mediainfo parsing, and language flag emojis
- **Ban → Kick**: automatically expels banned users from the Telegram group
- **Group invite link** with inline keyboard button after handshake and on `/status`
- **Webhook API** handling 3 bot commands: `/start`, `/status`, `/help`
- **Cyberpunk-styled UI** in the notification settings panel
- **Production deploy script** (v4.0.0) with 11 validation phases

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│ TELEGRAM BOT (@your_bot)                                        │
│  Webhook → POST /api/telegram/webhook                           │
│  Commands: /start TRK-xxx, /status, /help                      │
│  Inline keyboard: "📡 JOIN GROUP" (invite link)                  │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ WEBHOOK CONTROLLER (TelegramWebhookController.php)              │
│  • No auth middleware (withoutMiddleware)                        │
│  • Deep-link: validates TRK- prefix, pessimistic lock, dedup   │
│  • Sends invite link via sendMessageWithButton (JSON POST)      │
└────────────────────────┬────────────────────────────────────────┘
                         │
          ┌──────────────┼──────────────┐
          ↓              ↓              ↓
┌──────────────┐ ┌──────────────┐ ┌──────────────────────┐
│ HANDSHAKE    │ │ NOTIFICATIONS│ │ BAN → KICK           │
│              │ │              │ │                      │
│ User model:  │ │ Job queued   │ │ BanController calls  │
│ telegram_    │ │ via Redis    │ │ TelegramService::    │
│ chat_id +    │ │ Worker proc  │ │ kickUser()           │
│ token=null   │ │ in container │ │ → banChatMember API  │
└──────────────┘ └──────────────┘ │ → clears chat_id     │
                                  └──────────────────────┘
```

---

## Prerequisites

1. **Telegram Bot** created via [@BotFather](https://t.me/BotFather)
2. **Telegram Supergroup** with the bot added as admin (needs `Ban users` + `Invite users via link` permissions)
3. **UNIT3D** running on Docker with Redis queue backend
4. **HTTPS domain** for webhook (Telegram requires SSL)

---

## Environment Variables

Add to your `.env`:

```env
# Telegram Integration
TELEGRAM_BOT_TOKEN=123456789:ABCDefGHIjklMNOpqrsTuvWXyz_1234567890
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_GROUP_ID="-100XXXXXXXXXX"
TELEGRAM_TOPIC_NOVEDADES="6"
TELEGRAM_TOPIC_NOTICIAS="10"
TELEGRAM_TOPIC_OFFTOPIC="1"
TELEGRAM_GROUP_INVITE_LINK="https://t.me/+XXXXXXXXXXXXXX"
```

### How to get these values

| Variable | How to get it |
|----------|---------------|
| `BOT_TOKEN` | [@BotFather](https://t.me/BotFather) → `/newbot` or `/token` |
| `BOT_USERNAME` | The bot's `@username` without the `@` |
| `GROUP_ID` | Add [@raw_data_bot](https://t.me/raw_data_bot) to your group, send a message, it'll show the chat ID (starts with `-100`) |
| `TOPIC_*` | In a forum-enabled supergroup, right-click a topic → Copy Link → the number after the last `/` |
| `GROUP_INVITE_LINK` | Bot can generate it: `curl "https://api.telegram.org/bot<TOKEN>/createChatInviteLink" -d "chat_id=<GROUP_ID>" -d "name=Auto-Link"` |

---

## Configuration

### `config/services.php`

Add the `telegram` key to your services config:

```php
'telegram' => [
    'token'             => env('TELEGRAM_BOT_TOKEN'),
    'chat_id'           => env('TELEGRAM_GROUP_ID'),
    'topic_id'          => env('TELEGRAM_TOPIC_NOVEDADES'),
    'bot_username'      => env('TELEGRAM_BOT_USERNAME'),
    'group_invite_link' => env('TELEGRAM_GROUP_INVITE_LINK'),
],
```

> **⚠️ Critical**: Use `token` and `chat_id` as key names. If you use `bot_token` or `group_id`, the service layer will silently fail (nulls).

---

## Database Migration

Create a migration to add Telegram fields to the `users` table:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('telegram_token', 36)->nullable()->unique();
    $table->bigInteger('telegram_chat_id')->unsigned()->nullable()->unique();
});
```

Run:
```bash
docker compose exec -T app php artisan migrate
```

---

## Files to Create

### 1. Webhook Controller

**`app/Http/Controllers/API/TelegramWebhookController.php`** (~180 lines)

Handles incoming bot messages. Key methods:

| Method | Purpose |
|--------|---------|
| `handle()` | Main router — dispatches to /start, /status, /help |
| `handleStart()` | Deep-link handshake with `TRK-` token validation, `lockForUpdate()` transaction, duplicate chat check, invite link button |
| `handleStatus()` | Shows link status + "JOIN GROUP" button for linked users |
| `handleHelp()` | Lists available commands |
| `sendMessage()` | Simple Telegram API send |
| `sendMessageWithButton()` | Send with inline keyboard. **Must use `Http::asJson()`** to preserve `+` in invite URLs |

```php
// Key pattern: handleStart deep-link
private function handleStart(int|string $chatId, string $text): void
{
    $token = trim(str_replace('/start', '', $text));

    // Validate TRK- prefix format
    if (!preg_match('/^TRK-[a-zA-Z0-9]+$/', $token)) {
        $this->sendMessage($chatId, "❌ Invalid token.");
        return;
    }

    // Check if chat is already linked to another account
    $existingUser = User::where('telegram_chat_id', $chatId)->first();
    if ($existingUser) {
        $this->sendMessage($chatId, "⚠️ Already linked to {$existingUser->username}.");
        return;
    }

    // Transactional linking with pessimistic lock
    DB::transaction(function () use ($chatId, $token) {
        $user = User::where('telegram_token', $token)
            ->lockForUpdate()
            ->first();

        if (!$user) {
            $this->sendMessage($chatId, "❌ Token not found or already used.");
            return;
        }

        $user->telegram_chat_id = $chatId;
        $user->telegram_token = null; // One-time use
        $user->save();

        $inviteLink = config('services.telegram.group_invite_link');
        $text = "✅ <b>Handshake Successful, {$user->username}!</b>";

        if ($inviteLink) {
            $this->sendMessageWithButton($chatId, $text, "📡 JOIN GROUP", $inviteLink);
        } else {
            $this->sendMessage($chatId, $text, 'HTML');
        }
    });
}
```

```php
// Key pattern: inline keyboard with JSON POST (preserves + in URLs)
private function sendMessageWithButton(int|string $chatId, string $text, string $buttonText, string $url): void
{
    $botToken = config('services.telegram.token');

    Http::timeout(10)->asJson()->post(
        "https://api.telegram.org/bot{$botToken}/sendMessage",
        [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => $buttonText, 'url' => $url]],
                ],
            ],
        ]
    );
}
```

---

### 2. Telegram Service

**`app/Services/TelegramService.php`** (~95 lines)

```php
class TelegramService
{
    public function sendAnnouncement(string $text, ?string $photoUrl = null, ?string $threadId = null): bool
    {
        $token  = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');

        $method  = $photoUrl ? 'sendPhoto' : 'sendMessage';
        $payload = [
            'chat_id'           => $chatId,
            'message_thread_id' => $threadId,
            'parse_mode'        => 'HTML',
        ];

        if ($photoUrl) {
            $payload['photo']   = $photoUrl;
            $payload['caption'] = $text;
        } else {
            $payload['text'] = $text;
        }

        $response = Http::post("https://api.telegram.org/bot{$token}/{$method}", $payload);
        return $response->successful();
    }

    public function sendMessage(string $text, ?string $chatId = null, ?string $threadId = null): bool
    {
        $token  = config('services.telegram.token');
        $chatId = $chatId ?? config('services.telegram.chat_id');

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'                  => $chatId,
            'message_thread_id'        => $threadId,
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => false,
        ]);

        return $response->successful();
    }

    public function kickUser(string $telegramChatId): bool
    {
        $token  = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');

        $response = Http::post("https://api.telegram.org/bot{$token}/banChatMember", [
            'chat_id'         => $chatId,
            'user_id'         => $telegramChatId,
            'revoke_messages' => true,
        ]);

        return $response->successful();
    }
}
```

---

### 3. Torrent Notification Job

**`app/Jobs/SendTelegramNotification.php`** (~306 lines)

Key design decisions:
- **Retry policy**: 3 attempts with backoff `[10, 60, 300]` seconds
- **Timeout**: 30 seconds per attempt
- **Poster support**: Uses `sendPhoto` with caption when torrent has an image
- **Mediainfo parsing**: Extracts resolution, video/audio codecs, languages
- **Language flags**: `languageToFlag()` maps "Spanish" → 🇪🇸, "English" → 🇬🇧, etc.

Dispatched from `TorrentObserver` when a torrent is approved:

```php
// In TorrentObserver
if ($torrent->status === TorrentStatus::APPROVED) {
    SendTelegramNotification::dispatch($torrent);
}
```

---

### 4. Token Controller

**`app/Http/Controllers/User/TelegramController.php`** (~24 lines)

```php
class TelegramController extends Controller
{
    public function resetToken(Request $request)
    {
        $user = $request->user();

        $user->update([
            'telegram_token' => 'TRK-' . Str::random(32),
        ]);

        return redirect()
            ->route('users.notification_settings.edit', ['user' => $user->username])
            ->with('success', 'Telegram token regenerated.');
    }
}
```

---

### 5. Token Generator Command

**`app/Console/Commands/GenerateTelegramTokens.php`** (~44 lines)

```bash
php artisan telegram:generate-tokens         # Only users without tokens
php artisan telegram:generate-tokens --force  # Regenerate all
```

Generates `TRK-` prefixed tokens for bulk provisioning.

---

### 6. Ban → Kick Integration

Add to **`app/Http/Controllers/Staff/BanController.php`**, inside `store()` after the user update:

```php
// Telegram kick: expel from group if user was linked
if ($user->telegram_chat_id) {
    try {
        (new TelegramService())->kickUser((string) $user->telegram_chat_id);
    } catch (\Throwable $e) {
        Log::warning('Telegram kick failed for user '.$user->username, ['error' => $e->getMessage()]);
    }

    $user->update(['telegram_chat_id' => null, 'telegram_token' => null]);
}
```

**Design notes**:
- `banChatMember` API with `revoke_messages: true` removes the user and their messages
- Clears both `telegram_chat_id` and `telegram_token` so the banned user cannot re-link
- Try/catch ensures a Telegram API failure doesn't block the tracker ban

---

## Routes

### Web Route (Token Reset)

In `routes/web.php`, inside the authenticated user prefix group:

```php
// Telegram
Route::prefix('telegram')->name('telegram.')->group(function (): void {
    Route::post('/reset-token', [TelegramController::class, 'resetToken'])
        ->name('reset_token');
});
```

### API Route (Webhook)

In `routes/api.php`:

```php
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->withoutMiddleware(['throttle:api', 'auth:api', 'banned']);
```

> **⚠️ Critical**: The webhook route **must** exclude auth and throttle middleware. Telegram sends webhooks without authentication, so `$request->user()` is `null`. If your `RouteServiceProvider` rate limiter calls `$request->user()->id`, it will crash with a 500 error.

---

## Webhook Setup

Register your webhook URL with Telegram:

```bash
BOT_TOKEN="your_bot_token_here"

# Set webhook
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -d "url=https://your-domain.com/api/telegram/webhook" \
  -d "drop_pending_updates=true"

# Verify
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo" | python3 -m json.tool
```

The response should show `"pending_update_count": 0` and `"last_error_message": ""`.

---

## UI Integration

Include the Telegram settings partial in your notification settings view:

```blade
@include('partials.telegram_settings')
```

The partial should handle two states:

**Linked** (user has `telegram_chat_id`):
- Green badge showing linked username
- Unlink/reset button with `confirm()` dialog

**Unlinked** (user has `telegram_token`):
- Token display with copy-to-clipboard button
- Deep-link button: `https://t.me/{bot_username}?start={token}`
- Reset button to regenerate token

---

## User Flows

### Linking (Handshake)

```
User → Notification Settings panel
    ↓
Sees token TRK-XXXXXXX and "🚀 LINK WITH BOT" button
    ↓
Click → opens t.me/your_bot?start=TRK-XXXXXXX
    ↓
Bot receives /start TRK-XXXXXXX
    ↓
Webhook validates TRK- format, finds user with lockForUpdate()
    ↓
Saves telegram_chat_id, clears token (one-time use)
    ↓
Responds: "✅ Handshake Successful!" + button [📡 JOIN GROUP]
    ↓
User clicks button → joins the Telegram group
```

### Ban → Kick

```
Staff bans user from admin panel
    ↓
BanController::store() changes group_id to "banned"
    ↓
Checks: does user have telegram_chat_id?
    ↓ YES
TelegramService::kickUser() → banChatMember API
    ↓
Clears telegram_chat_id + telegram_token
    ↓
User expelled from group, cannot re-link
```

### Token Reset

```
User → Notification Settings → "Reset Token"
    ↓
confirm() dialog in JavaScript
    ↓
POST /users/{username}/telegram/reset-token
    ↓
Generates new TRK-XXXXXXX, clears telegram_chat_id
    ↓
Redirect with success message
```

---

## Docker Notes

| Container | Role |
|-----------|------|
| `app` | Laravel app (PHP-FPM) |
| `worker` | Queue worker (`php artisan queue:work` as PID 1) |
| `web` | Nginx reverse proxy |
| `db` | MySQL |
| `redis` | Queue backend + cache |

**Important**: The worker container bind-mounts the same code as app but caches classes in memory. **Always restart the worker** after code changes:

```bash
docker compose restart worker
```

If your host PHP version doesn't match the container (e.g., host has PHP 8.0, container has 8.4), always run artisan through Docker:

```bash
docker compose exec -T app php artisan <command>
```

---

## Security

| Check | Detail |
|-------|--------|
| ✅ CSRF | All forms use `@csrf` |
| ✅ Secure tokens | `TRK-` + `Str::random(32)` = 36 chars, cryptographically random |
| ✅ Webhook isolation | Excludes `throttle:api`, `auth:api`, `banned` middleware |
| ✅ Pessimistic locking | `lockForUpdate()` in handshake prevents race conditions |
| ✅ One chat per account | Duplicate `telegram_chat_id` check before linking |
| ✅ Confirmation dialogs | `confirm()` on all destructive actions |
| ✅ Ban cleanup | Clears `chat_id` + `token` on ban (no re-linking possible) |
| ✅ JSON POST for keyboards | `Http::asJson()` preserves `+` in invite link URLs |

---

## Common Pitfalls & Fixes

### 1. Token Format Mismatch (Handshake Impossible)

**Symptom**: Bot says "Token not found" even though it exists in the DB.  
**Cause**: Token generators created `Str::random(32)` without `TRK-` prefix, but webhook validates `TRK-` format.  
**Fix**: Ensure ALL token generators use `'TRK-' . Str::random(32)`. Update existing DB tokens:

```sql
UPDATE users
SET telegram_token = CONCAT('TRK-', telegram_token)
WHERE telegram_token IS NOT NULL
  AND telegram_token NOT LIKE 'TRK-%';
```

### 2. Webhook Returns 500

**Symptom**: Telegram webhook gets 500 errors, `getWebhookInfo` shows `last_error_message`.  
**Cause**: API rate limiter in `RouteServiceProvider` calls `$request->user()->id` — webhook has no authenticated user → null crash.  
**Fix**: Add `->withoutMiddleware(['throttle:api', 'auth:api', 'banned'])` to the webhook route.

### 3. Config Key Mismatch (Silent Failures)

**Symptom**: `TelegramService` methods return `false`, no errors in logs.  
**Cause**: Code uses `config('services.telegram.bot_token')` but config defines the key as `token`.  
**Fix**: Ensure service code uses exactly `config('services.telegram.token')` and `config('services.telegram.chat_id')`.

### 4. kickUser() Never Called (Orphaned Method)

**Symptom**: Banning a user changes their tracker group but doesn't remove them from Telegram.  
**Cause**: `kickUser()` exists in `TelegramService` but is never called from `BanController`.  
**Fix**: Wire it into `BanController::store()` with a try/catch (see section 6 above).

### 5. Invite Link Not Working (URL Encoding)

**Symptom**: Inline keyboard button shows broken URL with `%20` instead of `+`.  
**Cause**: `Http::post()` with form encoding converts `+` to space.  
**Fix**: Use `Http::asJson()->post()` for messages with inline keyboards.

### 6. Stale Pending Updates

**Symptom**: Webhook gets flooded with old messages causing repeated errors.  
**Fix**: Reset with `drop_pending_updates`:

```bash
curl "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -d "url=https://your-domain.com/api/telegram/webhook" \
  -d "drop_pending_updates=true"
```

---

## Troubleshooting

### Webhook not responding

```bash
# Verify route is registered
docker compose exec -T app php artisan route:list | grep telegram

# Direct test
curl -s -X POST "https://your-domain.com/api/telegram/webhook" \
  -H "Content-Type: application/json" \
  -d '{"message":{"chat":{"id":12345},"from":{"first_name":"Test"},"text":"/help"}}'
```

### Bot not sending messages

```bash
# Verify config is loaded
docker compose exec -T app php artisan tinker \
  --execute="echo config('services.telegram.token');"

# Direct API test
curl -s "https://api.telegram.org/bot<TOKEN>/getMe"
```

### Worker not processing jobs

```bash
docker compose ps worker              # Is it Up?
docker compose logs worker | tail -20  # Errors?
docker compose restart worker          # Restart
```

### Ban doesn't kick from Telegram

```bash
# Check user had telegram_chat_id before ban
docker compose exec -T app php artisan tinker \
  --execute="\$u = App\Models\User::find(ID); echo \$u->telegram_chat_id;"

# Check logs
docker compose logs app | grep "Telegram kick"
```

---

## Production Deploy Script

The deploy script (`production-deploy-telegram.sh`) runs 11 phases:

| Phase | Purpose |
|-------|---------|
| 0 | Validate `.env` (all Telegram vars including `INVITE_LINK`) |
| 1 | Extract env variables with safe handling for special-character passwords |
| 2 | Database backup |
| 3 | Validate Telegram Bot API connectivity (`getMe`) |
| 4 | Apply database migrations |
| 5 | Verify all files exist + code validations (see below) |
| 6 | Restart worker + clear caches |
| 7 | Verify routes (`reset_token` + webhook) |
| 8 | End-to-end tests (TelegramService, observer, queue, config) |
| 9 | Verify UI injection in notification settings |
| 10 | Generate `TRK-` tokens for all users |
| 11 | Generate deployment report |

### Phase 5 Code Validations

- ✅ `TelegramService.php` and `BanController.php` exist
- ✅ TelegramService doesn't use deprecated keys (`bot_token` / `group_id`)
- ✅ BanController has `kickUser()` integrated
- ✅ WebhookController has `sendMessageWithButton`
- ✅ `config/services.php` has `group_invite_link`
- ✅ PHP lint on all critical files

---

## Files Summary

| File | Type | Lines | Purpose |
|------|------|-------|---------|
| `app/Http/Controllers/API/TelegramWebhookController.php` | PHP | ~180 | Bot webhook handler |
| `app/Jobs/SendTelegramNotification.php` | PHP | ~306 | Torrent announcement job |
| `app/Services/TelegramService.php` | PHP | ~95 | Telegram API wrapper |
| `app/Http/Controllers/User/TelegramController.php` | PHP | ~24 | Token reset |
| `app/Http/Controllers/Staff/BanController.php` | PHP | ~84 | Modified: kick integration |
| `app/Console/Commands/GenerateTelegramTokens.php` | PHP | ~44 | Bulk token provisioning |
| `app/Observers/TorrentObserver.php` | PHP | — | Modified: dispatch notification job |
| `resources/views/partials/telegram_settings.blade.php` | Blade | ~337 | UI partial |
| `resources/views/user/notification-setting/edit.blade.php` | Blade | — | Modified: include partial |
| `config/services.php` | PHP | — | Modified: +telegram section |
| `routes/api.php` | PHP | — | Modified: webhook route |
| `routes/web.php` | PHP | — | Modified: reset token route |
| `production-deploy-telegram.sh` | Bash | ~613 | Deploy script v4.0.0 |
| `database/migrations/*_add_telegram_fields_to_users_table.php` | PHP | — | Migration |

---

## License

This integration guide is provided as a community contribution for UNIT3D-based trackers.  
UNIT3D is licensed under the [GNU AGPL v3.0](https://www.gnu.org/licenses/agpl-3.0.en.html).
