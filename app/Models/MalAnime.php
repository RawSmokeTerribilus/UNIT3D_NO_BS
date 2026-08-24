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
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\MalAnime.
 *
 * @property int                             $id            MAL anime ID
 * @property string                          $title         Romaji title
 * @property string|null                     $title_english
 * @property string|null                     $title_japanese
 * @property string|null                     $synopsis
 * @property float|null                      $mean          MAL community score
 * @property int|null                        $rank
 * @property int|null                        $num_episodes
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null                     $media_type    tv|movie|ova|ona|special|music
 * @property string|null                     $status        finished_airing|currently_airing|not_yet_aired
 * @property string|null                     $nsfw          white|gray|black
 * @property string|null                     $poster
 * @property array|null                      $genres
 */
final class MalAnime extends Model
{
    public $incrementing = false;

    protected $table = 'mal_anime';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'genres'     => 'array',
            'mean'       => 'float',
            'rank'       => 'integer',
        ];
    }

    /**
     * @return HasMany<\App\Models\Torrent, $this>
     */
    public function torrents(): HasMany
    {
        return $this->hasMany(Torrent::class, 'mal', 'id');
    }
}
