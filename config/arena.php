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
 * Cada módulo es un stack propio fuera de UNIT3D (arena/modulos/<slug>/ en el compose) que
 * cuelga de <origen de la Arena>/arena-app/<slug>/ detrás del gateway de Arena. El origen es
 * propio (arena.rawsmoke.net), así el código de los juegos no comparte origen con el tracker.
 * UNIT3D solo pone la página con el iframe, que lleva un pase firmado (App\Services\ArenaPase),
 * y la puerta sin sesión (/__arena/pase, routes/arena.php). Añadir o quitar un juego = una
 * entrada aquí + su bloque en el gateway; nada más en el tracker.
 *
 * 'pase_horas' es lo que dura la cookie del origen de la Arena que sale del pase de la página
 * /arena/<slug> (que sí pasa por todo el grupo auth: baneo, verificado, 2FA/Telegram, aviso H&R).
 * Sin pase vigente, nginx no deja llegar al módulo.
 */
return [
    // Interruptor general: lo manda la fila arena.is_enabled de `settings` (/dashboard/config).
    // Este valor solo es el de por defecto y va en false para que portar o instalar no abra nada.
    'is_enabled' => false,

    'pase_horas' => 12,

    // Token compartido con los agentes de control de los módulos (red interna arena)
    'control_token' => env('ARENA_CONTROL_TOKEN'),

    // Origen propio de la Arena (p. ej. https://arena.rawsmoke.net): el iframe de los juegos no
    // comparte origen con el tracker. Vacío = modo antiguo (mismo origen, /arena-app/ en NOBS).
    'origen' => env('ARENA_ORIGEN'),

    // Clave HMAC del pase que lleva la identidad del socio a ese origen (≥ 32 caracteres)
    'pase_secreto' => env('ARENA_PASE_SECRETO'),

    // Guía de usuario (wiki del tracker); vacío = la página de la Arena no enlaza ninguna
    'wiki_url' => env('ARENA_WIKI_URL'),

    'modulos' => [
        'wz2100' => [
            'nombre'      => 'Warzone 2100',
            'descripcion' => 'Estrategia en tiempo real, entre socios y en el navegador.',
            'icono'       => 'fa-chess-rook',
            // Ficha del Arcade: public/img/arcade/tarjeta-wz2100-{800,1600}.webp (fondo y logo oficiales de WZ, GPL)
            'imagen'      => '/img/arcade/tarjeta-wz2100',
            'detalle'     => 'Estrategia en tiempo real',
            'activo'      => true,
            'control_url' => 'http://arena-wz2100-salas:8081',
        ],
        'q3' => [
            'nombre'      => 'Quake 3 · OpenArena',
            'descripcion' => 'Shooter de arena clásico: todos contra todos, por equipos y bandera, con bots.',
            'icono'       => 'fa-crosshairs',
            'imagen'      => '/img/arcade/tarjeta-q3',
            'detalle'     => 'Shooter de arena',
            'activo'      => true,
            'control_url' => 'http://arena-q3-salas:8081',
        ],
        'cs16' => [
            'nombre'      => 'Counter-Strike 1.6',
            'descripcion' => 'El clásico táctico: terroristas contra antiterroristas, con bots que rellenan.',
            'icono'       => 'fa-person-rifle',
            'imagen'      => '/img/arcade/tarjeta-cs16',
            'detalle'     => 'Shooter táctico',
            'activo'      => true,
            'control_url' => 'http://arena-cs16-salas:8081',
        ],
        'stk' => [
            'nombre'      => 'SuperTuxKart',
            'descripcion' => 'Carreras de karts libres: carrera, batalla, fútbol y bandera, con 40 pistas para explorar.',
            'icono'       => 'fa-flag-checkered',
            'imagen'      => '/img/arcade/tarjeta-stk',
            'detalle'     => 'Carreras',
            'activo'      => true,
            'control_url' => 'http://arena-stk-salas:8081',
        ],
    ],
];
