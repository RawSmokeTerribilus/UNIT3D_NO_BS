<?php

declare(strict_types=1);

namespace App\Models;

use AllowDynamicProperties;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Book.
 *
 * One e-book edition, keyed by its ISBN-13. Identification comes from Google
 * Books; OpenLibrary only enriches an already-confirmed ISBN (synopsis,
 * subjects, page count, a fallback cover) and never decides identity.
 *
 * @property string                      $isbn13
 * @property ?string                     $isbn10
 * @property ?string                     $olid
 * @property ?string                     $google_volume_id
 * @property string                      $title
 * @property ?string                     $subtitle
 * @property ?array<int, string>         $authors
 * @property ?array<int, string>         $subjects
 * @property ?array<int, string>         $languages
 * @property ?int                        $first_publish_year
 * @property ?int                        $page_count
 * @property ?string                     $publisher
 * @property ?int                        $book_publisher_id
 * @property ?int                        $book_series_id
 * @property ?string                     $description
 * @property ?string                     $cover_url
 * @property ?array<int, array{url: string, source: string, tier: string, w: ?int, h: ?int}> $cover_urls
 * @property ?float                      $average_rating
 * @property ?int                        $ratings_count
 * @property ?string                     $maturity_rating
 * @property ?string                     $print_type
 * @property ?string                     $preview_link
 * @property ?string                     $info_link
 * @property ?string                     $series
 * @property ?string                     $series_position
 * @property ?array<string, string>      $provenance
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class Book extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $guarded = [];

    /**
     * The ISBN-13 is the key, so the model is neither incrementing nor
     * integer-keyed.
     *
     * @var string
     */
    protected $primaryKey = 'isbn13';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array{authors: 'array', subjects: 'array', languages: 'array', cover_urls: 'array', provenance: 'array'}
     */
    protected function casts(): array
    {
        return [
            'authors'    => 'array',
            'subjects'   => 'array',
            'languages'  => 'array',
            'cover_urls' => 'array',
            'provenance' => 'array',
        ];
    }

    /**
     * Get the torrents that carry this edition.
     *
     * @return HasMany<Torrent, $this>
     */
    public function torrents(): HasMany
    {
        return $this->hasMany(Torrent::class, 'isbn13', 'isbn13');
    }

    /**
     * Get the cached author records, in authorship order.
     *
     * Distinct from the `authors` json column: that one holds the plain names
     * Google Books reports and is always present; this one holds the resolved
     * OpenLibrary records with photo and bio, and may be empty until
     * `books:sync-authors` has run.
     *
     * @return BelongsToMany<BookAuthor, $this>
     */
    public function bookAuthors(): BelongsToMany
    {
        return $this->belongsToMany(BookAuthor::class, 'book_author', 'isbn13', 'author_olid', 'isbn13', 'olid')
            ->withPivot('position')
            ->orderBy('book_author.position');
    }

    /**
     * @return BelongsToMany<BookGenre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(BookGenre::class, 'book_genre', 'isbn13', 'book_genre_id', 'isbn13', 'id');
    }

    /**
     * The normalized publisher. The `publisher` column keeps whatever string
     * the provider sent; this is the one that can be navigated and counted.
     *
     * @return BelongsTo<BookPublisher, $this>
     */
    public function bookPublisher(): BelongsTo
    {
        return $this->belongsTo(BookPublisher::class, 'book_publisher_id', 'id');
    }

    /**
     * @return BelongsTo<BookSeries, $this>
     */
    public function bookSeries(): BelongsTo
    {
        return $this->belongsTo(BookSeries::class, 'book_series_id', 'id');
    }

    /**
     * A single display line for the authors, for blades and search documents.
     */
    public function authorLine(): string
    {
        return implode(', ', $this->authors ?? []);
    }

    /**
     * The rating as a percentage, so the blade can show it the same way a
     * film does. Google Books rates out of 5.
     */
    public function ratingPercent(): ?int
    {
        return $this->average_rating === null ? null : (int) round(((float) $this->average_rating) * 20);
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
