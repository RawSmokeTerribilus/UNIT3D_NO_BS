<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 * Copyright (C) 2026 RawSmoke
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 */

namespace App\Notifications;

use App\Interfaces\SystemNotificationInterface;
use App\Models\User;
use App\Notifications\Channels\SystemNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Un solo aviso por reportero y por tanda del boton nuke de la lista de
 * reportes, no uno por reporte: quien reporta con un script manda decenas.
 */
class ReportesDescartadosEnBloque extends Notification implements ShouldQueue, SystemNotificationInterface
{
    use Queueable;

    private const int MAX_TITULOS = 20;

    /**
     * @param list<string> $titulos
     * @param string       $staff   quien pulso la seta; el aviso lo nombra
     */
    public function __construct(public readonly array $titulos, public readonly string $staff)
    {
    }

    public function via(object $notifiable): string
    {
        return SystemNotificationChannel::class;
    }

    public function toSystemNotification(User $notifiable): array
    {
        $n = \count($this->titulos);

        $lista = '';
        foreach (\array_slice($this->titulos, 0, self::MAX_TITULOS) as $titulo) {
            $lista .= '[*]'.$titulo."\n";
        }
        if ($n > self::MAX_TITULOS) {
            $lista .= '[*]... y '.($n - self::MAX_TITULOS).' mas'."\n";
        }

        $message = ($n === 1
                ? 'Hemos descartado [b]1[/b] de tus reportes'
                : 'Hemos descartado [b]'.$n.'[/b] de tus reportes')
            .' por motivos tecnicos, sin revisarlos uno a uno. No se ha tomado ninguna '
            .'medida sobre lo que reportaste.'."\n\n"
            .'[list]'.$lista.'[/list]'."\n"
            .'Descartados por: [b]'.$this->staff.'[/b]'."\n\n"
            .'Si crees que alguno merecia atencion, contacta con '.$this->staff.' o con otro admin.';

        return [
            'subject' => $n === 1 ? 'Tu reporte se ha descartado' : 'Tus reportes se han descartado',
            'message' => $message,
        ];
    }
}
