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
    | Version de los assets (anticache de Cloudflare)
    |--------------------------------------------------------------------------
    |
    | Cloudflare cachea los SVG y los GIF en el edge con max-age=14400 (4h) y
    | una purga desde el panel NO evicto el objeto: seguia devolviendo
    | cf-cache-status HIT con el `age` intacto. Resultado: se puede iterar sobre
    | un icono y no verlo cambiar nunca.
    |
    | Se le cuelga esta version a la URL, igual que se hace con libretro.js.
    | SUBIRLA cada vez que se toque un fichero de icono o de efecto; si no, se
    | seguira sirviendo el viejo hasta que expire solo.
    |
    | Formato: AAAAMMDDnn
    */
    'version' => '2026082802',

    /*
    |--------------------------------------------------------------------------
    | Qué puede elegir un donante
    |--------------------------------------------------------------------------
    |
    | DECISIÓN DE PRODUCTO (operador, 2026-08-27): estas listas NO se segmentan
    | por tier ni por antigüedad. Cualquier donante, done 5 € o 50 €, elige lo
    | que quiera de aquí. Lo único que separa a los tiers es la DURACIÓN y los
    | BON; lo demás es común.
    |
    | El motivo, en sus palabras: «no queremos crear frustración sino
    | recompensar su fidelidad. Si les segregamos nosotros en base a nuestro
    | criterio, podemos fallar. Si deciden ellos, ganamos siempre».
    |
    | Cada lista es además la LISTA BLANCA que valida la elección: el valor
    | acaba en una ruta de imagen o en una propiedad CSS, así que nunca se
    | acepta nada que no sea una clave de aquí.
    |
    | Para añadir: dejar el fichero en public/img/... con dueño 82:82, añadir la
    | línea, y `php artisan config:cache` (NUNCA config:clear en producción).
    |
    */

    /*
    | Icono de rango, a la izquierda del nick. Sustituye al icono FontAwesome
    | del grupo. Sólo SVG con contraste suficiente sobre el fondo oscuro del
    | tema (#1a1a1f); ver la tabla medida en el vault, `iconos-de-rango-svg`.
    | Los ficheros viven en public/img/insignias/.
    */
    'iconos' => [
        'wizard.svg'              => 'Hechicero',
        'shinigami-blanco.svg'    => 'Parca',
        'alien.svg'               => 'Alien',
        'alien-5.svg'             => 'Alien gris',
        'alien-monster.svg'       => 'Alien monstruo',
        'gladiator-blanco.svg'    => 'Gladiador',
        'star-one-blanco.svg'     => 'Estrella',
        'sith-blanco.svg'         => 'Sith',
        'jedi-blanco.svg'         => 'Jedi',
        'rebel-blanco.svg'        => 'Rebelde',
        'boba-fett-blanco.svg'    => 'Cazarrecompensas',
    ],

    /*
    | Efecto de fondo del nick. El valor es el atajo `background` COMPLETO, no
    | sólo la url: hay texturas (que se tesela en horizontal) y marcos (que se
    | estiran a la caja), y cada uno necesita su propio tamaño y repetición.
    | Los gif viven en public/img/.
    */
    'efectos' => [
        'confeti-magenta'    => [
            'rotulo' => 'Confeti magenta',
            'css'    => 'url(/img/confeti-magenta.gif) center/auto 100% repeat-x',
        ],
        'sparkels'           => [
            'rotulo' => 'Purpurina',
            'css'    => 'url(/img/sparkels.gif) center/auto 100% repeat-x',
        ],
        'salpicadura-oscura' => [
            'rotulo' => 'Salpicadura',
            'css'    => 'url(/img/salpicadura-oscura.gif) center/auto 100% repeat-x',
        ],
        'estela-dorada'      => [
            'rotulo' => 'Estela dorada',
            'css'    => 'url(/img/estela-dorada.gif) center/auto 100% repeat-x',
        ],
        'trazo-neon-rosa'    => [
            'rotulo' => 'Neón rosa',
            'css'    => 'url(/img/trazo-neon-rosa.gif) center/100% 100% no-repeat',
        ],
        // El de la cuenta del operador en produccion. Es ademas el efecto del
        // grupo #root, asi que un donante que lo elija se vera igual que un
        // Owner: decision consciente, no descuido.
        'destellos-verdes'   => [
            'rotulo' => 'Destellos verdes',
            'css'    => 'url(/img/background9.gif) center/auto 100% repeat-x',
        ],
    ],

    /*
    | La insignia de la derecha NO se elige: es común a todos los donantes y
    | sustituye a la estrella. Es lo que los identifica como grupo de un
    | vistazo; lo que varía por gusto es el icono y el efecto.
    */
    'insignia' => [
        'fichero' => 'awesome-blanco.svg',
        'rotulo'  => 'Donante',
    ],
];
