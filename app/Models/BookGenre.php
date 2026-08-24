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
 * App\Models\BookGenre.
 *
 * Género de libro, en tabla y no en json, para que se pueda navegar y contar.
 * El espejo de IgdbGenre.
 *
 * @property int                         $id
 * @property string                      $name
 * @property string                      $slug
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class BookGenre extends Model
{
    /** @var string[] */
    protected $guarded = [];

    /**
     * @return BelongsToMany<Book, $this>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_genre', 'book_genre_id', 'isbn13', 'id', 'isbn13');
    }

    /**
     * @return BelongsToMany<Audiobook, $this>
     */
    public function audiobooks(): BelongsToMany
    {
        return $this->belongsToMany(Audiobook::class, 'audiobook_genre', 'book_genre_id', 'asin', 'id', 'asin');
    }
}
