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

use App\Enums\ModerationStatus;
use App\Models\TorrentModeration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recupera el historial de moderación que ya existía, disperso en mensajes.
 *
 * El motivo de cada rechazo o aplazamiento nunca se guardó en el torrent, pero
 * NO se ha perdido: está dentro del mensaje privado que se le mandó al
 * uploader, y ese mensaje tiene una forma fija que se puede leer sin adivinar
 * nada.
 *
 *     Your upload, [url=/torrents/7870]nombre[/url], has been postponed. …
 *
 *     [quote=NoSoyAni]Faltan imágenes, falta mediainfo, mal nombrado[/quote]
 *
 * De ahí salen las cuatro cosas que hacen falta, todas exactas y ninguna
 * inferida: el id del torrent viene en el `[url]`, el motivo entre `[quote]`,
 * el moderador es el remitente del mensaje y la fecha es la del propio mensaje.
 *
 * Es idempotente: no reescribe una decisión ya registrada.
 */
class BackfillTorrentModerations extends Command
{
    protected $signature = 'torrents:backfill-moderations
                            {--dry-run : Sólo dice qué recuperaría}';

    protected $description = 'Recupera de los mensajes privados el motivo de los rechazos y aplazamientos ya hechos';

    final public function handle(): int
    {
        $seco = (bool) $this->option('dry-run');

        $mensajes = DB::table('private_messages')
            ->join('conversations', 'conversations.id', '=', 'private_messages.conversation_id')
            ->where(function ($q): void {
                $q->where('conversations.subject', 'LIKE', '%has been rejected%')
                    ->orWhere('conversations.subject', 'LIKE', '%has been postponed%');
            })
            ->orderBy('private_messages.id')
            ->get([
                'private_messages.id',
                'private_messages.conversation_id',
                'private_messages.sender_id',
                'private_messages.message',
                'private_messages.created_at',
                'conversations.subject',
            ]);

        $this->info(sprintf('%d mensaje(s) de moderación por revisar.', $mensajes->count()));

        // Se leen de una vez los torrents y las decisiones ya registradas: una
        // consulta por mensaje serían cientos de viajes a la base para nada.
        $existentes = TorrentModeration::query()
            ->select(['torrent_id', 'status', 'created_at'])
            ->get()
            ->map(fn ($m) => $m->torrent_id.':'.$m->status->value.':'.$m->created_at?->format('Y-m-d H:i:s'))
            ->flip();

        $recuperados = 0;
        $yaEstaban = 0;
        $sinMotivo = 0;
        $sinTorrent = 0;

        $idsValidos = DB::table('torrents')->pluck('id')->flip();

        foreach ($mensajes as $m) {
            if (!preg_match('#\[url=/torrents/(\d+)\]#i', (string) $m->message, $u)) {
                $sinMotivo++;

                continue;
            }

            $torrentId = (int) $u[1];

            if (!isset($idsValidos[$torrentId])) {
                // El torrent se borró después de moderarlo. La decisión ya no
                // tiene a qué colgarse.
                $sinTorrent++;

                continue;
            }

            if (!preg_match('#\[quote=[^\]]*\](.*?)\[/quote\]#is', (string) $m->message, $q)) {
                $sinMotivo++;

                continue;
            }

            $motivo = trim($q[1]);

            $status = str_contains(strtolower((string) $m->subject), 'rejected')
                ? ModerationStatus::REJECTED
                : ModerationStatus::POSTPONED;

            $clave = $torrentId.':'.$status->value.':'.$m->created_at;

            if (isset($existentes[$clave])) {
                $yaEstaban++;

                continue;
            }

            if (!$seco) {
                TorrentModeration::create([
                    'torrent_id'      => $torrentId,
                    'user_id'         => $m->sender_id,
                    'conversation_id' => $m->conversation_id,
                    'status'          => $status,
                    'message'         => $motivo,
                    'created_at'      => $m->created_at,
                    'updated_at'      => $m->created_at,
                ]);
            }

            $existentes[$clave] = true;
            $recuperados++;
        }

        $this->info(sprintf(
            '%s%d decisión(es) recuperada(s). %d ya estaban, %d sin motivo legible, %d de torrents borrados.',
            $seco ? '[simulacro] ' : '',
            $recuperados,
            $yaEstaban,
            $sinMotivo,
            $sinTorrent,
        ));

        return self::SUCCESS;
    }
}
