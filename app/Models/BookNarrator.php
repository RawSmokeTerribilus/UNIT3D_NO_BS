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

use AllowDynamicProperties;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * App\Models\BookNarrator.
 *
 * Narrador de audiolibro. Es lo que distingue dos grabaciones del mismo texto
 * y por lo que la gente busca, así que va en tabla.
 *
 * No se reutiliza BookAuthor: aquella tiene el olid de OpenLibrary como clave
 * primaria y los narradores no están en OpenLibrary.
 *
 * @property int                         $id
 * @property string                      $name
 * @property string                      $slug
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class BookNarrator extends Model
{
    /** @var string[] */
    protected $guarded = [];

    /**
     * @return BelongsToMany<Audiobook, $this>
     */
    public function audiobooks(): BelongsToMany
    {
        return $this->belongsToMany(Audiobook::class, 'audiobook_narrator', 'book_narrator_id', 'asin', 'id', 'asin')
            ->withPivot('position');
    }
}
