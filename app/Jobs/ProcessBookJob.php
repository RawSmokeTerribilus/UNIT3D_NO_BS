<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GlobalRateLimit;
use App\Models\Book;
use App\Models\BookGenre;
use App\Services\Books\GoogleBooksClient;
use App\Services\Books\OpenLibraryClient;
use App\Services\Books\Support\Isbn;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Populate one row of `books` from its ISBN-13.
 *
 * Google Books supplies the record; OpenLibrary is asked afterwards for the
 * fields it is better at (subject headings, a longer synopsis, a page count,
 * a higher-resolution cover) and is allowed to return nothing, which for a
 * Spanish edition is the normal outcome.
 */
class ProcessBookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 300;

    public function __construct(public string $isbn13)
    {
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            Skip::when(cache()->has("book-scraper:{$this->isbn13}")),
            new WithoutOverlapping($this->isbn13)->dontRelease()->expireAfter(30),
            new RateLimited(GlobalRateLimit::GOOGLE_BOOKS),
        ];
    }

    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    public function handle(GoogleBooksClient $google, OpenLibraryClient $openLibrary): void
    {
        $isbn13 = Isbn::toIsbn13($this->isbn13);

        if ($isbn13 === '') {
            throw new RuntimeException('Not a valid ISBN: '.$this->isbn13);
        }

        $candidate = $google->byIsbn($isbn13);

        if ($candidate === null) {
            throw new RuntimeException('Google Books returned no volume for ISBN '.$isbn13);
        }

        $covers = $candidate->coverUrls;
        $provenance = ['identity' => 'google', 'cover' => $covers === [] ? null : 'google'];

        $extra = $openLibrary->enrich($isbn13);

        // OpenLibrary covers are the better image when they exist, so they go
        // to the front of the pool that meta:rotate-covers draws from.
        if (isset($extra['cover_url'])) {
            array_unshift($covers, $extra['cover_url']);
            $provenance['cover'] = 'openlibrary';
        }

        if ($extra !== []) {
            $provenance['enrichment'] = 'openlibrary';
        }

        $book = Book::query()->updateOrCreate(['isbn13' => $isbn13], [
            'isbn10'             => $candidate->isbn10 ?: null,
            'olid'               => $extra['olid'] ?? null,
            'google_volume_id'   => $candidate->googleVolumeId ?: null,
            'title'              => $candidate->title,
            'subtitle'           => $candidate->subtitle ?: null,
            'authors'            => $candidate->authors,
            'subjects'           => $extra['subjects'] ?? null,
            'languages'          => $candidate->language === '' ? null : [$candidate->language],
            'first_publish_year' => $candidate->year,
            'page_count'         => $extra['page_count'] ?? $candidate->pageCount,
            'publisher'          => $candidate->publisher ?: null,
            'description'        => $extra['description'] ?? ($candidate->description ?: null),
            'cover_url'          => $covers[0] ?? null,
            'cover_urls'         => array_values(array_unique($covers)),
            'average_rating'     => $candidate->averageRating,
            'ratings_count'      => $candidate->ratingsCount,
            'maturity_rating'    => $candidate->maturityRating ?: null,
            'print_type'         => $candidate->printType ?: null,
            'preview_link'       => $candidate->previewLink ?: null,
            'info_link'          => $candidate->infoLink ?: null,
            'provenance'         => $provenance,
        ]);

        $this->syncGenres($book, $candidate->categories);

        // Same window TMDB and IGDB use, so a burst of uploads of the same
        // edition does not re-ask the provider.
        cache()->put("book-scraper:{$isbn13}", now(), 8 * 3600);
    }

    /**
     * Los generos van en tabla y no en una columna json, igual que
     * igdb_genres para juegos: asi se pueden navegar, filtrar y contar.
     *
     * El slug es la clave de deduplicacion, porque Google devuelve la misma
     * categoria escrita de formas distintas segun la edicion.
     *
     * @param list<string> $categories
     */
    private function syncGenres(Book $book, array $categories): void
    {
        $ids = [];

        foreach ($categories as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $genre = BookGenre::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );

            $ids[] = $genre->id;
        }

        $book->genres()->sync(array_unique($ids));
    }
}
