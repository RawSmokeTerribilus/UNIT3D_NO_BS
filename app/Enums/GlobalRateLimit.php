<?php

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     Roardom <roardom@protonmail.com>
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

declare(strict_types=1);

namespace App\Enums;

enum GlobalRateLimit: string
{
    case ANNOUNCE = 'announce';
    case API = 'api';
    case AUTHENTICATED_IMAGES = 'authenticated-images';
    case CHAT = 'chat';
    case FORGOT_PASSWORD = 'forgot-password';
    case AUDNEX = 'audnex';
    case GOOGLE_BOOKS = 'google-books';
    case IGDB = 'igdb';
    case MAL = 'mal';
    case OPENLIBRARY = 'openlibrary';
    case RESET_PASSWORD = 'reset-password';
    case RSS = 'rss';
    case GAMING_SAVES = 'gaming-saves';
    case SEARCH = 'search';
    case TMDB = 'tmdb';
    case WEB = 'web';
}
