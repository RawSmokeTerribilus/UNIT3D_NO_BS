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
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\BookPublisher.
 *
 * Editorial, normalizada. El equivalente de TmdbCompany: en la columna de
 * texto que escriben los proveedores caben tres grafías del mismo sello, así
 * que la columna se queda como está y la clave navegable vive aquí.
 *
 * @property int                         $id
 * @property string                      $name
 * @property string                      $slug
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class BookPublisher extends Model
{
    /** @var string[] */
    protected $guarded = [];

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'book_publisher_id', 'id');
    }

    /**
     * @return HasMany<Audiobook, $this>
     */
    public function audiobooks(): HasMany
    {
        return $this->hasMany(Audiobook::class, 'book_publisher_id', 'id');
    }
}
