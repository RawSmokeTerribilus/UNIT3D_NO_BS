<?php

declare(strict_types=1);

namespace App\Services\Books\Support;

/**
 * A single book hit from one provider, normalised so hits can be scored and
 * compared.
 *
 * Deliberately not App\Services\Metadata\Support\Candidate: that one carries
 * the video id-set (tmdb/tvdb/imdb/mal) as readonly fields, and stuffing an
 * ISBN into it would make every one of those names a lie. The scoring helper
 * (Normalize) is shared; the shape is not.
 */
final class BookCandidate
{
    /**
     * Best title+year score across the resolver's query set.
     */
    public float $score = 0.0;

    /**
     * @param list<string> $authors
     * @param list<string> $coverUrls
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $title,
        public readonly ?int $year,
        public readonly string $isbn13,
        public readonly array $authors = [],
        public readonly string $subtitle = '',
        public readonly string $isbn10 = '',
        public readonly string $googleVolumeId = '',
        public readonly string $olid = '',
        public readonly string $publisher = '',
        public readonly ?int $pageCount = null,
        public readonly string $description = '',
        public readonly array $coverUrls = [],
        public readonly string $language = '',
        public readonly ?float $averageRating = null,
        public readonly ?int $ratingsCount = null,
        /** @var list<string> */
        public readonly array $categories = [],
        public readonly string $maturityRating = '',
        public readonly string $printType = '',
        public readonly string $previewLink = '',
        public readonly string $infoLink = '',
    ) {
    }

    /**
     * Authors as one line, for scoring against a release name that usually
     * carries "Author - Title".
     */
    public function authorLine(): string
    {
        return implode(', ', $this->authors);
    }
}
