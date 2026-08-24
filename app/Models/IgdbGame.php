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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use AllowDynamicProperties;

/**
 * App\Models\IgdbGame.
 *
 * @property int                         $id
 * @property string                      $name
 * @property ?string                     $summary
 * @property ?string                     $first_artwork_image_id
 * @property ?\Illuminate\Support\Carbon $first_release_date
 * @property ?string                     $cover_image_id
 * @property ?string                     $url
 * @property ?float                      $rating
 * @property ?int                        $rating_count
 * @property ?string                     $first_video_video_id
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class IgdbGame extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array{first_release_date: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'first_release_date' => 'datetime',
        ];
    }

    /**
     * Get the platforms that belong to the game.
     *
     * @return BelongsToMany<IgdbPlatform, $this>
     */
    /**
     * La valoración redondeada a entero. IGDB la entrega como double con toda
     * la precisión del cálculo interno («86.737434604785»), que en una ficha
     * no significa nada. Gemelo de Book::ratingPercent().
     */
    public function ratingPercent(): ?int
    {
        return $this->rating === null ? null : (int) round((float) $this->rating);
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(IgdbPlatform::class);
    }

    /**
     * Get the companies that belong to the game.
     *
     * @return BelongsToMany<IgdbCompany, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(IgdbCompany::class);
    }

    /**
     * Get the genres that belong to the game.
     *
     * @return BelongsToMany<IgdbGenre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(IgdbGenre::class);
    }
}
