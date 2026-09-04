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
 * Aviso de salida de la amnistia de descarga del freeleech.
 *
 * Tres motivos y tres mensajes distintos, porque para el usuario son tres
 * situaciones opuestas: ha salido del grupo por meritos propios, se le ha
 * acabado el plazo, o ha perdido la descarga por Hit & Run.
 */
class LeechAmnestyRevoked extends Notification implements ShouldQueue, SystemNotificationInterface
{
    use Queueable;

    /**
     * @param string $reason freeleech_ended | hitrun | left_group
     */
    public function __construct(private readonly string $reason = 'freeleech_ended')
    {
    }

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
        $ratio = number_format((float) config('other.ratio'), 2, ',', '.');

        return match ($this->reason) {
            'left_group' => [
                'subject' => 'Has salido de Sanguijuela',
                'message' => '[b]Enhorabuena: ya no estas en Sanguijuela.[/b]'."\n\n"
                    .'Has subido el ratio por encima de '.$ratio.' y el sistema te ha ascendido '
                    .'solo. Tus derechos de descarga son ahora los normales de tu grupo, no los '
                    .'prestados del freeleech: cuando el freeleech acabe, [b]no[/b] se te quita nada.'."\n\n"
                    .'Sigue sembrando lo que ya tienes conectado. Lo que te ha sacado de ahi es lo '
                    .'mismo que te mantiene fuera.',
            ],
            'hitrun' => [
                'subject' => 'Has perdido la descarga por Hit & Run',
                'message' => '[b]Se te ha retirado la descarga por acumular avisos de Hit & Run.[/b]'."\n\n"
                    .'La amnistia del freeleech perdona el ratio bajo, pero no los avisos. Has '
                    .'llegado al maximo de avisos activos y eso pesa mas que el freeleech.'."\n\n"
                    .'Un aviso se apaga cuando siembras ese torrent el tiempo minimo exigido. '
                    .'Vuelve a conectar los torrents por los que te avisaron y dejalos sembrando: '
                    .'es la via directa. Si crees que hay un error o tu caso es particular, abre un '
                    .'ticket y lo miramos a mano.',
            ],
            default => [
                'subject' => 'Se acabo el freeleech: vuelves a estar bloqueado',
                'message' => '[b]El freeleech ha terminado y con el la amnistia de descarga.[/b]'."\n\n"
                    .'Sigues en Sanguijuela, asi que vuelves a quedarte sin poder descargar hasta '
                    .'que tu ratio suba de '.$ratio.'.'."\n\n"
                    .'[b]No estas atrapado.[/b] Lo que tengas ya bajado sigue contando: dejalo '
                    .'sembrando y la subida sube igual. Y en la tienda de BON puedes cambiar puntos '
                    .'por ratio directamente — 100 GB de subida por 5.000 BON, o «Operacion bikini», '
                    .'que te resta 100 GB de descarga por otros 5.000.'."\n\n"
                    .'En cuanto pases el umbral el sistema te asciende solo, sin que tengas que '
                    .'pedir nada.',
            ],
        };
    }
}
