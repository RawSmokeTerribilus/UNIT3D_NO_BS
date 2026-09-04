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
use App\Services\LeechAmnesty;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Aviso de entrada en la amnistia de descarga del freeleech.
 *
 * El texto insiste en que el freeleech CONGELA pero NO REPARA porque ese fue
 * exactamente el malentendido del reporte #3 del 2026-09-02: un usuario con
 * 899.420 BON sin gastar creia que el freeleech le arreglaria el ratio solo,
 * y le faltaban 3,65 GiB de subida que podia haber comprado cien veces.
 */
class LeechAmnestyGranted extends Notification implements ShouldQueue, SystemNotificationInterface
{
    use Queueable;

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
        $slots = LeechAmnesty::slots();
        $ratio = number_format((float) config('other.ratio'), 2, ',', '.');
        $until = (string) config('other.freeleech_until');

        $message = '[b]Se te ha devuelto la descarga mientras dure el freeleech.[/b]'."\n\n"
            .'Estas en [b]Sanguijuela[/b] porque tu ratio esta por debajo de '.$ratio.'. '
            .'Normalmente ese grupo no puede descargar, y ahi es facil quedarse atascado: '
            .'sin descargar no siembras, y sin sembrar no subes el ratio. Mientras haya '
            .'freeleech global te abrimos la puerta para que puedas salir.'."\n\n"
            .'[b]Que puedes hacer ahora[/b]'."\n"
            .'[list]'
            .'[*]Descargar hasta [b]'.$slots.'[/b] torrents a la vez.'."\n"
            .'[*]Nada de lo que bajes te cuenta como descarga mientras dure el freeleech.'."\n"
            .'[*]Todo lo que siembres SI te cuenta como subida.'."\n"
            .'[/list]'."\n"
            .'[b]Lo importante, y es donde se equivoca casi todo el mundo:[/b] el freeleech '
            .'[b]congela[/b] tu ratio, pero [b]no repara[/b] lo que ya debes. Si te quedas '
            .'quieto, cuando esto acabe seguiras igual que ahora. La unica forma de salir es '
            .'[b]sembrar[/b] lo que bajes, y cuanto mas tiempo lo dejes conectado, mejor.'."\n\n"
            .'[b]Atajo si tienes BON[/b] — en la tienda de BON puedes cambiarlos por ratio '
            .'directamente: 100 GB de subida por 5.000 BON, o «Operacion bikini», que te resta '
            .'100 GB de descarga por otros 5.000. Mira cuantos tienes antes de nada.'."\n\n"
            .'[b]Ojo con el Hit & Run.[/b] La amnistia perdona el ratio, no los avisos. Si '
            .'acumulas avisos por no sembrar, te quedas sin descarga otra vez y esto no te '
            .'salva.'."\n\n"
            .'El freeleech termina el [b]'.$until.'[/b]. Cuando acabe, si sigues en '
            .'Sanguijuela, vuelves a quedarte sin descarga. Aprovechalo.';

        return [
            'subject' => 'Puedes descargar durante el freeleech',
            'message' => $message,
        ];
    }
}
