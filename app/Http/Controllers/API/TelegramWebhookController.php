<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    private function telegramInstanceLabel(): string
    {
        return (string) config('services.telegram.instance_label', config('app.name', 'tracker'));
    }

    private function replyCooldownSeconds(): int
    {
        return max(5, (int) config('services.telegram.reply_cooldown_seconds', 120));
    }

    private function isPrivateChat(array $message): bool
    {
        return ($message['chat']['type'] ?? null) === 'private';
    }

    private function isCommand(string $text, string $command): bool
    {
        return preg_match(sprintf('/^\/%s(?:@\w+)?$/u', preg_quote($command, '/')), $text) === 1;
    }

    private function extractStartToken(string $text): ?string
    {
        if (preg_match('/^\/start(?:@\w+)?(?:(?:\s+|=)(.*))?$/u', trim($text), $matches) !== 1) {
            return null;
        }

        return ltrim(trim($matches[1] ?? ''), '=');
    }

    private function canSendReply(int|string $chatId, string $kind): bool
    {
        return Cache::add(
            sprintf('telegram:webhook:reply:%s:%s', $kind, (string) $chatId),
            true,
            now()->addSeconds($this->replyCooldownSeconds()),
        );
    }

    private function sendMessageOnce(int|string $chatId, string $kind, string $text, ?string $parseMode = null, ?int $threadId = null): void
    {
        if (!$this->canSendReply($chatId, $kind)) {
            return;
        }

        $this->sendMessage($chatId, $text, $parseMode, $threadId);
    }

    public function handle(Request $request)
    {
        $membershipUpdate = $request->input('chat_member') ?? $request->input('my_chat_member');

        if (is_array($membershipUpdate)) {
            $this->handleMembershipUpdate($membershipUpdate);

            return response()->json(['status' => 'ok'], 200);
        }

        $message = $request->input('message');

        if (!$message || !isset($message['text'])) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $chatId    = $message['chat']['id'];
        $fromId    = $message['from']['id'] ?? null;
        $threadId  = $message['message_thread_id'] ?? null;
        $text      = trim($message['text']);
        $isPrivate = $this->isPrivateChat($message);

        if (str_starts_with($text, '/start') && $isPrivate) {
            $this->handleStart($chatId, $text);
        } elseif ($this->isCommand($text, 'status')) {
            $isPrivate ? $this->handleStatus($chatId) : $this->handleGroupStatus($chatId, $fromId, $threadId);
        } elseif ($this->isCommand($text, 'help')) {
            $this->handleHelp($chatId, $isPrivate, $threadId);
        } elseif ($this->isCommand($text, 'opennews')) {
            $this->handleOpenNews($chatId, $fromId, $threadId);
        } elseif ($this->isCommand($text, 'closenews')) {
            $this->handleCloseNews($chatId, $fromId, $threadId);
        } elseif ($this->isCommand($text, 'myid')) {
            $this->handleMyId($chatId, $fromId, $threadId);
        } elseif ($isPrivate && str_starts_with($text, 'TELEGRAPH ') && $fromId !== null) {
            $url = trim(substr($text, 10));
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $this->handleNewsTelegraph($chatId, $fromId, $url);
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * /start TRK-XXXXX — Deep-link handshake.
     */
    private function handleStart(int|string $chatId, string $text): void
    {
        $token = $this->extractStartToken($text);
        $instanceLabel = $this->telegramInstanceLabel();

        if ($token === null || $token === '') {
            $this->sendMessageOnce($chatId, 'welcome', "\xE2\x9A\xA1 Bienvenido al bot de {$instanceLabel}.\n\nUsa el enlace de vinculación desde tu panel de notificaciones para conectar tu cuenta.\n\nEscribe /help para ver los comandos disponibles.");
            return;
        }

        // Validate token format
        if (!preg_match('/^TRK-[a-zA-Z0-9]+$/', $token)) {
            $this->sendMessageOnce($chatId, 'invalid-token-format', "\xE2\x9D\x8C Token inválido. Usa el botón \"Vincular con el Bot\" desde tu panel de notificaciones.");
            Log::warning('Telegram: invalid token format', ['token' => $token, 'chat_id' => $chatId]);
            return;
        }

        // Transactional linking with pessimistic lock
        DB::transaction(function () use ($chatId, $token) {
            $chatUser = User::where('telegram_chat_id', $chatId)
                ->lockForUpdate()
                ->first();

            $tokenUser = User::where('telegram_token', $token)
                ->lockForUpdate()
                ->first();

            if ($chatUser && $tokenUser && $chatUser->is($tokenUser)) {
                if ($chatUser->telegram_token !== null) {
                    $chatUser->telegram_token = null;
                    $chatUser->save();
                }

                $this->sendMessageOnce($chatId, 'already-linked-same-user', "\xE2\x9C\x85 Tu cuenta ya estaba vinculada: <b>{$chatUser->username}</b>.\n\nNo hace falta volver a usar el token. Escribe /status para comprobar el estado del enlace.", 'HTML');
                return;
            }

            if ($chatUser) {
                $this->sendMessageOnce($chatId, 'chat-already-linked', "\xE2\x9A\xA0\xEF\xB8\x8F Ya tienes una cuenta vinculada: <b>{$chatUser->username}</b>.\n\nSi quieres vincular otra cuenta, primero regenera el token desde tu panel.", 'HTML');
                return;
            }

            if (!$tokenUser) {
                $this->sendMessageOnce($chatId, 'token-not-found', "\xE2\x9D\x8C Token no encontrado o ya utilizado.\n\nRegenera tu token desde el panel de notificaciones e inténtalo de nuevo.");
                Log::warning('Telegram: token not found', ['token' => $token, 'chat_id' => $chatId]);
                return;
            }

            if ($tokenUser->telegram_chat_id !== null && (string) $tokenUser->telegram_chat_id !== (string) $chatId) {
                $tokenUser->telegram_token = null;
                $tokenUser->save();

                $this->sendMessageOnce($chatId, 'token-already-linked-elsewhere', "\xE2\x9A\xA0\xEF\xB8\x8F Ese token pertenece a una cuenta que ya estaba vinculada.\n\nPor seguridad, ese token ha caducado. Si necesitas mover el enlace, regenera uno nuevo desde el panel.", 'HTML');
                Log::warning('Telegram: stale token reuse blocked', [
                    'user'           => $tokenUser->username,
                    'chat_id'        => $chatId,
                    'linked_chat_id' => $tokenUser->telegram_chat_id,
                ]);
                return;
            }

            $tokenUser->telegram_chat_id = $chatId;
            $tokenUser->telegram_token = null;
            $tokenUser->save();

            $groupJoined = app(TelegramService::class)->syncLinkedUserMembership($tokenUser);

            $inviteLink = config('services.telegram.group_invite_link');
            $successText = $groupJoined
                ? "\xE2\x9C\x85 <b>Handshake Successful, {$tokenUser->username}!</b>\n\n\xF0\x9F\x94\x92 Tu cuenta ha sido vinculada al bot y tu membresía del grupo ya está confirmada.\nRecibirás notificaciones de nuevos torrents directamente aquí.\n\nEscribe /status para verificar tu enlace."
                : "\xE2\x9C\x85 <b>Handshake Successful, {$tokenUser->username}!</b>\n\n\xF0\x9F\x94\x92 Tu cuenta ha sido vinculada al bot de Nuclear Order.\nPara completar el acceso, entra ahora al grupo desde el botón inferior.\n\nEscribe /status para verificar tu enlace.";

            if ($inviteLink) {
                $this->sendMessageWithButton($chatId, $successText, "\xF0\x9F\x93\xA1 UNIRSE AL GRUPO", $inviteLink);
            } else {
                $this->sendMessage($chatId, $successText, 'HTML');
            }

            Log::info('Telegram: account linked', ['user' => $tokenUser->username, 'chat_id' => $chatId]);
        });
    }

    /**
     * /status — Check link status (private chat).
     */
    private function handleStatus(int|string $chatId): void
    {
        if (!$this->canSendReply($chatId, 'status')) {
            return;
        }

        $instanceLabel = $this->telegramInstanceLabel();
        $user = User::where('telegram_chat_id', $chatId)->first();

        if ($user) {
            $groupJoined = app(TelegramService::class)->syncLinkedUserMembership($user);
            $inviteLink = config('services.telegram.group_invite_link');
            $statusText = $groupJoined
                ? "\xF0\x9F\x9F\xA2 <b>ACCESO VERIFICADO</b>\n\n\xF0\x9F\x91\xA4 Usuario: <b>{$user->username}</b>\n\xF0\x9F\x94\x97 Bot: Vinculado\n\xF0\x9F\x93\xA1 Grupo: Confirmado"
                : "\xF0\x9F\x9F\xA1 <b>BOT VINCULADO</b>\n\n\xF0\x9F\x91\xA4 Usuario: <b>{$user->username}</b>\n\xF0\x9F\x94\x97 Bot: Vinculado\n\xF0\x9F\x93\xA1 Grupo: Pendiente de confirmación";

            if (!$groupJoined && $inviteLink) {
                $this->sendMessageWithButton($chatId, $statusText, "\xF0\x9F\x93\xA1 UNIRSE AL GRUPO", $inviteLink);
            } else {
                $this->sendMessage($chatId, $statusText, 'HTML');
            }
        } else {
            $this->sendMessageOnce($chatId, 'status-unlinked', "\xF0\x9F\x94\xB4 <b>SIN VINCULAR</b>\n\nTu cuenta de Telegram no está vinculada a ningún usuario de {$instanceLabel}.\nUsa el enlace desde tu panel de notificaciones para conectarte.", 'HTML');
        }
    }

    /**
     * /status — Check link status (group chat).
     */
    private function handleGroupStatus(int|string $chatId, ?int $fromId, ?int $threadId = null): void
    {
        if ($fromId === null) {
            return;
        }

        if (!$this->canSendReply($fromId, 'group-status')) {
            return;
        }

        $user = User::where('telegram_chat_id', $fromId)->first();

        if ($user && $user->telegram_group_joined_at !== null) {
            $this->sendMessage($chatId, "\xF0\x9F\x9F\xA2 Vinculado y acceso confirmado.", 'HTML', $threadId);
        } elseif ($user) {
            $inviteLink = config('services.telegram.group_invite_link');
            $text = "\xF0\x9F\x9F\xA1 Bot vinculado — pendiente de confirmar el grupo.";
            $inviteLink
                ? $this->sendMessageWithButton($chatId, $text, "\xF0\x9F\x93\xA1 UNIRSE AL GRUPO", $inviteLink, $threadId)
                : $this->sendMessage($chatId, $text, null, $threadId);
        } else {
            $instanceLabel = $this->telegramInstanceLabel();
            $this->sendMessage($chatId, "\xF0\x9F\x94\xB4 Sin vincular. Usa el enlace del panel de notificaciones en {$instanceLabel}.", null, $threadId);
        }
    }

    /**
     * /opennews — Reopen novedades forum topic (admin only).
     */
    private function handleOpenNews(int|string $chatId, ?int $fromId, ?int $threadId = null): void
    {
        if (!$this->isTrackerAdmin($fromId)) {
            $this->sendMessageOnce($chatId, 'opennews-denied', "\xF0\x9F\x94\x92 Solo administradores del tracker.", null, $threadId);
            return;
        }

        $ok = app(TelegramService::class)->setForumTopicState(true);
        $this->sendMessage($chatId, $ok
            ? "\xE2\x9C\x85 Topic de novedades abierto."
            : "\xE2\x9D\x8C No se pudo abrir el topic. \xC2\xBFTengo permisos de admin con Manage Topics?", null, $threadId);
    }

    /**
     * /closenews — Close novedades forum topic (admin only).
     */
    private function handleCloseNews(int|string $chatId, ?int $fromId, ?int $threadId = null): void
    {
        if (!$this->isTrackerAdmin($fromId)) {
            $this->sendMessageOnce($chatId, 'closenews-denied', "\xF0\x9F\x94\x92 Solo administradores del tracker.", null, $threadId);
            return;
        }

        $ok = app(TelegramService::class)->setForumTopicState(false);
        $this->sendMessage($chatId, $ok
            ? "\xE2\x9C\x85 Topic de novedades cerrado."
            : "\xE2\x9D\x8C No se pudo cerrar el topic. \xC2\xBFTengo permisos de admin con Manage Topics?", null, $threadId);
    }

    /**
     * /myid — Return caller's Telegram user ID.
     */
    private function handleMyId(int|string $chatId, ?int $fromId, ?int $threadId = null): void
    {
        if ($fromId === null) {
            return;
        }

        $this->sendMessageOnce($chatId, sprintf('myid:%s', $fromId), "\xF0\x9F\x86\x94 Tu ID de Telegram: <code>{$fromId}</code>", 'HTML', $threadId);
    }

    /**
     * TELEGRAPH <url> — post daily news from trusted news bot (private DM only).
     */
    private function handleNewsTelegraph(int|string $chatId, int $fromId, string $url): void
    {
        $allowedBotId = (int) config('services.telegram.news_bot_id');

        if ($allowedBotId === 0 || $fromId !== $allowedBotId) {
            Log::warning('Telegram: TELEGRAPH request from unauthorized sender', ['from_id' => $fromId]);
            return;
        }

        $service     = app(TelegramService::class);
        $groupChatId = config('services.telegram.chat_id');
        $topicId     = (int) config('services.telegram.topic_id');

        $service->setForumTopicState(true);

        $this->sendMessage($groupChatId, "\xF0\x9F\x93\xB0 " . $url, null, $topicId);

        $service->setForumTopicState(false);

        $this->sendMessage($chatId, "\xE2\x9C\x85 Publicado en novedades.");

        Log::info('Telegram: TELEGRAPH posted to novedades', ['url' => $url]);
    }

    /**
     * Check whether the Telegram user is a tracker admin/mod/owner.
     */
    private function isTrackerAdmin(?int $fromId): bool
    {
        if ($fromId === null) {
            return false;
        }

        $user = User::where('telegram_chat_id', $fromId)->with('group')->first();

        return $user !== null
            && ($user->group->is_admin || $user->group->is_owner || $user->group->is_modo);
    }

    /**
     * /help — Command list.
     */
    private function handleHelp(int|string $chatId, bool $isPrivate = true, ?int $threadId = null): void
    {
        $instanceLabel = $this->telegramInstanceLabel();

        if ($isPrivate) {
            $text = "\xF0\x9F\xA4\x96 <b>{$instanceLabel} Tracker Bot — Comandos</b>\n\n/start — Vincular tu cuenta (usa el enlace del panel)\n/status — Comprobar estado del enlace\n/help — Mostrar esta ayuda\n\n\xE2\x9A\xA1 Las notificaciones de nuevos torrents se envían automáticamente una vez vinculado. El acceso queda completo cuando también se confirma tu entrada al grupo.";
        } else {
            $text = "\xF0\x9F\xA4\x96 <b>{$instanceLabel} Bot — Comandos de grupo</b>\n\n/status — Estado de tu vinculación\n/myid — Ver tu ID de Telegram\n/opennews — Abrir topic de novedades <i>(admin)</i>\n/closenews — Cerrar topic de novedades <i>(admin)</i>\n/help — Esta ayuda\n\n\xF0\x9F\x92\xAC Para vincular tu cuenta, escríbeme en privado.";
        }

        $this->sendMessage($chatId, $text, 'HTML', $threadId);
    }

    /**
     * Send a message via Telegram API.
     */
    private function sendMessage(int|string $chatId, string $text, ?string $parseMode = null, ?int $threadId = null): void
    {
        $botToken = config('services.telegram.token');

        if (empty($botToken)) {
            Log::error('Telegram: bot token not configured');
            return;
        }

        $payload = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($threadId !== null) {
            $payload['message_thread_id'] = $threadId;
        }

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);
        } catch (\Throwable $e) {
            Log::error('Telegram: failed to send message', ['error' => $e->getMessage(), 'chat_id' => $chatId]);
        }
    }

    /**
     * Send a message with an inline keyboard button (URL).
     */
    private function sendMessageWithButton(int|string $chatId, string $text, string $buttonText, string $url, ?int $threadId = null): void
    {
        $botToken = config('services.telegram.token');

        if (empty($botToken)) {
            Log::error('Telegram: bot token not configured');
            return;
        }

        $payload = [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => $buttonText, 'url' => $url]],
                ],
            ],
        ];

        if ($threadId !== null) {
            $payload['message_thread_id'] = $threadId;
        }

        try {
            Http::timeout(10)->asJson()->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);
        } catch (\Throwable $e) {
            Log::error('Telegram: failed to send button message', ['error' => $e->getMessage(), 'chat_id' => $chatId]);
        }
    }

    private function handleMembershipUpdate(array $update): void
    {
        $groupChatId = (string) config('services.telegram.chat_id', '');
        $updateChatId = (string) ($update['chat']['id'] ?? '');

        if ($groupChatId === '' || $updateChatId !== $groupChatId) {
            return;
        }

        $member = $update['new_chat_member'] ?? null;

        if (!is_array($member)) {
            return;
        }

        $telegramChatId = $member['user']['id'] ?? null;

        if ($telegramChatId === null) {
            return;
        }

        $user = User::where('telegram_chat_id', $telegramChatId)->first();

        if ($user === null) {
            Log::info('Telegram: membership update ignored for unknown linked chat', ['chat_id' => $telegramChatId]);

            return;
        }

        $isMember = in_array($member['status'] ?? null, ['member', 'administrator', 'creator'], true)
            || (($member['status'] ?? null) === 'restricted' && (bool) ($member['is_member'] ?? false));

        app(TelegramService::class)->persistMembershipState($user, $isMember);

        Log::info('Telegram: group membership synced', [
            'user'      => $user->username,
            'chat_id'   => $telegramChatId,
            'is_member' => $isMember,
            'status'    => $member['status'] ?? null,
        ]);
    }
}
