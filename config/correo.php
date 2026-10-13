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

return [
    /*
    |--------------------------------------------------------------------------
    | Cuota diaria de correo
    |--------------------------------------------------------------------------
    |
    | El tracker manda por una cuenta de Gmail, que corta el envío si se pasa
    | del cupo diario. Se cuenta TODO lo que sale (App\Services\CuotaCorreo,
    | por el evento MessageSent), pero solo se frena el correo de campañas
    | (mensajes de H&R, baneos): si no cabe, sale solo al día siguiente.
    |
    | «reserva» es lo que se deja libre para el correo del sistema
    | (verificación, contraseña), que nunca se frena. El día va en UTC; Gmail
    | cuenta 24 h móviles, y la reserva cubre la diferencia.
    |
    */

    'cuota_diaria' => (int) env('MAIL_CUOTA_DIARIA', 150),

    'reserva_sistema' => (int) env('MAIL_CUOTA_RESERVA', 30),
];
