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
use App\Models\Warning;
use App\Notifications\Channels\SystemNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WarningDeactivated extends Notification implements ShouldQueue, SystemNotificationInterface
{
    use Queueable;

    public function __construct(public User $staff, public Warning $warning)
    {
    }

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
        // NOBS: el original metía en el texto el modelo Torrent entero; al pasarlo
        // a JSON el info_hash binario reventaba (Malformed UTF-8) y el aviso no
        // llegaba nunca. Solo el nombre, y aunque el torrent ya esté borrado.
        $torrent = $this->warning->torrent()->withTrashed()->value('name')
            ?? ($this->warning->torrent_id !== null ? "#{$this->warning->torrent_id}" : '—');

        return [
            'subject' => 'Hit and Run Warning Deactivated',
            'message' => "{$this->staff->username} has decided to deactivate your warning for torrent {$torrent}. You lucked out!",
        ];
    }
}
