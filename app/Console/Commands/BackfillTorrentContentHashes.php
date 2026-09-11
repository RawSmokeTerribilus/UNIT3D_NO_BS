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

use App\Helpers\Bencode;
use App\Helpers\TorrentTools;
use App\Models\Torrent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Rellena `torrents.content_hash` en lo que ya estaba subido.
 *
 * El info_hash nunca sirvió para decir "esto ya está subido": normalizeTorrent()
 * le mete 64 bytes de entropía a cada .torrent que entra, así que dos subidas
 * del mismo material salen con info_hash distintos por diseño. La huella se
 * calcula sobre la lista de ficheros -- rutas relativas y longitudes -- que es
 * lo único que sobrevive a regenerar el torrent.
 *
 * El dato se saca del .torrent guardado, no de la fila: la fila sólo tiene
 * tamaño total y número de ficheros, y eso no distingue dos discos distintos
 * del mismo peso.
 *
 * Es idempotente: sólo toca las filas que aún no tienen huella, salvo --force.
 */
class BackfillTorrentContentHashes extends Command
{
    protected $signature = 'torrents:backfill-content-hashes
                            {--dry-run : Sólo dice qué escribiría}
                            {--force : Recalcula también las que ya tienen huella}
                            {--chunk=200 : Filas por lote}';

    protected $description = 'Calcula la huella de contenido de los torrents ya subidos, leyendo su .torrent';

    final public function handle(): int
    {
        $seco = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $lote = max(1, (int) $this->option('chunk'));

        // withoutGlobalScopes(): sin esto se dejaba fuera lo pendiente y lo
        // rechazado por el ApprovedScope, y esas filas también tienen que tener
        // huella -- la comprobación del formulario web mira justo ahí.
        $query = Torrent::withoutGlobalScopes()->select(['id', 'file_name', 'content_hash']);

        if (!$force) {
            $query->whereNull('content_hash');
        }

        $total = (clone $query)->count();
        $this->info(sprintf('%d torrent(s) por procesar.', $total));

        if ($total === 0) {
            return Command::SUCCESS;
        }

        $barra = $this->output->createProgressBar($total);
        $barra->start();

        $escritos = 0;
        $sinFichero = 0;
        $ilegibles = [];

        $query->orderBy('id')->chunkById($lote, function ($torrents) use ($seco, $barra, &$escritos, &$sinFichero, &$ilegibles): void {
            foreach ($torrents as $torrent) {
                $barra->advance();

                if (!Storage::disk('torrent-files')->exists($torrent->file_name)) {
                    $sinFichero++;

                    continue;
                }

                try {
                    $decoded = Bencode::bdecode_file(Storage::disk('torrent-files')->path($torrent->file_name));
                    $huella = TorrentTools::contentHash($decoded);
                } catch (Throwable $e) {
                    $ilegibles[] = $torrent->id.': '.$e->getMessage();

                    continue;
                }

                if ($torrent->content_hash === $huella) {
                    continue;
                }

                $escritos++;

                if (!$seco) {
                    // Update directo: no hay que despertar observers ni tocar
                    // updated_at por rellenar un dato que ya era verdad.
                    DB::table('torrents')->where('id', '=', $torrent->id)->update(['content_hash' => $huella]);
                }
            }
        });

        $barra->finish();
        $this->newLine(2);

        $this->info(sprintf('%s %d huella(s).', $seco ? 'Escribiría' : 'Escritas', $escritos));

        if ($sinFichero > 0) {
            $this->warn(sprintf('%d torrent(s) sin .torrent en disco: se quedan sin huella.', $sinFichero));
        }

        if ($ilegibles !== []) {
            $this->warn(sprintf('%d .torrent ilegible(s):', \count($ilegibles)));

            foreach (\array_slice($ilegibles, 0, 20) as $linea) {
                $this->line('  '.$linea);
            }
        }

        return Command::SUCCESS;
    }
}
