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

/**
 * Aviso de desbaneo, en lugar del UserBanExpire nativo (en inglés y sin el
 * camino de vuelta al grupo de Telegram). Ver App\Services\BanConInvitacion::unban().
 */
class HitRunUnbanned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $motivo,
        public ?string $enlaceGrupo,
        public bool $telegramRestaurado,
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
        $mail = (new MailMessage())
            ->subject('Tu cuenta de '.config('other.title').' vuelve a estar activa')
            ->greeting('Hola, '.$notifiable->username.'.')
            ->line('Tu cuenta ha sido desbaneada y vuelve a estar activa.');

        if (trim($this->motivo) !== '') {
            $mail->line('Nota del staff: '.trim($this->motivo));
        }

        if ($this->enlaceGrupo !== null) {
            $mail->line($this->telegramRestaurado
                ? 'Tu Telegram sigue vinculado: solo tienes que volver a entrar al grupo.'
                : 'Para entrar a la web tendrás que volver a vincular tu Telegram desde tu perfil, y después entrar al grupo.')
                ->action('Volver al grupo de Telegram', $this->enlaceGrupo);
        }

        return $mail->line('Bienvenido de vuelta.');
    }
}
