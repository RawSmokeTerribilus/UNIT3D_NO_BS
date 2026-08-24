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
 * App\Models\Audiobook.
 *
 * One audiobook recording, keyed by its Audible ASIN — the only identifier
 * Audnexus accepts, and the only one that separates two recordings of the
 * same book. The ASIN is not always a B0-style code: Audible reuses the
 * ISBN-10 for part of its catalogue.
 *
 * @property string                      $asin
 * @property string                      $region
 * @property string                      $title
 * @property ?string                     $subtitle
 * @property ?array<int, string>         $authors
 * @property ?array<int, string>         $narrators
 * @property ?string                     $series
 * @property ?string                     $series_position
 * @property ?int                        $runtime_length_min
 * @property ?\Illuminate\Support\Carbon $release_date
 * @property ?string                     $publisher
 * @property ?int                        $book_publisher_id
 * @property ?int                        $book_series_id
 * @property ?string                     $language
 * @property ?array<int, string>         $genres
 * @property ?string                     $isbn13
 * @property ?string                     $description
 * @property ?string                     $cover_url
 * @property ?array<int, array{url: string, source: string, tier: string, w: ?int, h: ?int}> $cover_urls
 * @property ?array<string, string>      $provenance
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class Audiobook extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $guarded = [];

    /** @var string */
    protected $primaryKey = 'asin';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array{authors: 'array', narrators: 'array', genres: 'array', cover_urls: 'array', provenance: 'array', release_date: 'date'}
     */
    protected function casts(): array
    {
        return [
            'authors'      => 'array',
            'narrators'    => 'array',
            'genres'       => 'array',
            'cover_urls'   => 'array',
            'provenance'   => 'array',
            'release_date' => 'date',
        ];
    }

    /**
     * Get the torrents that carry this recording.
     *
     * @return HasMany<Torrent, $this>
     */
    public function torrents(): HasMany
    {
        return $this->hasMany(Torrent::class, 'asin', 'asin');
    }

    /**
     * The book this recording narrates, when Audnexus reported an ISBN.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Book, $this>
     */
    public function book(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Book::class, 'isbn13', 'isbn13');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<BookPublisher, $this>
     */
    public function bookPublisher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BookPublisher::class, 'book_publisher_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<BookSeries, $this>
     */
    public function bookSeries(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BookSeries::class, 'book_series_id', 'id');
    }

    /**
     * The resolved author records, shared with e-books on purpose: an author
     * is the same person whoever reads them out loud. The `authors` json
     * column is still the plain list Audnexus reports.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<BookAuthor, $this>
     */
    public function bookAuthors(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(BookAuthor::class, 'audiobook_author', 'asin', 'author_olid', 'asin', 'olid')
            ->withPivot('position')
            ->orderBy('audiobook_author.position');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<BookNarrator, $this>
     */
    public function bookNarrators(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(BookNarrator::class, 'audiobook_narrator', 'asin', 'book_narrator_id', 'asin', 'id')
            ->withPivot('position')
            ->orderBy('audiobook_narrator.position');
    }

    /**
     * Same catalogue as e-books: a genre is the same whether it is printed or
     * narrated, and splitting it would give two hubs with half the catalogue
     * each. Audnexus is the provider with the better genres here — Spanish and
     * specific, against Google Books' "Fiction".
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<BookGenre, $this>
     */
    public function bookGenres(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(BookGenre::class, 'audiobook_genre', 'asin', 'book_genre_id', 'asin', 'id');
    }

    /**
     * A single display line for the authors, the twin of Book::authorLine()
     * so both models render the same in shared blades.
     */
    public function authorLine(): string
    {
        return implode(', ', $this->authors ?? []);
    }

    /**
     * A single display line for the narrators — the field that makes two
     * recordings of the same title different releases.
     */
    public function narratorLine(): string
    {
        return implode(', ', $this->narrators ?? []);
    }

    /**
     * Runtime rendered as "12h 34m", or null when Audnexus had no duration.
     */
    public function runtimeForHumans(): ?string
    {
        if (!$this->runtime_length_min) {
            return null;
        }

        return intdiv($this->runtime_length_min, 60).'h '.($this->runtime_length_min % 60).'m';
    }

    /**
     * La portada mas pequena que llegue a `$minAncho`, o la mayor que haya.
     *
     * Lo que pide un consumidor de verdad: el hook de Telegram quiere ~1280 px
     * porque manda la imagen por la red, el listado ~300 porque pinta una
     * miniatura, y la ficha la mayor. Ninguno de los tres deberia tener que
     * razonar sobre el parametro `zoom` de Google ni sobre los sufijos de
     * Amazon.
     *
     * Devuelve `cover_url` cuando no hay pool, para que nada dependa de que
     * la escalera se haya generado.
     */
    public function coverAtLeast(int $minAncho = 0): ?string
    {
        return \App\Services\Metadata\CoverLadder::pick($this->cover_urls ?? [], $minAncho)
            ?? $this->cover_url;
    }
}
