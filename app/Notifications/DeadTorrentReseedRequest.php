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
use App\Models\Torrent;
use App\Models\User;
use App\Notifications\Channels\SystemNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Peticion de resiembra de un torrent muerto, con la fecha de borrado.
 *
 * No se usa la nativa (NewReseedRequest): va solo a la campana, en ingles, sin
 * plazo, y a quien tiene historial inactivo aunque no completara la descarga.
 * Esta va por privado al que lo subio y a quien lo completo.
 */
class DeadTorrentReseedRequest extends Notification implements ShouldQueue, SystemNotificationInterface
{
    use Queueable;

    public function __construct(
        public Torrent $torrent,
        public Carbon $borradoEl,
        public bool $esSubidor,
    ) {
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
        $url = href_torrent($this->torrent);
        $fecha = $this->borradoEl->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i');

        $porque = $this->esSubidor
            ? 'Lo subiste tú y ahora mismo no lo está sembrando nadie.'
            : 'Lo descargaste completo en su día y ahora mismo no lo está sembrando nadie.';

        return [
            'subject' => 'Petición de resiembra: '.$this->torrent->name,
            'message' => '[b]Este torrent se ha quedado sin semillas:[/b] '
                .'[url='.$url.']'.$this->torrent->name.'[/url]'."\n\n"
                .$porque.' Si todavía lo tienes, vuelve a cargarlo en tu cliente y déjalo '
                .'sembrando: basta con que una persona lo siembre para salvarlo.'."\n\n"
                .'[b]Si sigue sin semillas el '.$fecha.' (hora peninsular), se borrará del '
                .'tracker.[/b]'."\n\n"
                .'Si ya no lo tienes, no hace falta que hagas nada.',
        ];
    }
}
