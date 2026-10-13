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

namespace App\Jobs\Middleware;

use App\Services\CuotaCorreo;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Middleware para el correo de campañas: si hoy ya no cabe en la cuota
 * (config/correo.php), el job se reencola para pasada la medianoche UTC, con
 * algo de margen aleatorio para que no salgan todos en el mismo segundo.
 * Mientras espera no ocupa al worker: queda en la cola de retrasados de Redis.
 *
 * Los mailables y notificaciones que lo usan llevan retryUntil() de varios
 * dias, porque el worker corre con --tries=1 y cada espera cuenta un intento.
 */
final class CuotaDeCorreo
{
    public function handle(object $job, Closure $next): void
    {
        if (CuotaCorreo::libreParaCampanas() > 0) {
            $next($job);

            return;
        }

        $espera = CuotaCorreo::segundosHastaManana() + random_int(60, 3600);

        Log::info('Correo de campaña aplazado por la cuota diaria', [
            'job'      => $job::class,
            'enviados' => CuotaCorreo::enviadosHoy(),
            'espera_s' => $espera,
        ]);

        $job->release($espera);
    }
}
