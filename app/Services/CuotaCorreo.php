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

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Contador de correos enviados hoy (UTC), compartido por todos los procesos
 * (php-fpm y los workers) a traves de la cache de Redis. Ver config/correo.php.
 */
final class CuotaCorreo
{
    public static function enviadosHoy(): int
    {
        return (int) Cache::get(self::clave(), 0);
    }

    /**
     * Lo que queda hoy para campañas, descontada la reserva del sistema.
     */
    public static function libreParaCampanas(): int
    {
        return max(0, (int) config('correo.cuota_diaria') - (int) config('correo.reserva_sistema') - self::enviadosHoy());
    }

    /**
     * Lo llama el evento MessageSent: un correo mas que ha salido.
     */
    public static function registrar(): void
    {
        Cache::add(self::clave(), 0, now()->addDays(2));
        Cache::increment(self::clave());
    }

    /**
     * Segundos hasta la proxima medianoche UTC, cuando se reinicia la cuenta.
     */
    public static function segundosHastaManana(): int
    {
        return (int) now('UTC')->diffInSeconds(Carbon::tomorrow('UTC'), true);
    }

    private static function clave(): string
    {
        return 'correo:enviados:'.now('UTC')->format('Y-m-d');
    }
}
