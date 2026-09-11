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

namespace App\Notifications;

use App\Interfaces\SystemNotificationInterface;
use App\Models\User;
use App\Notifications\Channels\SystemNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Aviso al staff de que una promo global ha vencido y se ha apagado sola.
 *
 * Lleva el estado de los dos candados a proposito: apagar el ajuste no basta,
 * porque quien cobra es el announce Rust y su .env es cache derivada. Si el
 * empujon no llego, el aviso lo dice en vez de dejar creer que todo fue bien.
 *
 * El texto va en espanol literal, sin claves de traduccion, como el resto de
 * avisos de este fork.
 */
class PromoExpired extends Notification implements ShouldQueue, SystemNotificationInterface
{
    use Queueable;

    /**
     * @param string $promoLabel  nombre de la promo, tal como lo llama el panel
     * @param string $venceUtc    hora de fin que anunciaba el panel, 'Y-m-d H:i' UTC
     * @param string $corteUtc    hora real del apagado, 'Y-m-d H:i' UTC
     * @param list<array{texto: string, ok: bool}> $candados  comprobaciones posteriores al apagado
     */
    public function __construct(
        private readonly string $promoLabel,
        private readonly string $venceUtc,
        private readonly string $corteUtc,
        private readonly array $candados,
    ) {}

    /**
     * @return class-string
     */
    public function via(object $notifiable): string
    {
        return SystemNotificationChannel::class;
    }

    /**
     * @return array{subject: string, message: string}
     */
    public function toSystemNotification(User $notifiable): array
    {
        return [
            'subject' => 'Promo terminada: '.$this->promoLabel,
            'message' => self::cuerpo($this->promoLabel, $this->venceUtc, $this->corteUtc, $this->candados),
        ];
    }

    /**
     * Mismo texto para la campanita y para Telegram, para que no se separen.
     *
     * Lo que falla se marca en negrita: un aviso que da igual leer entero que
     * por encima no sirve de nada a las cuatro de la manana.
     *
     * @param list<array{texto: string, ok: bool}> $candados
     */
    public static function cuerpo(string $promoLabel, string $venceUtc, string $corteUtc, array $candados): string
    {
        $lineas = '';

        foreach ($candados as $candado) {
            $lineas .= '[*]'.($candado['ok'] ? $candado['texto'] : '[b]'.$candado['texto'].'[/b]')."\n";
        }

        $fallos = \count(array_filter($candados, static fn (array $c): bool => !$c['ok']));

        $cierre = $fallos === 0
            ? 'La fecha de fin se ha vaciado en el panel. Para volver a encender la promo hay que ponerle fecha nueva.'
            : '[b]Hay '.$fallos.' comprobacion(es) en rojo: revisalo a mano.[/b] La fecha de fin se ha vaciado en el panel igualmente.';

        return '[b]'.$promoLabel.'[/b] ha vencido y se ha apagado sola.'."\n\n"
            .'[list]'
            .'[*]Fin anunciado: [b]'.$venceUtc.' UTC[/b]'."\n"
            .'[*]Apagado real: [b]'.$corteUtc.' UTC[/b]'."\n"
            .'[/list]'."\n"
            .'[b]Comprobaciones[/b]'."\n"
            .'[list]'
            .$lineas
            .'[/list]'."\n"
            .$cierre;
    }

    /**
     * Version en texto plano para Telegram: mismas frases, sin BBCode.
     *
     * @param list<array{texto: string, ok: bool}> $candados
     */
    public static function cuerpoPlano(string $promoLabel, string $venceUtc, string $corteUtc, array $candados): string
    {
        $texto = self::cuerpo($promoLabel, $venceUtc, $corteUtc, $candados);

        return trim(preg_replace(['#\[/?b\]#', '#\[/?list\]#', '#\[\*\]#'], ['', '', '- '], $texto) ?? $texto);
    }
}
