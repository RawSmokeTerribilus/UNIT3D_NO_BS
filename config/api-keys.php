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
    | TheMovieDB (Movies/TV)
    |--------------------------------------------------------------------------
    |
    | TMDB API Key
    |
    */

    'tmdb' => env('TMDB_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | OMDb (IMDB metadata)
    |--------------------------------------------------------------------------
    |
    | OMDb API Key — used by the metadata consensus resolver for IMDB-id
    | voting and by-id cross-verification. The resolver degrades gracefully
    | when this is empty (OMDb is simply skipped as a provider).
    |
    */

    'omdb' => env('OMDB_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Google Books (E-Books)
    |--------------------------------------------------------------------------
    |
    | Google Books API Key — second voter of the book consensus resolver,
    | alongside OpenLibrary (which needs no key). Free tier is 1000 requests
    | per day, so a full backfill must be rate limited. Requests also need
    | country=ES or the API answers 403 from a European address.
    |
    | The resolver degrades gracefully when this is empty: Google Books is
    | simply skipped and OpenLibrary answers alone.
    |
    */

    'google_books' => env('GOOGLE_BOOKS_API_KEY', ''),
];
