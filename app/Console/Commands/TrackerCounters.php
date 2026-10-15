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

use App\Services\Unit3dAnnounce;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Comprobador de contadores de torrents, y reset de cada parte por separado.
 *
 * - Pares: `torrents.seeders/leechers` frente a la tabla `peers` y a la memoria
 *   del announce Rust. Quien manda es el announce: el reset reescribe la
 *   columna desde su memoria (al revés que `auto:sync_peers`, que la rehace
 *   desde `peers`).
 * - Balance: `torrents.balance` frente al tráfico de `history` (todas las filas,
 *   también las de soft delete, como `AutoTorrentBalance`). La memoria del
 *   announce no guarda balance: sólo manda deltas.
 *
 * Los resets no escriben valores absolutos: el announce suma deltas a esas
 * columnas cada pocos segundos y un SET absoluto podría pisar uno en vuelo.
 * Se lee dos veces con una pausa mayor que su intervalo de volcado, sólo se
 * tocan los torrents cuyo desfase es idéntico en las dos lecturas, y se aplica
 * la corrección como suma (`seeders = seeders + diff`). Antes de escribir se
 * guarda una copia de las filas tocadas en storage/app/tracker-counters/.
 */
class TrackerCounters extends Command
{
    protected $signature = 'tracker:counters
        {--fix-peers : Reescribe seeders/leechers desde la memoria del announce}
        {--fix-balance : Reescribe balance desde history}';

    protected $description = 'Comprueba seeders/leechers (columna, peers, announce) y balance (columna, history); con --fix-* corrige cada parte';

    private const EXAMPLES = 8;

    private const SETTLE_SECONDS = 5;

    final public function handle(): int
    {
        // Desde el panel corre dentro de la petición web (límite de 30 s).
        set_time_limit(300);

        $fixPeers = (bool) $this->option('fix-peers');
        $fixBalance = (bool) $this->option('fix-balance');

        $first = $this->snapshot();

        if ($first === null) {
            $this->error('No se ha podido leer la memoria del announce. No se ha tocado nada.');

            return self::FAILURE;
        }

        $this->report($first);

        if (!$fixPeers && !$fixBalance) {
            return self::SUCCESS;
        }

        $peerIds = array_keys(array_filter($first, fn ($row) => $row['mem'] !== null && $row['col_s'].'/'.$row['col_l'] !== $row['mem_s'].'/'.$row['mem_l']));
        $balanceIds = array_keys(array_filter($first, fn ($row) => $row['col_bal'] !== $row['hist_bal']));

        if (($fixPeers && $peerIds !== []) || ($fixBalance && $balanceIds !== [])) {
            $this->line('');
            $this->line('Segunda lectura en '.self::SETTLE_SECONDS.' s para no pisar deltas del announce en vuelo…');
            sleep(self::SETTLE_SECONDS);
            $second = $this->snapshot(array_values(array_unique(array_merge($fixPeers ? $peerIds : [], $fixBalance ? $balanceIds : []))));

            if ($second === null) {
                $this->error('La segunda lectura del announce ha fallado. No se ha tocado nada.');

                return self::FAILURE;
            }

            if ($fixPeers) {
                $this->fixPeers($first, $second, $peerIds);
            }

            if ($fixBalance) {
                $this->fixBalance($first, $second, $balanceIds);
            }
        } else {
            $this->line('');
            $this->line('Nada que corregir.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int>|null                               $onlyIds
     * @return array<int, array<string, int|bool|null>>|null
     */
    private function snapshot(?array $onlyIds = null): ?array
    {
        $torrents = DB::table('torrents')
            ->whereNull('deleted_at')
            ->when($onlyIds !== null, fn ($query) => $query->whereIn('id', $onlyIds))
            ->get(['id', 'seeders', 'leechers', 'balance']);

        $ids = $torrents->pluck('id')->all();

        $peers = DB::table('peers')
            ->when($onlyIds !== null, fn ($query) => $query->whereIn('torrent_id', $ids))
            ->selectRaw('torrent_id, SUM(`left` = 0 AND active = 1 AND visible = 1) AS s, SUM(`left` != 0 AND active = 1 AND visible = 1) AS l')
            ->groupBy('torrent_id')
            ->get()
            ->keyBy('torrent_id');

        $history = DB::table('history')
            ->when($onlyIds !== null, fn ($query) => $query->whereIn('torrent_id', $ids))
            ->selectRaw('torrent_id, SUM(actual_uploaded) - SUM(actual_downloaded) AS bal')
            ->groupBy('torrent_id')
            ->pluck('bal', 'torrent_id');

        $memory = [];

        if (config('announce.external_tracker.is_enabled')) {
            $memory = Unit3dAnnounce::getTorrentCounters($ids);

            if ($memory === false) {
                return null;
            }
        }

        $rows = [];

        foreach ($torrents as $torrent) {
            $mem = $memory[$torrent->id] ?? null;
            $rows[$torrent->id] = [
                'col_s'    => (int) $torrent->seeders,
                'col_l'    => (int) $torrent->leechers,
                'db_s'     => (int) ($peers[$torrent->id]->s ?? 0),
                'db_l'     => (int) ($peers[$torrent->id]->l ?? 0),
                'mem'      => $mem !== null,
                'mem_s'    => $mem['seeders'] ?? null,
                'mem_l'    => $mem['leechers'] ?? null,
                'col_bal'  => (int) $torrent->balance,
                'hist_bal' => (int) ($history[$torrent->id] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, int|bool|null>> $rows
     */
    private function report(array $rows): void
    {
        $inMemory = array_filter($rows, fn ($row) => $row['mem']);
        $colVsMem = array_filter($inMemory, fn ($row) => $row['col_s'] !== $row['mem_s'] || $row['col_l'] !== $row['mem_l']);
        $dbVsMem = array_filter($inMemory, fn ($row) => $row['db_s'] !== $row['mem_s'] || $row['db_l'] !== $row['mem_l']);
        $colVsDb = array_filter($rows, fn ($row) => $row['col_s'] !== $row['db_s'] || $row['col_l'] !== $row['db_l']);
        $balance = array_filter($rows, fn ($row) => $row['col_bal'] !== $row['hist_bal']);
        $balanceGib = array_sum(array_map(fn ($row) => abs($row['col_bal'] - $row['hist_bal']), $balance)) / 1024 ** 3;

        $this->line(\sprintf('Torrents: %d (%d en la memoria del announce)', \count($rows), \count($inMemory)));
        $this->line('');
        $this->line('PARES (seeders/leechers)');
        $this->line(\sprintf('  columna ≠ announce: %d', \count($colVsMem)));
        $this->line(\sprintf('  peers   ≠ announce: %d', \count($dbVsMem)));
        $this->line(\sprintf('  columna ≠ peers:    %d', \count($colVsDb)));

        foreach (\array_slice($colVsMem, 0, self::EXAMPLES, true) as $id => $row) {
            $this->line(\sprintf('    t%d  columna %d/%d · peers %d/%d · announce %d/%d', $id, $row['col_s'], $row['col_l'], $row['db_s'], $row['db_l'], $row['mem_s'], $row['mem_l']));
        }

        $this->line('');
        $this->line('BALANCE');
        $this->line(\sprintf('  columna ≠ history: %d (desvío total %.1f GiB)', \count($balance), $balanceGib));

        foreach (\array_slice($balance, 0, self::EXAMPLES, true) as $id => $row) {
            $this->line(\sprintf('    t%d  columna %.2f GiB · history %.2f GiB', $id, $row['col_bal'] / 1024 ** 3, $row['hist_bal'] / 1024 ** 3));
        }
    }

    /**
     * @param array<int, array<string, int|bool|null>> $first
     * @param array<int, array<string, int|bool|null>> $second
     * @param array<int>                               $ids
     */
    private function fixPeers(array $first, array $second, array $ids): void
    {
        $changes = [];
        $unstable = 0;

        foreach ($ids as $id) {
            $a = $first[$id];
            $b = $second[$id] ?? null;

            if ($b === null || !$b['mem']) {
                $unstable++;

                continue;
            }

            $diffS = $a['mem_s'] - $a['col_s'];
            $diffL = $a['mem_l'] - $a['col_l'];

            if ($diffS !== $b['mem_s'] - $b['col_s'] || $diffL !== $b['mem_l'] - $b['col_l']) {
                $unstable++;

                continue;
            }

            if ($diffS !== 0 || $diffL !== 0) {
                $changes[$id] = ['seeders' => $b['col_s'], 'leechers' => $b['col_l'], 'diff_s' => $diffS, 'diff_l' => $diffL];
            }
        }

        $this->line('');
        $this->line('RESET DE PARES desde el announce');

        if ($changes === []) {
            $this->line(\sprintf('  Nada que aplicar (%d con desfase cambiante, se dejan).', $unstable));

            return;
        }

        $backup = $this->backup('pares', ['torrent_id', 'seeders_antes', 'leechers_antes', 'diff_seeders', 'diff_leechers'], array_map(
            fn ($id, $change) => [$id, $change['seeders'], $change['leechers'], $change['diff_s'], $change['diff_l']],
            array_keys($changes),
            $changes
        ));

        DB::transaction(function () use ($changes): void {
            foreach ($changes as $id => $change) {
                DB::table('torrents')->where('id', '=', $id)->update([
                    'seeders'    => DB::raw('seeders + '.(int) $change['diff_s']),
                    'leechers'   => DB::raw('leechers + '.(int) $change['diff_l']),
                    'updated_at' => DB::raw('updated_at'),
                ]);
            }
        });

        $this->line(\sprintf('  Corregidos: %d · con desfase cambiante (no tocados): %d', \count($changes), $unstable));
        $this->line('  Copia previa: '.$backup);
    }

    /**
     * @param array<int, array<string, int|bool|null>> $first
     * @param array<int, array<string, int|bool|null>> $second
     * @param array<int>                               $ids
     */
    private function fixBalance(array $first, array $second, array $ids): void
    {
        $changes = [];
        $unstable = 0;

        foreach ($ids as $id) {
            $b = $second[$id] ?? null;

            if ($b === null) {
                $unstable++;

                continue;
            }

            $diff = $first[$id]['hist_bal'] - $first[$id]['col_bal'];

            if ($diff !== $b['hist_bal'] - $b['col_bal']) {
                $unstable++;

                continue;
            }

            if ($diff !== 0) {
                $changes[$id] = ['balance' => $b['col_bal'], 'diff' => $diff];
            }
        }

        $this->line('');
        $this->line('RESET DE BALANCE desde history');

        if ($changes === []) {
            $this->line(\sprintf('  Nada que aplicar (%d con desfase cambiante, se dejan).', $unstable));

            return;
        }

        $backup = $this->backup('balance', ['torrent_id', 'balance_antes', 'diff'], array_map(
            fn ($id, $change) => [$id, $change['balance'], $change['diff']],
            array_keys($changes),
            $changes
        ));

        DB::transaction(function () use ($changes): void {
            foreach ($changes as $id => $change) {
                DB::table('torrents')->where('id', '=', $id)->update([
                    'balance'    => DB::raw('balance + '.(int) $change['diff']),
                    'updated_at' => DB::raw('updated_at'),
                ]);
            }
        });

        $this->line(\sprintf('  Corregidos: %d · con desfase cambiante (no tocados): %d', \count($changes), $unstable));
        $this->line('  Copia previa: '.$backup);
    }

    /**
     * Copia CSV de las filas que se van a tocar, antes de tocarlas.
     *
     * @param array<string>            $header
     * @param array<array<int|string>> $rows
     */
    private function backup(string $what, array $header, array $rows): string
    {
        $path = 'tracker-counters/'.now()->format('Y-m-d_His').'-'.$what.'.csv';
        $lines = ['# tracker:counters --fix-'.($what === 'pares' ? 'peers' : 'balance').' · '.now()->toDateTimeString().' UTC · valores ANTES del cambio', implode(',', $header)];

        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        Storage::disk('local')->put($path, implode("\n", $lines)."\n");

        return 'storage/app/'.$path;
    }
}
