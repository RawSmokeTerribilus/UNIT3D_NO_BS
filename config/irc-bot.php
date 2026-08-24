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
    | IRC Bot
    |--------------------------------------------------------------------------
    |
    | IRC Bot Settings
    |
    */

    'enabled'      => (bool) env('IRC_BOT_ENABLED', false),
    'hostname'     => env('IRC_BOT_HOSTNAME', 'example.com'),
    'server'       => env('IRC_BOT_SERVER', 'irc.example.com'),
    'port'         => (int) env('IRC_BOT_PORT', 6667),
    'username'     => env('IRC_BOT_USERNAME', 'UNIT3D'),
    'password'     => env('IRC_BOT_PASSWORD', 'UNIT3D'),
    'channel'      => env('IRC_BOT_CHANNEL', '#announce'),
    'channel_key'  => env('IRC_BOT_CHANNEL_KEY', ''),
    'nickservpass' => env('IRC_BOT_NICKSERV_PASS', false),
    'joinchannel'  => (bool) env('IRC_BOT_JOIN_CHANNEL', false),
];
