<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $message = $request->input('message');

        if (!$message || !isset($message['text'])) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $chatId = $message['chat']['id'];
        $text   = trim($message['text']);
        $from   = $message['from']['first_name'] ?? 'usuario';

        // Route commands
        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId, $text);
        } elseif ($text === '/status') {
            $this->handleStatus($chatId);
        } elseif ($text === '/help') {
            $this->handleHelp($chatId);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * /start TRK-XXXXX — Deep-link handshake.
     */
    private function handleStart(int|string $chatId, string $text): void
    {
        $token = trim(str_replace('/start', '', $text));

        if (empty($token)) {
            $this->sendMessage($chatId, "\xE2\x9A\xA1 Bienvenido al bot de NOBS.\n\nUsa el enlace de vinculación desde tu panel de notificaciones para conectar tu cuenta.\n\nEscribe /help para ver los comandos disponibles.");
            return;
        }

        // Validate token format
        if (!preg_match('/^TRK-[a-zA-Z0-9]+$/', $token)) {
            $this->sendMessage($chatId, "\xE2\x9D\x8C Token inválido. Usa el botón \"Vincular con el Bot\" desde tu panel de notificaciones.");
            Log::warning('Telegram: invalid token format', ['token' => $token, 'chat_id' => $chatId]);
            return;
        }

        // Check if this chat is already linked to a different account
        $existingUser = User::where('telegram_chat_id', $chatId)->first();

        if ($existingUser) {
            $this->sendMessage($chatId, "\xE2\x9A\xA0\xEF\xB8\x8F Ya tienes una cuenta vinculada: <b>{$existingUser->username}</b>.\n\nSi quieres vincular otra cuenta, primero regenera el token desde tu panel.", 'HTML');
            return;
        }

        // Transactional linking with pessimistic lock
        DB::transaction(function () use ($chatId, $token) {
            $user = User::where('telegram_token', $token)
                ->lockForUpdate()
                ->first();

            if (!$user) {
                $this->sendMessage($chatId, "\xE2\x9D\x8C Token no encontrado o ya utilizado.\n\nRegenera tu token desde el panel de notificaciones e inténtalo de nuevo.");
                Log::warning('Telegram: token not found', ['token' => $token, 'chat_id' => $chatId]);
                return;
            }

            $user->telegram_chat_id = $chatId;
            $user->telegram_token = null;
            $user->save();

            $inviteLink = config('services.telegram.group_invite_link');
            $successText = "\xE2\x9C\x85 <b>Handshake Successful, {$user->username}!</b>\n\n\xF0\x9F\x94\x92 Tu cuenta ha sido vinculada al bot de Nuclear Order.\nRecibirás notificaciones de nuevos torrents directamente aquí.\n\nEscribe /status para verificar tu enlace.";

            if ($inviteLink) {
                $this->sendMessageWithButton($chatId, $successText, "\xF0\x9F\x93\xA1 UNIRSE AL GRUPO", $inviteLink);
            } else {
                $this->sendMessage($chatId, $successText, 'HTML');
            }

            Log::info('Telegram: account linked', ['user' => $user->username, 'chat_id' => $chatId]);
        });
    }

    /**
     * /status — Check link status.
     */
    private function handleStatus(int|string $chatId): void
    {
        $user = User::where('telegram_chat_id', $chatId)->first();

        if ($user) {
            $inviteLink = config('services.telegram.group_invite_link');
            $statusText = "\xF0\x9F\x9F\xA2 <b>ENLACE ACTIVO</b>\n\n\xF0\x9F\x91\xA4 Usuario: <b>{$user->username}</b>\n\xF0\x9F\x94\x97 Estado: Vinculado\n\xF0\x9F\x93\xA1 Notificaciones: Activas";

            if ($inviteLink) {
                $this->sendMessageWithButton($chatId, $statusText, "\xF0\x9F\x93\xA1 UNIRSE AL GRUPO", $inviteLink);
            } else {
                $this->sendMessage($chatId, $statusText, 'HTML');
            }
        } else {
            $this->sendMessage($chatId, "\xF0\x9F\x94\xB4 <b>SIN VINCULAR</b>\n\nTu cuenta de Telegram no está vinculada a ningún usuario de NOBS.\nUsa el enlace desde tu panel de notificaciones para conectarte.", 'HTML');
        }
    }

    /**
     * /help — Command list.
     */
    private function handleHelp(int|string $chatId): void
    {
        $this->sendMessage($chatId, "\xF0\x9F\xA4\x96 <b>NOBS Tracker Bot — Comandos</b>\n\n/start — Vincular tu cuenta (usa el enlace del panel)\n/status — Comprobar estado del enlace\n/help — Mostrar esta ayuda\n\n\xE2\x9A\xA1 Las notificaciones de nuevos torrents se envían automáticamente una vez vinculado.", 'HTML');
    }

    /**
     * Send a message via Telegram API.
     */
    private function sendMessage(int|string $chatId, string $text, ?string $parseMode = null): void
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

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);
        } catch (\Throwable $e) {
            Log::error('Telegram: failed to send message', ['error' => $e->getMessage(), 'chat_id' => $chatId]);
        }
    }

    /**
     * Send a message with an inline keyboard button (URL).
     */
    private function sendMessageWithButton(int|string $chatId, string $text, string $buttonText, string $url): void
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

        try {
            Http::timeout(10)->asJson()->post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);
        } catch (\Throwable $e) {
            Log::error('Telegram: failed to send button message', ['error' => $e->getMessage(), 'chat_id' => $chatId]);
        }
    }
}
