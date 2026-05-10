<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function syncLinkedUserMembership(User $user): bool
    {
        if (empty($user->telegram_chat_id)) {
            if ($user->telegram_group_joined_at !== null) {
                $user->forceFill(['telegram_group_joined_at' => null])->save();
            }

            return false;
        }

        $isMember = $this->isActiveGroupMember((string) $user->telegram_chat_id);

        if ($isMember === null) {
            return $user->telegram_group_joined_at !== null;
        }

        return $this->persistMembershipState($user, $isMember);
    }

    public function persistMembershipState(User $user, bool $isMember): bool
    {
        $newValue = $isMember ? now() : null;

        if (($user->telegram_group_joined_at !== null) === $isMember) {
            return $isMember;
        }

        $user->forceFill(['telegram_group_joined_at' => $newValue])->save();

        return $isMember;
    }

    public function isActiveGroupMember(string $telegramChatId): ?bool
    {
        $token = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');

        if (empty($token) || empty($chatId)) {
            Log::warning('TelegramService: Configuración incompleta para comprobar membresía de grupo');

            return null;
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getChatMember", [
                'chat_id' => $chatId,
                'user_id' => $telegramChatId,
            ]);

            if (!$response->successful()) {
                Log::warning('TelegramService: No se pudo consultar la membresía del grupo.', [
                    'chat_id' => $telegramChatId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $result = $response->json('result');

            if (!is_array($result)) {
                Log::warning('TelegramService: Respuesta inválida de getChatMember.', ['chat_id' => $telegramChatId]);

                return null;
            }

            return $this->isMemberStatus(
                (string) ($result['status'] ?? ''),
                (bool) ($result['is_member'] ?? false),
            );
        } catch (\Throwable $e) {
            Log::warning('TelegramService: Error consultando membresía del grupo.', [
                'chat_id' => $telegramChatId,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Envía un mensaje con Poster (si existe) o solo texto.
     */
    public function sendAnnouncement(string $text, ?string $photoUrl = null, ?string $threadId = null): bool
    {
        $token = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');
        
        // Si hay foto, disparamos sendPhoto. Si no, sendMessage.
        $method = $photoUrl ? 'sendPhoto' : 'sendMessage';
        $payload = [
            'chat_id' => $chatId,
            'message_thread_id' => $threadId,
            'parse_mode' => 'HTML',
        ];

        if ($photoUrl) {
            $payload['photo'] = $photoUrl;
            $payload['caption'] = $text; // En sendPhoto el texto es el 'caption'
        } else {
            $payload['text'] = $text;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/{$method}", $payload);
            return $response->successful();
        } catch (\Throwable $e) {
            \Log::error("Error en Anuncio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envía un mensaje a un hilo específico del búnker.
     */
    public function sendMessage(string $text, ?string $chatId = null, ?string $threadId = null): bool
    {
        $token = config('services.telegram.token');
        $chatId = $chatId ?? config('services.telegram.chat_id');

        if (empty($token) || empty($chatId)) {
            Log::warning('TelegramService: Configuración incompleta en el .env');
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'message_thread_id' => $threadId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('TelegramService: Fallo en envío.', ['msg' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * FASE 2: LA GUILLOTINA
     * Expulsa al usuario del grupo de Telegram.
     * Ban + Unban inmediato = kick limpio (el usuario puede volver a unirse después).
     */
    public function kickUser(string $telegramChatId): bool
    {
        $token = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/banChatMember", [
                'chat_id' => $chatId,
                'user_id' => $telegramChatId,
                'revoke_messages' => true,
            ]);

            if ($response->successful()) {
                // Unban inmediato para que sea un kick, no un ban permanente
                Http::post("https://api.telegram.org/bot{$token}/unbanChatMember", [
                    'chat_id'        => $chatId,
                    'user_id'        => $telegramChatId,
                    'only_if_banned' => true,
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('TelegramService: Error en banChatMember.', ['id' => $telegramChatId]);
            return false;
        }
    }

    private function isMemberStatus(string $status, bool $isMember = false): bool
    {
        return in_array($status, ['member', 'administrator', 'creator'], true)
            || ($status === 'restricted' && $isMember);
    }
}
