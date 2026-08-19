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
     * Credentials for App\Services\Igdb\IgdbClient.
     *
     * IGDB belongs to Twitch, so its API is authenticated with a
     * client-credentials token issued to a Twitch application. Register one at
     * https://dev.twitch.tv/console/apps — no user account is linked and no
     * personal data is exchanged; the pair works as a plain API key.
     */
    'credentials' => [
        'client_id'     => env('TWITCH_CLIENT_ID', ''),
        'client_secret' => env('TWITCH_CLIENT_SECRET', ''),
    ],
];
