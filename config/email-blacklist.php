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

return [
    /*
    |--------------------------------------------------------------------------
    | Email Domain Blacklist Configuration
    |--------------------------------------------------------------------------
    |
    | Sistema de sincronización multi-fuente para dominios desechables.
    | Las fuentes remotas se sincronizan localmente en la BD para evitar 
    | consultas online constantes.
    |
    */

    'enabled'   => true,

    /*
    |--------------------------------------------------------------------------
    | Storage Strategy
    |--------------------------------------------------------------------------
    | database: Usa la tabla 'disposable_email_domains' (recomendado)
    | cache: Usa solo caché (legacy)
    */
    'storage' => 'database',

    /*
    |--------------------------------------------------------------------------
    | Remote Sources (Sincronización Multi-Fuente)
    |--------------------------------------------------------------------------
    | Define múltiples fuentes remotas que se sincronizarán localmente
    */
    'remote_sources' => [
        'disposable-github' => [
            'url'      => 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf',
            'type'     => 'list', // 'list' o 'json'
            'enabled'  => true,
            'timeout'  => 30,
            'fallback' => true,
        ],
        'temp-mail-list' => [
            'url'      => 'https://github.com/search?q=temp+mail&type=repositories',
            'type'     => 'list',
            'enabled'  => false,
            'timeout'  => 30,
            'fallback' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Custom Domains
    |--------------------------------------------------------------------------
    | Dominios personalizados agregados localmente que se sincronizarán
    | en la BD y siempre estarán disponibles
    */
    'custom_domains' => 'dralias.com|simplelogin.com|passinbox.com|catmx.eu|alias.com|anonmail.net|tempmail.org|temp-mail.org|throwaway.email|yopmail.com|maildrop.cc|mailnesia.com|keemail.me|mintemail.com|10minutemail.com|trash-mail.com|spam4.me|dispostable.com|mailin8r.com|mailinator.com',

    /*
    |--------------------------------------------------------------------------
    | Hardcoded Known Forwarding Services
    |--------------------------------------------------------------------------
    | Servicios conocidos de re-envío de emails que siempre se bloquean
    */
    'hardcoded_services' => [
        'dralias.com',
        'simplelogin.com',
        'passinbox.com',
        'catmx.eu',
        'alias.com',
        'anonmail.net',
        'tempmail.org',
        'temp-mail.org',
        'throwaway.email',
        'yopmail.com',
        'maildrop.cc',
        'mailnesia.com',
        'keemail.me',
        'mintemail.com',
        '10minutemail.com',
        '10minutemail.de',
        'trash-mail.com',
        'spam4.me',
        'dispostable.com',
        'mailin8r.com',
        'mailinator.com',
        'guerrillamail.com',
        'tempmail.com',
        '1secmail.com',
        'protonmail.com',
        'ProtonMail.ch',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Configuration (Backward Compatibility)
    |--------------------------------------------------------------------------
    */
    'source'    => 'https://cdn.jsdelivr.net/gh/andreis/disposable-email-domains@master/domains.json',
    'cache-key' => 'email.domains.blacklist',
    'append'    => null, // Deprecated, use custom_domains instead

    /*
    |--------------------------------------------------------------------------
    | Synchronization
    |--------------------------------------------------------------------------
    | Configuración de sincronización automática
    */
    'sync' => [
        'enabled'   => true,
        'schedule'  => '0 * * * *', // Cada hora (cron format)
        'max_retries' => 3,
        'retry_delay' => 300, // 5 minutos
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled'  => true,
        'ttl'      => 3600, // 1 hora
        'key'      => 'email.blacklist.domains',
    ],
];

