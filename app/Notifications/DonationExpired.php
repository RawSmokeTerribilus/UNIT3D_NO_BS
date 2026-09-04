<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     Roardom <roardom@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

/**
 * MODIFICADO PARA NOBS
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
 * Se distribuye bajo la misma licencia, GNU AGPL v3.0.
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

class DonationExpired extends Notification implements ShouldQueue, SystemNotificationInterface
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return class-string
     */
    public function via(object $notifiable): string
    {
        return SystemNotificationChannel::class;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toSystemNotification(User $notifiable): array
    {
        // Texto por clave de idioma y no incrustado, como en
        // `PersonalFreeleechCreated`. Aviso: esta notificacion es ShouldQueue, y
        // el worker no pasa por SetLanguage, asi que hoy se compone con
        // `config('app.locale')` para todo el mundo. Para que respete el idioma
        // de cada usuario haria falta que `User` implementase
        // `HasLocalePreference` devolviendo `settings->locale`; mientras no lo
        // haga, las claves en `en` estan puestas pero no se llegan a usar.
        return [
            'subject' => __('notification.donation-expired-subject'),
            'message' => __('notification.donation-expired-message'),
        ];
    }
}
