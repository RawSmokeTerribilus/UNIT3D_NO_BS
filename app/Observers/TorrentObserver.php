<?php

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

namespace App\Observers;

use App\Enums\ModerationStatus;
use App\Jobs\SendTelegramNotification;
use App\Models\Torrent;

class TorrentObserver
{
    public function updated(Torrent $torrent): void
    {
        if (
            $torrent->wasChanged('status')
            && $torrent->status === ModerationStatus::APPROVED
            && $torrent->getOriginal('status') !== ModerationStatus::APPROVED->value
        ) {
            if ($torrent->user) {
                SendTelegramNotification::dispatch(
                    $torrent->load(['category', 'type', 'movie', 'tv']),
                    $torrent->user,
                );
            }
        }
    }
}
