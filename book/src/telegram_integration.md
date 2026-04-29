# Telegram Integration

This page documents the full Telegram bot integration for UNIT3D trackers: deep-link account linking, queued torrent notifications, automatic kick on ban, and group invite flow.

> [!IMPORTANT]
> The webhook endpoint requires an HTTPS domain. Telegram does not deliver webhooks to plain HTTP URLs.

## 1. Prerequisites

- A Telegram bot created via [@BotFather](https://t.me/BotFather).
- A Telegram **supergroup** (not a regular group) with the bot added as an administrator. The bot must have **Ban users** and **Invite users via link** permissions.
- Your UNIT3D instance must be accessible over HTTPS.
- Redis must be configured as the Laravel queue backend (the notification job is queued, not synchronous).

## 2. Environment Variables

Add the following variables to your `.env` file:

```env
TELEGRAM_BOT_TOKEN=123456789:ABCDefGHIjklMNOpqrsTuvWXyz_1234567890
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_GROUP_ID=-100XXXXXXXXXX
TELEGRAM_TOPIC_NOVEDADES=6
TELEGRAM_GROUP_INVITE_LINK=https://t.me/+XXXXXXXXXXXXXX
```

| Variable | How to obtain |
|----------|---------------|
| `TELEGRAM_BOT_TOKEN` | [@BotFather](https://t.me/BotFather) → `/newbot` or `/token` |
| `TELEGRAM_BOT_USERNAME` | The bot's `@username` without the `@` |
| `TELEGRAM_GROUP_ID` | Add [@raw_data_bot](https://t.me/raw_data_bot) to your group, send any message; the chat ID is shown (starts with `-100`) |
| `TELEGRAM_TOPIC_NOVEDADES` | In a forum-enabled supergroup, right-click the announcements topic → Copy Link → the number after the last `/` |
| `TELEGRAM_GROUP_INVITE_LINK` | Generate via API: `curl "https://api.telegram.org/bot<TOKEN>/createChatInviteLink" -d "chat_id=<GROUP_ID>"` |

## 3. Configuration

The `config/services.php` file maps these variables to the `telegram` service key:

```php
'telegram' => [
    'token'             => env('TELEGRAM_BOT_TOKEN'),
    'chat_id'           => env('TELEGRAM_GROUP_ID'),
    'topic_id'          => env('TELEGRAM_TOPIC_NOVEDADES'),
    'bot_username'      => env('TELEGRAM_BOT_USERNAME'),
    'group_invite_link' => env('TELEGRAM_GROUP_INVITE_LINK'),
],
```

All application code reads these values via `config('services.telegram.*')`.

## 4. Database Migration

Run the migration to add the required columns to the `users` table:

```sh
php artisan migrate
```

This adds:

| Column | Type | Constraints |
|--------|------|-------------|
| `telegram_chat_id` | `BIGINT` | nullable, unique |
| `telegram_token` | `VARCHAR(64)` | nullable, unique |

## 5. Webhook Setup

Register the webhook with Telegram so updates are delivered to your application:

```sh
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://your-domain.tld/api/telegram/webhook"}'
```

Verify the webhook is active:

```sh
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo"
```

The response should show `"url"` set to your endpoint and `"pending_update_count": 0`.

### Automatic Registration (Post-Deploy)

Instead of manual `curl`, use the Artisan command:

```sh
php artisan telegram:set-webhook
```

This validates configuration, handles `setWebhook` API calls, and provides error feedback. Use `--test` to check current webhook status without modifying. **Run this once after deploy or when `.env` changes.**

> [!IMPORTANT]
> The webhook route at `POST /api/telegram/webhook` intentionally excludes the `throttle:api`, `auth:api`, and `banned` middleware. Telegram's servers must be able to reach it without authentication.

## 6. User Flow: Linking an Account

Linking works via a deep-link handshake using a `TRK-` prefixed token:

1. The user navigates to **Notification Settings** in the tracker UI.
2. A unique token of the form `TRK-` + 32 random alphanumeric characters (36 characters total) is displayed alongside a **Link with Bot** button.
3. Clicking the button opens a `t.me/<bot_username>?start=TRK-xxxxx` deep link in Telegram.
4. The bot receives a `/start TRK-xxxxx` message.
5. The webhook controller looks up the token in the database using a pessimistic lock (`lockForUpdate()`) inside a transaction to prevent race conditions.
6. On success, `telegram_chat_id` is set to the user's Telegram chat ID, and `telegram_token` is cleared to `null` (one-time use).
7. The bot sends a confirmation message. If `TELEGRAM_GROUP_INVITE_LINK` is configured, an inline keyboard button to join the group is included.

> [!IMPORTANT]
> Each token is single-use. Once linked, the token is cleared. Users must reset their token to re-link.

## 7. User Flow: Bot Commands

The bot handles three commands after the webhook is active:

| Command | Behaviour |
|---------|-----------|
| `/start` | Without a token: shows a welcome message. With `TRK-` token: performs the account linking handshake. |
| `/status` | Replies with link status. If linked, shows the username and optionally an inline group invite button. |
| `/help` | Lists the available commands. |

## 8. Admin Flow: Ban → Kick

When a staff member bans a user via the `BanController`, the following happens automatically if the user has a linked Telegram account:

1. `TelegramService::kickUser()` is called with the user's `telegram_chat_id`.
2. The service calls the Telegram API `banChatMember` (with `revoke_messages: true`).
3. Immediately after a successful ban, it calls `unbanChatMember` (with `only_if_banned: true`).
4. This produces a **clean kick**: the user is removed from the group but is not permanently banned and can rejoin via a new invite link.
5. Both `telegram_chat_id` and `telegram_token` are then cleared to `null` on the user record.

## 9. Torrent Notifications

When a torrent's moderation status transitions to `APPROVED`, `TorrentObserver` dispatches a `SendTelegramNotification` job:

```php
SendTelegramNotification::dispatch($torrent, $torrent->user);
```

The job has the following retry configuration:

| Property | Value |
|----------|-------|
| `$tries` | `3` |
| `$backoff` | `[10, 60, 300]` seconds |
| `$timeout` | `30` seconds |

The notification is sent as a `sendPhoto` API call to the configured group and topic, with an inline keyboard containing Download, IMDb, TMDb, and Trailer buttons. Poster URL is resolved from the linked movie or TV record. Trailer URL supports both a raw YouTube ID and `[youtube]ID[/youtube]` tags in the torrent description.

The queue worker processes these jobs. The worker runs in the `worker` container.

## 10. Token Management

### Reset via UI

Users can reset their Telegram link from the Notification Settings page. Resetting:

1. Clears `telegram_chat_id` from the user record.
2. Generates a new `TRK-` token.
3. If the user was previously linked, sends a "disconnected" message to their Telegram account and kicks them from the group.

### Bulk token provisioning

To generate tokens for all users who do not yet have one:

```sh
docker compose exec app php artisan telegram:generate-tokens
```

Add `--force` to skip the confirmation prompt:

```sh
docker compose exec app php artisan telegram:generate-tokens --force
```

## 11. Docker Notes

> [!IMPORTANT]
> After any code change to jobs, services, or controllers related to Telegram, restart the queue worker so it picks up the updated class definitions:

```sh
docker compose restart worker
```

Failure to restart the worker means the running process continues to execute the old code from memory.

## 12. Troubleshooting

### Webhook returns 500

The webhook route excludes authentication middleware deliberately. If you receive 500 errors, check that no upstream middleware (e.g., a reverse proxy WAF rule) is rejecting the request before it reaches Laravel. Verify with:

```sh
docker compose logs worker | tail -20
```

### Bot commands return no response

Check that the bot token and chat ID are loaded correctly:

```sh
docker compose exec app php artisan route:list | grep telegram
```

Confirm the route exists, then verify the `.env` values are not empty:

```sh
docker compose exec app php artisan tinker --execute="dd(config('services.telegram'));"
```

### Notifications are silent (no Telegram message on approval)

1. Confirm the queue worker is running: `docker compose ps worker`.
2. Check for failed jobs: `docker compose exec app php artisan queue:failed`.
3. Confirm `TELEGRAM_BOT_TOKEN` and `TELEGRAM_GROUP_ID` are set and non-empty.
4. Check application logs: `docker compose logs app | grep -i telegram`.

### Token already used / not found

Each `TRK-` token is single-use and is cleared on successful linking. If a user sees "token not found", they must reset their token from the Notification Settings page to generate a new one.
