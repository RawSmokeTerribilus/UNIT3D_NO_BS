<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Services;

use App\Models\Conversation;
use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Un aviso a un socio por donde lo vaya a leer: privado del bot de Telegram si
 * lo tiene vinculado y el envío sale; si no, privado de System en el tracker.
 */
class AvisoDirecto
{
    /**
     * @param string $texto Cuerpo en texto plano; [b]…[/b] marca negritas.
     * @return 'telegram'|'privado'
     */
    public static function enviar(User $user, string $asunto, string $texto, string $boton, string $url): string
    {
        if (!empty($user->telegram_chat_id)) {
            $html = '<b>'.e($asunto).'</b>'."\n\n"
                .(preg_replace('/\[b\](.*?)\[\/b\]/s', '<b>$1</b>', e($texto)) ?? e($texto));

            if (app(TelegramService::class)->sendMessageWithButton($html, (string) $user->telegram_chat_id, $boton, $url)) {
                return 'telegram';
            }
        }

        DB::transaction(function () use ($user, $asunto, $texto, $boton, $url): void {
            $conversation = Conversation::create(['subject' => $asunto]);

            PrivateMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => User::SYSTEM_USER_ID,
                'message'         => $texto."\n\n[url=".$url.']'.$boton.'[/url]',
            ]);

            $conversation->users()->sync([User::SYSTEM_USER_ID => ['read' => true], $user->id]);
        });

        return 'privado';
    }
}
