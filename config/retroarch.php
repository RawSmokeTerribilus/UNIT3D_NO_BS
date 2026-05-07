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
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

/**
 * Catálogo RetroArch — mapa sistema → core libretro.
 *
 * 'systems' lista los directorios bajo public/retroarch/assets/cores/roms/ que
 * tienen catálogo navegable. El controller y los artisan commands derivan
 * todo de aquí. Para añadir un sistema con ROMs: crear el subdir, registrarlo
 * abajo, y ejecutar `php artisan retroarch:scan-roms`.
 *
 * 'rom_mount' es el path virtual dentro de la VFS BrowserFS que monta
 * libretro.js (línea 192). Cambia sólo si tocas ese mount.
 *
 * 'arcade_overrides' permite forzar otro core para roms arcade individuales
 * cuando fbneo no la acepta. Clave = nombre del rom sin extensión.
 */
return [
    'rom_mount' => '/home/web_user/retroarch/userdata/content/downloads/roms',

    'page_size' => 60,

    'systems' => [
        'snes'    => ['label' => 'Super Nintendo',           'core' => 'snes9x',           'icon' => '/img/console/snes.png'],
        'nes'     => ['label' => 'Nintendo (NES)',           'core' => 'nestopia',         'icon' => '/img/console/nes.png'],
        'fds'     => [
            'label' => 'Famicom Disk System',
            'core'  => 'fceumm',
            'icon'  => '/img/console/fds.png',
            // Cerrado: FDS necesita el BIOS `disksys.rom` (8KB, copyright Nintendo)
            // en /home/web_user/retroarch/userdata/system/disksys.rom y no se
            // distribuye con los cores libretro. Sin él, fceumm/nestopia
            // erroran al cargar cualquier .fds. Sólo 2 juegos en disco así
            // que prioridad baja hasta resolver el deploy del BIOS.
            'unavailable'        => true,
            'unavailable_reason' => 'BIOS disksys.rom no desplegado.',
        ],
        'gb'      => ['label' => 'Game Boy',                 'core' => 'gambatte',         'icon' => '/img/console/gb.png'],
        'gbc'     => ['label' => 'Game Boy Color',           'core' => 'gambatte',         'icon' => '/img/console/gbc.png'],
        'gba'     => ['label' => 'Game Boy Advance',         'core' => 'mgba',             'icon' => '/img/console/gba.png'],
        'genesis' => ['label' => 'Sega Mega Drive / Genesis','core' => 'genesis_plus_gx',  'icon' => '/img/console/genesis.png'],
        'sms'     => ['label' => 'Sega Master System',       'core' => 'genesis_plus_gx',  'icon' => '/img/console/sms.png'],
        'gg'      => ['label' => 'Sega Game Gear',           'core' => 'genesis_plus_gx',  'icon' => '/img/console/gg.png'],
        'pce'     => ['label' => 'NEC PC Engine / TurboGrafx-16', 'core' => 'mednafen_pce_fast', 'icon' => '/img/console/pce.png'],
        'arcade'  => [
            'label' => 'Arcade',
            'core'  => 'fbneo',
            'icon'  => '/img/console/arcade.png',
            // Cerrado: el set de ROMs en disco es incompatible con el FBNeo
            // actual (split-set incompleto). Probado mame2003_plus también
            // falla porque los CRC no coinciden con su romset MAME 0.78.
            // Hasta resourcear un set válido, mantenemos la sección visible
            // (icono + conteo) pero sin lanzador → "Cerrado por mantenimiento".
            'unavailable'        => true,
            'unavailable_reason' => 'Romsets actuales incompatibles con el core. Trabajando en ello.',
        ],
    ],

    'arcade_overrides' => [
        // 'rom_basename' => 'mame2003_plus',
    ],

    /**
     * Cores presentes pero sin directorio de ROMs propio. El catálogo no los
     * lista como "Jugar ahora"; quedan accesibles desde el modo libre en
     * /retroarch/index.html para que el usuario suba su propio contenido.
     */
    'free_play_only_cores' => [
        'ecwolf', 'gearcoleco', 'mednafen_ngp', 'mednafen_vb', 'mednafen_wswan',
        'neocd', 'nxengine', 'opera', 'pcsx_rearmed', 'prboom', 'tic80',
        'vecx', 'vice_x64', 'virtualxt',
    ],
];
