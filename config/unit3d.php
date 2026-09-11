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
 * MODIFICADO PARA NOBS
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
 * Se distribuye bajo la misma licencia, GNU AGPL v3.0.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Powered By
    |--------------------------------------------------------------------------
    |
    | A string that describes the core software that powers the application
    |
    */

    'powered-by' => 'Powered By NOBS v9.2.0',

    /*
    |--------------------------------------------------------------------------
    | Codebase Name
    |--------------------------------------------------------------------------
    |
    | Name of Codebase
    |
    */

    'codebase' => 'NOBS',

    /*
    |--------------------------------------------------------------------------
    | Codebase Version
    |--------------------------------------------------------------------------
    |
    | Version of Codebase
    |
    */

    'version' => 'v9.2.0',

    /*
    |--------------------------------------------------------------------------
    | Owner Account Configuration
    |--------------------------------------------------------------------------
    |
    | Various settings related to the Owner account configuration
    |
    */

    'owner-username'         => env('DEFAULT_OWNER_NAME', 'UNIT3D'),
    'default-owner-email'    => env('DEFAULT_OWNER_EMAIL', 'none@none.com'),
    'default-owner-password' => env('DEFAULT_OWNER_PASSWORD', 'UNIT3D'),

    // If using a Reverse Proxy for HTTPS set the 'PROXY_SCHEME' value in your .env file to `https` or adjust the below value
    'proxy_scheme'      => env('PROXY_SCHEME', false),

    /*
     * Sirve por nuestro propio origen las imagenes de las DESCRIPCIONES cuyo
     * host no esta en la lista blanca. Con esto a false se vuelve al
     * comportamiento anterior: esas imagenes salen por images.weserv.nl, un
     * tercero gratuito que devuelve 404 con algunos origenes y deja el hueco
     * en blanco sin decir por que.
     *
     * Es un interruptor y no una constante para poder volver atras con un
     * cambio de .env y `config:cache`, sin desplegar codigo.
     */
    'description_image_proxy' => env('DESCRIPTION_IMAGE_PROXY', false),

    /*
     * Tope en GB de la cache en disco de ese proxy. Al pasarse, la tarea
     * `images:prune-description-cache` borra lo MENOS usado hasta volver por
     * debajo. Medido: solo las capturas de ptscreens que hay en descripciones
     * son ~19.900 imagenes de ~2 MB, o sea ~40 GB si se vieran todas.
     */
    'description_cache_cap_gb' => env('DESCRIPTION_CACHE_CAP_GB', 10),
    'root_url_override' => env('FORCE_ROOT_URL', false),

    // Global Rate Limit for Comments - X Per Minute
    'comment-rate-limit' => env('COMMENTS_PER_MINUTE', 3),

    /*
    |--------------------------------------------------------------------------
    | External Chat Platform
    |--------------------------------------------------------------------------
    |
    | Settings to configure an external chat platform
    |
    */

    'chat-link-name' => 'Discord',
    'chat-link-icon' => 'fab fa-discord',
    'chat-link-url'  => '',
];
