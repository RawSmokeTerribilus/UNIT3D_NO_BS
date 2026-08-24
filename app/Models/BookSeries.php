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
 * App\Models\BookSeries.
 *
 * Saga, normalizada. El equivalente de TmdbCollection, y la razón de existir
 * es medida: los dos audiolibros de la misma saga llegaron como
 * «Crónica del asesino de reyes [Kingkiller Chronicles]» y
 * «Crónica del asesino de reyes», o sea sin agrupar nunca.
 *
 * @property int                         $id
 * @property string                      $name
 * @property string                      $slug
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class BookSeries extends Model
{
    /**
     * Laravel pluraliza «BookSeries» a «book_series» igualmente, pero se
     * declara porque la palabra ya es plural y el siguiente que lo lea no
     * tiene por qué fiarse.
     *
     * @var string
     */
    protected $table = 'book_series';

    /** @var string[] */
    protected $guarded = [];

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'book_series_id', 'id');
    }

    /**
     * @return HasMany<Audiobook, $this>
     */
    public function audiobooks(): HasMany
    {
        return $this->hasMany(Audiobook::class, 'book_series_id', 'id');
    }
}
