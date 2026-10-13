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

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Aviso de baneo por H&R abandonado, en lugar del UserBan nativo (ingles y
 * con un enlace al chat que un baneado no puede abrir). Lleva la invitacion
 * de un solo uso al grupo de Telegram «Baneados de NOBS», que es por donde se
 * responde: un baneado no navega el sitio.
 */
class HitRunBanned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $motivo,
        public ?string $invitacion,
        public Carbon $podaEl,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fecha = $this->podaEl->copy()->timezone('Europe/Madrid')->format('d/m/Y');

        $mail = (new MailMessage())
            ->subject('Tu cuenta de '.config('other.title').' ha sido baneada')
            ->greeting('Hola, '.$notifiable->username.'.')
            ->line('Tu cuenta ha sido baneada. Motivo: '.$this->motivo)
            ->line('Antes de llegar aquí te escribimos con tu lista de torrents y las horas que faltaban, y no ha habido respuesta ni actividad. Esto es un baneo, no un borrado: la cuenta sigue existiendo y se puede deshacer.');

        if ($this->invitacion !== null) {
            $mail->line('Si quieres explicarte o arreglarlo, entra en nuestro grupo de Telegram para baneados con este enlace. Es personal y vale para una sola persona: no lo compartas.')
                ->action('Entrar al grupo «Baneados de NOBS»', $this->invitacion);
        } else {
            $mail->line('Si quieres explicarte o arreglarlo, contesta a este correo.');
        }

        return $mail->line('Si el '.$fecha.' no hemos sabido nada de ti, la cuenta se dará de baja definitivamente.');
    }
}
