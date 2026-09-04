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
    'version' => '2026083001',

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
    | del grupo. Los ficheros viven en public/img/insignias/.
    |
    | SVG o PNG: `user-tag.blade.php` reconoce como imagen cualquier extensión
    | de imagen, y lo que NO lo sea lo trata como clase de FontAwesome. Si esa
    | condición vuelve a ser sólo '.svg', los png de aquí se inyectan como clase
    | CSS y desaparecen sin dar error.
    |
    | El contraste manda: sobre el fondo oscuro del tema (#1a1a1f) hay que medir
    | componiendo encima, nunca con `-alpha off`, que cuenta el transparente
    | como negro. Tabla medida en el vault, `iconos-de-rango-svg`.
    |
    | Los rótulos son de UNA palabra a propósito: es un menú, y un nombre
    | compuesto no ayuda a elegir.
    */
    'iconos' => [
        // Los de siempre, en svg blanco.
        'wizard.svg'              => 'Hechicero',
        'shinigami-blanco.svg'    => 'Parca',
        'alien.svg'               => 'Alien',
        'alien-5.svg'             => 'Gris',
        'alien-monster.svg'       => 'Monstruo',
        'gladiator-blanco.svg'    => 'Gladiador',
        'star-one-blanco.svg'     => 'Estrella',
        'sith-blanco.svg'         => 'Sith',
        'jedi-blanco.svg'         => 'Jedi',
        'rebel-blanco.svg'        => 'Rebelde',

        // Los dos cazarrecompensas van juntos por estar emparentados: el de
        // arriba es el svg blanco de siempre, el de abajo el del pack, a color.
        'boba-fett-blanco.svg'    => 'Boba',
        'mando.png'               => 'Mando',

        // Pack a color. Naves y cacharros.
        'halcon.png'              => 'Halcón',
        'muerte.png'              => 'Muerte',

        // Cascos.
        'soldado.png'             => 'Soldado',
        'clon.png'                => 'Clon',
        'casco.png'               => 'Casco',
        'vader.png'               => 'Vader',
        'kylo.png'                => 'Kylo',

        // Droides. «Arturito» es el guiño latinoamericano a R2-D2, que allí se
        // dobló así; el rótulo manda sobre la transliteración castellana.
        'arturito.png'            => 'Arturito',
        'bebeocho.png'            => 'Bebeocho',
        'c3po.png'                => 'C3PO',
        'droide.png'              => 'Droide',

        // Dorados, de trazo fino. Contraste medido sobre #1a1a1f: 0,154 /
        // 0,145 / 0,171 — se leen, pero están en la banda floja de la tabla
        // (`star-laptop` 0,136 flojo, `awesome` 0,179 bien). Si se ven
        // pequeños, la palanca es encoger el viewBox como en `alien-5`, no
        // tocar el CSS.
        'infinito.svg'            => 'Infinito',
        'laurel.svg'              => 'Laurel',
        'rayo.svg'                => 'Rayo',

        // Neón. Venía con 78% de relleno transparente y descentrada;
        // recortada, recentrada y llevada a 130 px como el resto del pack.
        'diana.png'               => 'Diana',
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
