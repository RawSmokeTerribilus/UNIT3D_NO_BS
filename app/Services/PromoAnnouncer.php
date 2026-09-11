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

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Anuncia por Telegram lo que le pasa a las promos globales.
 *
 * Dos audiencias y dos mensajes distintos, a proposito:
 *
 *  - Los miembros, en el topic de noticias del grupo: que empieza, que termina,
 *    y hasta cuando. Nada de nombres de variables ni de estado del tracker.
 *  - El staff, en su grupo aparte: el detalle operativo, incluido si el empujon
 *    al announce Rust llego o no.
 *
 * Si falta el destino en el .env se salta y se registra, pero un aviso interno
 * NUNCA cae al grupo de miembros por defecto.
 */
final class PromoAnnouncer
{
    /**
     * Texto para los miembros. `null` en `until` = sin fecha de fin anunciada.
     */
    public static function textoMiembros(string $promo, bool $encendida, ?CarbonImmutable $until): string
    {
        $hasta = $until === null
            ? ''
            : ' Termina el <b>'.$until->format('d/m/Y \a \l\a\s H:i').' UTC</b>.';

        return match ([$promo, $encendida]) {
            ['freeleech', true] => '⚡ <b>Freeleech global activado</b>'."\n\n"
                .'Nada de lo que descargues cuenta como descarga mientras dure.'.$hasta."\n\n"
                .'Recuerda: el freeleech <b>congela</b> el ratio, no lo repara. Lo que arregla el ratio es sembrar.',
            ['freeleech', false] => '⚡ <b>Se acabo el freeleech global</b>'."\n\n"
                .'La descarga vuelve a contar desde este momento. Lo que bajaste durante la promo sigue sin contar: eso no se toca.'."\n\n"
                .'Deja sembrando lo que te llevaste, que ahora es cuando suma.',
            ['doubleup', true] => '🚀 <b>Doble subida global activada</b>'."\n\n"
                .'Todo lo que siembres cuenta el doble.'.$hasta,
            ['doubleup', false] => '🚀 <b>Se acabo la doble subida global</b>'."\n\n"
                .'La subida vuelve a contar al ritmo normal desde este momento.',
            ['openreg', true] => '🔓 <b>Registro abierto</b>'."\n\n"
                .'Cualquiera puede registrarse sin invitacion.'.$hasta."\n\n"
                .'Buen momento para avisar a quien llevabas tiempo queriendo traer.',
            ['openreg', false] => '🔓 <b>Registro cerrado</b>'."\n\n"
                .'Se vuelve a entrar solo por invitacion.',
            default => '',
        };
    }

    /**
     * Anuncia a los miembros en el topic de noticias.
     */
    public static function miembros(string $promo, bool $encendida, ?CarbonImmutable $until): bool
    {
        $texto = self::textoMiembros($promo, $encendida, $until);

        if ($texto === '') {
            return false;
        }

        $topic = config('services.telegram.topic_noticias');

        if (empty($topic)) {
            Log::warning('PromoAnnouncer: sin TELEGRAM_TOPIC_NOTICIAS, no anuncio a los miembros.', [
                'promo'     => $promo,
                'encendida' => $encendida,
            ]);

            return false;
        }

        return app(TelegramService::class)->sendMessage($texto, null, (string) $topic);
    }

    /**
     * Manda un informe al grupo interno del staff.
     *
     * Sin `staff_chat_id` no se envia a ninguna parte: no hay caida al grupo de
     * miembros, que es justo lo que no debe pasar con un mensaje interno.
     */
    public static function staff(string $texto): bool
    {
        $chatId = config('services.telegram.staff_chat_id');

        if (empty($chatId)) {
            Log::warning('PromoAnnouncer: sin TELEGRAM_STAFF_GROUP_ID, el aviso interno no sale.');

            return false;
        }

        return app(TelegramService::class)->sendMessage($texto, (string) $chatId);
    }
}
