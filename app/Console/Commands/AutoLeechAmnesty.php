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

use App\Services\LeechAmnesty;
use App\Services\TrackerPromos;
use Illuminate\Console\Command;

class AutoLeechAmnesty extends Command
{
    protected $signature = 'auto:leech-amnesty';

    protected $description = 'Aplica o revierte la amnistia de descarga de Sanguijuela segun el freeleech global';

    final public function handle(): int
    {
        $r = LeechAmnesty::sync();

        // Reconciliacion de las promos globales (freeleech y double upload). El
        // .env del announce es cache derivada de `settings`, y aqui es donde
        // converge si el empujon del panel fallo o si alguien edito el fichero a
        // mano. Idempotente: no escribe ni recarga nada si ya estan correctas.
        $promos = TrackerPromos::sync();

        if ($promos['changed']) {
            foreach ($promos['factors'] as $variable => $cambio) {
                $this->warn(sprintf(
                    'Promo global desincronizada: %s %s -> %d%s',
                    $variable,
                    $cambio['from'] ?? 'ilegible',
                    $cambio['to'],
                    $promos['reloaded'] ? ' (announce recargado)' : ' (¡el announce NO recargo!)',
                ));
            }
        }

        $estado = $r['active'] ? 'ACTIVA' : 'inactiva';

        $this->info(sprintf(
            'Amnistia %s — slots=%d%s | concedidas=%d | revocadas=%d | omitidas por H&R=%d',
            $estado,
            $r['slots'],
            $r['slots_changed'] ? ' (cambiados)' : '',
            $r['granted'],
            $r['revoked'],
            $r['skipped_hitrun'],
        ));

        if ($r['announce_failures'] > 0) {
            // El announce Rust falla en silencio: addUser/addGroup devuelven bool
            // y nadie los mira. Si no se entera, el usuario sigue rechazado en el
            // tracker aunque la web diga que puede descargar.
            $this->error('Fallos al empujar al announce: '.$r['announce_failures'].' — revisa el contenedor announce');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
