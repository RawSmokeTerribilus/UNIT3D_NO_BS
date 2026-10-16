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
 * @author     HDVinnie <hdinnovations@protonmail.com>
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

use App\Models\TorrentRequestClaim;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewRequestUnclaim extends Notification implements ShouldQueue
{
    use Queueable;

    // NOBS: el original guardaba el claim y lo releía en el worker, pero
    // ClaimController lo borra justo después de encolar el aviso: el job
    // fallaba siempre (ModelNotFoundException) y el que pidió no se enteraba.
    // Se copian aquí los datos que hacen falta, en el momento de liberar.
    public int $claimerId;

    public ?int $claimerGroupId;

    public int $requestId;

    public string $requestName;

    /**
     * NewRequestUnclaim Constructor.
     */
    public function __construct(public string $type, public string $sender, TorrentRequestClaim $torrentRequestClaim)
    {
        $this->claimerId = $torrentRequestClaim->user_id;
        $this->claimerGroupId = $torrentRequestClaim->user?->group_id;
        $this->requestId = $torrentRequestClaim->request_id;
        $this->requestName = (string) $torrentRequestClaim->request?->name;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Determine if the notification should be sent.
     */
    public function shouldSend(User $notifiable): bool
    {
        // Do not notify self
        if ($this->claimerId === $notifiable->id) {
            return false;
        }

        if ($notifiable->notification?->block_notifications === 1) {
            return false;
        }

        if ($notifiable->notification?->show_request_unclaim === 0) {
            return false;
        }

        // If the sender's group ID is found in the "Block all notifications from the selected groups" array,
        // the expression will return false.
        return ! \in_array($this->claimerGroupId, $notifiable->notification?->json_request_groups ?? [], true);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->sender.' Has Unclaimed One Of Your Requested Torrents',
            'body'  => $this->sender.' has unclaimed your Requested Torrent '.$this->requestName,
            'url'   => \sprintf('/requests/%s', $this->requestId),
        ];
    }
}
