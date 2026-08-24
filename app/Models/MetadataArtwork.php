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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\MetadataArtwork.
 *
 * Pool of cover/backdrop URLs harvested from every metadata provider, keyed
 * to a tmdb_movies / tmdb_tv title. meta:rotate-covers rotates the active
 * cover from this pool.
 *
 * @property int                             $id
 * @property string                          $category MOVIE|TV
 * @property int                             $tmdb_id
 * @property string                          $source   tmdb|omdb|tvmaze|jikan|anilist
 * @property string                          $type     poster|backdrop
 * @property string                          $url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class MetadataArtwork extends Model
{
    protected $table = 'metadata_artwork';

    protected $guarded = [];
}
