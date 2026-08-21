<?php

declare(strict_types=1);

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
 * @property ?string                     $language
 * @property ?array<int, string>         $genres
 * @property ?string                     $isbn13
 * @property ?string                     $description
 * @property ?string                     $cover_url
 * @property ?array<int, string>         $cover_urls
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
}
