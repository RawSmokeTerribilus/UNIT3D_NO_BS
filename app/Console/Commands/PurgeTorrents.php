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

namespace App\Console\Commands;

use App\Models\Scopes\ApprovedScope;
use App\Models\Torrent;
use App\Models\User;
use App\Notifications\TorrentDeleted;
use App\Services\Unit3dAnnounce;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Borra torrents por id con el MISMO ritual que el boton de la web.
 *
 * Un `UPDATE torrents SET deleted_at=NOW()` a mano deja el torrent vivo en el
 * announce de Rust, deja peers, historial y avisos colgando, y no avisa a quien
 * se lo habia bajado. Este comando repite paso por paso lo que hace
 * `TorrentController@destroy`, para limpiezas en lote (duplicados) donde ir uno
 * a uno por la interfaz son decenas de clics.
 *
 * El motivo es obligatorio: es lo que le llega al que se lo descargo.
 */
class PurgeTorrents extends Command
{
    protected $signature = 'torrents:purge
                            {--ids= : Lista de ids separados por coma}
                            {--reason= : Motivo que se envia a quien lo tenga}
                            {--dry-run : Sólo dice qué borraría}';

    protected $description = 'Borra torrents por id con el mismo ritual que el borrado de la web (peers, historial, announce, aviso)';

    final public function handle(): int
    {
        $ids = array_values(array_filter(array_map(
            static fn ($x) => (int) trim($x),
            explode(',', (string) $this->option('ids'))
        )));

        $motivo = trim((string) $this->option('reason'));
        $seco = (bool) $this->option('dry-run');

        if ($ids === []) {
            $this->error('Sin --ids no hay nada que borrar.');

            return Command::FAILURE;
        }

        if ($motivo === '') {
            $this->error('--reason es obligatorio: es el mensaje que recibe quien se lo descargó.');

            return Command::FAILURE;
        }

        $torrents = Torrent::withoutGlobalScope(ApprovedScope::class)
            ->whereIn('id', $ids)
            ->get();

        $faltan = array_diff($ids, $torrents->pluck('id')->all());

        if ($faltan !== []) {
            $this->warn('No existen o ya estaban borrados: '.implode(', ', $faltan));
        }

        foreach ($torrents as $torrent) {
            $this->line(sprintf('%s #%d — %s', $seco ? 'borraría' : 'borrando', $torrent->id, $torrent->name));

            if ($seco) {
                continue;
            }

            Notification::send(
                User::query()->whereHas('history', fn ($query) => $query->where('torrent_id', '=', $torrent->id))->get(),
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
        }

        $this->newLine();
        $this->info(sprintf('%s %d torrent(s).', $seco ? 'Borraría' : 'Borrados', $seco ? $torrents->count() : $torrents->count()));

        return Command::SUCCESS;
    }
}
