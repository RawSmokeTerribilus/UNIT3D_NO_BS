<?php

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
                // Libro, audiolibro y juego van en la carga porque el
                // anuncio los lee: sin ellos el job los ve a `null` y manda
                // una ficha vacía con la carátula de relleno.
                SendTelegramNotification::dispatch(
                    $torrent->load(['category', 'type', 'movie', 'tv', 'game', 'book', 'audiobook']),
                    $torrent->user,
                );
            }
        }
    }
}
