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
    | Catálogo de insignias de donante
    |--------------------------------------------------------------------------
    |
    | Los ficheros viven en `public/img/insignias/`. Esta lista es además la
    | LISTA BLANCA que valida lo que un donante puede elegir: sólo se acepta una
    | clave que esté aquí, de modo que el nombre nunca llega crudo a una ruta.
    |
    | Para añadir una insignia: copiar el .svg a public/img/insignias/ (con
    | dueño 82:82), añadir la línea aquí, y `php artisan config:cache`.
    |
    | Son SVG multicolor: llegan con su color propio y NO heredan el color del
    | rango. Si algún día se quiere que lo hereden, la vía es `mask-image` en
    | CSS en vez de <img>.
    |
    */
    'catalogo' => [
        'star.svg'           => 'Estrella',
        'star-alt.svg'       => 'Estrella doble',
        'star-one.svg'       => 'Estrella de plata',
        'star-rate.svg'      => 'Estrella de oro',
        'star-laptop.svg'    => 'Estrella catódica',
        'awesome.svg'        => 'Impresionante',
        'gladiator.svg'      => 'Gladiador',
        'ninja.svg'          => 'Ninja',
        'ninja-2.svg'        => 'Ninja en la sombra',
        'ninja-3.svg'        => 'Ninja veterano',
        'wizard.svg'         => 'Hechicero',
        'shinigami.svg'      => 'Shinigami',
        'alien.svg'          => 'Marciano',
        'alien-5.svg'        => 'Visitante',
        'alien-monster.svg'  => 'Cosa del espacio',
        'harry-potter.svg'   => 'El Elegido',
        'jedi.svg'           => 'Jedi',
        'sith.svg'           => 'Sith',
        'rebel.svg'          => 'Rebelde',
        'boba-fett.svg'      => 'Cazarrecompensas',
        // Ojo: este viene en blanco puro (#ffffff). Sobre fondo claro no se ve.
        'star-wars-logo.svg' => 'La Guerra de las Galaxias',
    ],
];
