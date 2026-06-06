<?php

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
 * @credits    LokiThor2021 <https://github.com/LokiThor2021>
 */

declare(strict_types=1);

namespace App\Enums;

enum UserGroup: int
{
    // NOTE: values must match the live `groups` table ids. This install seeds
    // Editor (1) and Torrent Moderator (2) ahead of the stock groups, shifting
    // every other group +2 versus upstream UNIT3D's original enum ordering.
    case EDITOR = 1;
    case TORRENT_MODERATOR = 2;
    case VALIDATING = 3;
    case GUEST = 4;
    case USER = 5;
    case ADMINISTRATOR = 6;
    case BANNED = 7;
    case MODERATOR = 8;
    case UPLOADER = 9;
    case TRUSTEE = 10;
    case BOT = 11;
    case OWNER = 12;
    case POWERUSER = 13;
    case SUPERUSER = 14;
    case EXTREMEUSER = 15;
    case INSANEUSER = 16;
    case LEECH = 17;
    case VETERAN = 18;
    case SEEDER = 19;
    case ARCHIVIST = 20;
    case INTERNAL = 21;
    case DISABLED = 22;
    case PRUNED = 23;
}
