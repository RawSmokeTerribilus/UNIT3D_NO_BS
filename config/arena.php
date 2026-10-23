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

/**
 * Arena — juegos multijugador entre socios, como módulos enchufables.
 *
 * Cada módulo es un stack propio fuera de UNIT3D (~/Arena/<slug>/) que cuelga de
 * /arena-app/<slug>/ detrás del gateway de Arena. UNIT3D solo pone la página con el
 * iframe y la puerta (auth_request a /__arena/whoami). Añadir o quitar un juego =
 * una entrada aquí + su bloque en el gateway; nada más en el tracker.
 *
 * 'pase_horas' es lo que dura el pase que deja en sesión la página /arena/<slug>
 * (que sí pasa por todo el grupo auth: baneo, verificado, 2FA/Telegram, aviso H&R).
 * Sin pase vigente, nginx no deja llegar al módulo.
 */
return [
    // Interruptor general: lo manda la fila arena.is_enabled de `settings` (/dashboard/config).
    // Este valor solo es el de por defecto y va en false para que portar o instalar no abra nada.
    'is_enabled' => false,

    'pase_horas' => 12,

    // Token compartido con los agentes de control de los módulos (red interna arena)
    'control_token' => env('ARENA_CONTROL_TOKEN'),

    'modulos' => [
        'wz2100' => [
            'nombre'      => 'Warzone 2100',
            'descripcion' => 'Estrategia en tiempo real, entre socios y en el navegador.',
            'icono'       => 'fa-chess-rook',
            'activo'      => true,
            'control_url' => 'http://arena-wz2100-salas:8081',
        ],
    ],
];
