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

namespace App\Console\Commands;

use App\Models\Torrent;
use Exception;
use Illuminate\Console\Command;
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;

class AutoSyncTorrentsToMeilisearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:sync_torrents_to_meilisearch
        {--wipe : Vacía el índice y lo rehace entero (el buscador se queda sin resultados mientras)}
        {--all : Reindexa todos los torrents sin vaciar el índice y quita los que ya no existen}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs torrents and their relations to meilisearch';

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): void
    {
        $start = now();

        // Los modos completos se lanzan desde el panel, dentro de una petición
        // web con 30 s de límite en fpm; en prod rozan los 15 s y crecen con el
        // catálogo. nginx espera 300 s: así el botón termina y enseña la salida.
        if ($this->option('wipe') || $this->option('all')) {
            set_time_limit(300);
        }

        $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));
        $index = $client->getIndex(config('scout.prefix').'torrents');

        $index->updatePagination([
            'maxTotalHits' => max(1, Torrent::query()->count()) + 1000,
        ]);

        if ($this->option('wipe')) {
            Torrent::removeAllFromSearch();
            $count = Torrent::query()->count();
            Torrent::query()->selectRaw(Torrent::SEARCHABLE)->searchable();

            $this->comment("Índice vaciado y rehecho: {$count} torrents enviados en ".$this->seconds($start).' s.');

            return;
        }

        if ($this->option('all')) {
            $count = Torrent::query()->count();
            Torrent::query()->selectRaw(Torrent::SEARCHABLE)->searchable();

            // Lo que está en el índice y ya no es un torrent buscable (borrado,
            // rechazado…): sin vaciar no se iría nunca.
            $live = array_flip(Torrent::query()->pluck('id')->all());
            $ghosts = [];

            for ($offset = 0; ; $offset += 1000) {
                $page = $index->getDocuments((new DocumentsQuery())->setFields(['id'])->setLimit(1000)->setOffset($offset));

                foreach ($page->getResults() as $document) {
                    if (!isset($live[$document['id']])) {
                        $ghosts[] = $document['id'];
                    }
                }

                if (\count($page->getResults()) < 1000) {
                    break;
                }
            }

            if ($ghosts !== []) {
                $index->deleteDocuments($ghosts);
            }

            $this->comment("Reindexado completo sin vaciar: {$count} torrents enviados y ".\count($ghosts).' que ya no existen quitados, en '.$this->seconds($start).' s.');

            return;
        }

        // Reindex torrents that were updated since the start of the most
        // recently finished 15-minute period (since this cron job runs
        // every 15 minutes, but might be delayed by a few seconds/minutes
        // each time)
        $since = now()->startOfHour()->addMinutes(15 * (intdiv((int) now()->diffInMinutes(now()->startOfHour(), true), 15) - 1));

        $query = Torrent::query()->where('updated_at', '>', $since);
        $count = (clone $query)->count();
        $query->selectRaw(Torrent::SEARCHABLE)->searchable();

        // Antes decía «Synced all torrents» aunque solo mandaba los cambiados.
        $this->comment("Sincronizados {$count} torrents cambiados desde las {$since->format('H:i')} UTC, en ".$this->seconds($start).' s.');
    }

    private function seconds(\Illuminate\Support\Carbon $start): string
    {
        return number_format(now()->diffInMilliseconds($start, true) / 1000, 1, ',', '');
    }
}
