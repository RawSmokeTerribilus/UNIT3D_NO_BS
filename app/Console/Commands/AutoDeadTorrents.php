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

use App\Services\DeadTorrents;
use App\Services\PromoAnnouncer;
use Illuminate\Console\Command;

/**
 * Reloj de los torrents muertos (ver App\Services\DeadTorrents).
 *
 *   auto:dead-torrents          pasada: salva lo que volvio a tener semilla,
 *                               borra lo vencido y manda el resumen al grupo
 *                               de staff si algo cambio.
 *   auto:dead-torrents --list   solo lista los candidatos de ahora, sin tocar
 *                               nada (la misma lista que el panel).
 *
 * Pedir la resiembra NO lo hace este comando: es una decision del staff, desde
 * Staff → Comandos → Torrents muertos, con la lista delante.
 */
class AutoDeadTorrents extends Command
{
    protected $signature = 'auto:dead-torrents {--list : Solo lista los candidatos, no toca nada}';

    protected $description = 'Torrents muertos: cierra los salvados, borra los vencidos y resume al staff';

    final public function handle(): int
    {
        if ($this->option('list')) {
            $filas = DeadTorrents::candidates();

            $this->table(
                ['id', 'nombre', 'subidor', 'completaron', 'leechers', 'última semilla'],
                $filas->map(fn ($f) => [$f->id, mb_substr($f->name, 0, 60), $f->subidor, $f->completaron, $f->leechers, $f->ultima_semilla ?? 'nunca'])->all(),
            );
            $this->info(\sprintf('%d candidato(s) con plazo de %d h.', $filas->count(), DeadTorrents::hours()));

            return self::SUCCESS;
        }

        $r = DeadTorrents::sweep();

        $this->info(\sprintf(
            'Salvados %d · borrados %d · desaparecidos %d · errores %d',
            \count($r['salvados']),
            \count($r['borrados']),
            \count($r['desaparecidos']),
            \count($r['errores']),
        ));

        $texto = DeadTorrents::summary($r);

        if ($texto !== null && !PromoAnnouncer::staff($texto)) {
            $this->warn('No he podido mandar el resumen al grupo de staff (queda en el panel).');
        }

        return $r['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
