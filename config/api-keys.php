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
];
