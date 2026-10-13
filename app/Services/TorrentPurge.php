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

namespace App\Services;

use App\Models\Torrent;
use App\Models\User;
use App\Notifications\TorrentDeleted;
use Illuminate\Support\Facades\Notification;

/**
 * Borrar un torrent con el MISMO ritual que el boton de la web
 * (`TorrentController@destroy`): aviso a quien lo tuvo, peers, historial,
 * avisos, ficheros, announce de Rust. Lo usan torrents:purge y el borrado de
 * torrents muertos.
 *
 * Dos pasos mas que la web. El aviso llega tambien al que lo subio aunque no
 * tenga historial. Y el ritual borra los avisos de H&R del torrent, y
 * quien quedo bloqueado por ellos no recuperaba la descarga si se quedaba sin
 * filas de aviso visibles (ver HitRunDownload). Nadie se queda sancionado por
 * un torrent que ya no existe.
 */
final class TorrentPurge
{
    /**
     * @return array{rehabilitados: list<int>} ids de socios a los que se devolvio la descarga
     */
    public static function purge(Torrent $torrent, string $motivo): array
    {
        $avisados = $torrent->warnings()
            ->where('active', '=', true)
            ->pluck('user_id')
            ->unique()
            ->all();

        // Como la web, a quien tiene historial; y ademas al que lo subio, que
        // si nunca llego a sembrarlo no tiene fila y se quedaba sin enterarse.
        Notification::send(
            User::query()
                ->where(fn ($query) => $query
                    ->whereHas('history', fn ($query) => $query->where('torrent_id', '=', $torrent->id))
                    ->orWhere('id', '=', $torrent->user_id))
                ->where('id', '!=', User::SYSTEM_USER_ID)
                ->get(),
            new TorrentDeleted($torrent, $motivo),
        );

        $torrent->requests()->whereNull('approved_when')->update(['torrent_id' => null]);

        cache()->forget(\sprintf('torrent:%s', $torrent->info_hash));

        $torrent->comments()->delete();
        $torrent->peers()->delete();
        $torrent->history()->delete();
        $torrent->warnings()->delete();
        $torrent->files()->delete();
        $torrent->playlists()->detach();
        $torrent->subtitles()->delete();
        $torrent->resurrections()->delete();
        $torrent->featured()->delete();

        $freeleechTokens = $torrent->freeleechTokens();

        foreach ($freeleechTokens->get() as $freeleechToken) {
            cache()->forget('freeleech_token:'.$freeleechToken->user_id.':'.$torrent->id);
        }

        $freeleechTokens->delete();

        cache()->forget('announce-torrents:by-infohash:'.$torrent->info_hash);

        Unit3dAnnounce::removeTorrent($torrent);

        $torrent->delete();

        $rehabilitados = [];

        foreach (User::query()->whereIn('id', $avisados)->get() as $user) {
            if (HitRunDownload::restoreIfEarned($user)) {
                $rehabilitados[] = $user->id;
            }
        }

        return ['rehabilitados' => $rehabilitados];
    }
}
