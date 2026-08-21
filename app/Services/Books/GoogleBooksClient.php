<?php

declare(strict_types=1);

namespace App\Services\Books;

use App\Services\Books\Support\BookCandidate;
use App\Services\Books\Support\Isbn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google Books provider — the one that identifies e-books on this tracker.
 *
 * Measured against the live API on 2026-08-20: it carries the Spanish
 * editions this catalogue is made of, with their ISBN-13 and a cover, where
 * OpenLibrary answers 404. See OpenLibraryClient for the other half of that
 * finding.
 *
 * Two behaviours of the API drive the shape of this class:
 *
 *  - `totalItems` is not trustworthy. The same query returns 300 or 0 on
 *    consecutive calls. Nothing here branches on it; only `items` counts.
 *  - It answers HTTP 503 intermittently for no reason. A short retry absorbs
 *    that, and the failure counter below drops the provider for the rest of a
 *    run once it is clearly down, the same way ImdbClient handles OMDb.
 */
final class GoogleBooksClient
{
    private const BASE = 'https://www.googleapis.com/books/v1/volumes';

    private const TIMEOUT = 12;

    /**
     * Consecutive failures before this provider is skipped for the rest of
     * the run. A full backfill would otherwise burn the 1000/day quota on
     * requests that are already failing.
     */
    private const FAILURE_THRESHOLD = 8;

    /**
     * Attempts per search. The API answers 503 often enough that a single
     * try loses real results; three with a growing pause absorbs it.
     */
    private const ATTEMPTS = 3;

    private int $failures = 0;

    private readonly string $key;

    public function __construct()
    {
        $this->key = (string) config('api-keys.google_books');
    }

    public function isEnabled(): bool
    {
        return $this->key !== '' && $this->failures < self::FAILURE_THRESHOLD;
    }

    /**
     * Title (and optionally author) search.
     *
     * @return list<BookCandidate>
     */
    public function search(string $title, ?string $author = null, int $limit = 10): array
    {
        if (!$this->isEnabled() || trim($title) === '') {
            return [];
        }

        $q = 'intitle:'.$title;

        if ($author !== null && trim($author) !== '') {
            // A literal space, never a '+'. The query is sent as a normal
            // parameter, so a '+' is percent-encoded to %2B and reaches
            // Google as a plus sign inside the search terms, which corrupts
            // the query and comes back as HTTP 503.
            $q .= ' inauthor:'.$author;
        }

        $items = $this->get([
            'q'            => $q,
            'maxResults'   => max(1, min(40, $limit)),
            'langRestrict' => substr((string) config('app.meta_locale', 'es_ES'), 0, 2),
            'country'      => 'ES',
        ]);

        return array_values(array_filter(array_map(
            fn (array $item): ?BookCandidate => $this->toCandidate($item),
            $items
        )));
    }

    /**
     * Direct ISBN lookup. Used to confirm an id the uploader typed in.
     */
    public function byIsbn(string $isbn): ?BookCandidate
    {
        $isbn13 = Isbn::toIsbn13($isbn);

        if (!$this->isEnabled() || $isbn13 === '') {
            return null;
        }

        foreach ($this->get(['q' => 'isbn:'.$isbn13, 'maxResults' => 5]) as $item) {
            $cand = $this->toCandidate($item);

            if ($cand !== null && $cand->isbn13 === $isbn13) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function toCandidate(array $item): ?BookCandidate
    {
        $v = $item['volumeInfo'] ?? null;

        if (!\is_array($v) || ($v['title'] ?? '') === '') {
            return null;
        }

        $isbn13 = '';
        $isbn10 = '';

        foreach ($v['industryIdentifiers'] ?? [] as $id) {
            if (($id['type'] ?? '') === 'ISBN_13') {
                $isbn13 = Isbn::toIsbn13((string) ($id['identifier'] ?? ''));
            } elseif (($id['type'] ?? '') === 'ISBN_10') {
                $isbn10 = Isbn::clean((string) ($id['identifier'] ?? ''));
            }
        }

        // Older editions are catalogued with an ISBN-10 only; converting keeps
        // them addressable instead of dropping them.
        if ($isbn13 === '' && $isbn10 !== '') {
            $isbn13 = Isbn::toIsbn13($isbn10);
        }

        if ($isbn13 === '') {
            return null;   // nothing to key a row on; the manual path takes over
        }

        $covers = [];

        foreach (['extraLarge', 'large', 'medium', 'thumbnail', 'smallThumbnail'] as $size) {
            $url = $v['imageLinks'][$size] ?? null;

            if (\is_string($url) && $url !== '') {
                $covers[] = str_replace('http://', 'https://', $url);
            }
        }

        return new BookCandidate(
            provider: 'google',
            title: (string) $v['title'],
            year: $this->year($v['publishedDate'] ?? null),
            isbn13: $isbn13,
            authors: array_values(array_filter(array_map('strval', $v['authors'] ?? []))),
            subtitle: (string) ($v['subtitle'] ?? ''),
            isbn10: $isbn10,
            googleVolumeId: (string) ($item['id'] ?? ''),
            publisher: (string) ($v['publisher'] ?? ''),
            pageCount: isset($v['pageCount']) ? (int) $v['pageCount'] : null,
            description: (string) ($v['description'] ?? ''),
            coverUrls: $covers,
            language: (string) ($v['language'] ?? ''),
            // Todo lo de abajo venia ya en la misma respuesta y se estaba
            // descartando. La ficha de un libro ES sus metadatos, asi que
            // tirarlos dejaba la pagina vacia al lado de la de una pelicula.
            averageRating: isset($v['averageRating']) ? (float) $v['averageRating'] : null,
            ratingsCount: isset($v['ratingsCount']) ? (int) $v['ratingsCount'] : null,
            categories: array_values(array_filter(array_map('strval', $v['categories'] ?? []))),
            maturityRating: (string) ($v['maturityRating'] ?? ''),
            printType: (string) ($v['printType'] ?? ''),
            previewLink: str_replace('http://', 'https://', (string) ($v['previewLink'] ?? '')),
            infoLink: str_replace('http://', 'https://', (string) ($v['infoLink'] ?? '')),
        );
    }

    private function year(mixed $published): ?int
    {
        if (!\is_string($published) || preg_match('/(\d{4})/', $published, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * One search request, retried once on an empty or failed response.
     *
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private function get(array $params): array
    {
        $params['key'] = $this->key;

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            try {
                $json = Http::timeout(self::TIMEOUT)->get(self::BASE, $params)->throw()->json();

                $items = \is_array($json) ? ($json['items'] ?? []) : [];

                if (\is_array($items) && $items !== []) {
                    $this->failures = 0;

                    return array_values($items);
                }
            } catch (Throwable $e) {
                $this->failures++;
                Log::warning('google-books.search failed: '.$e->getMessage());
            }

            if ($attempt < self::ATTEMPTS - 1) {
                usleep(400_000 * ($attempt + 1));
            }
        }

        return [];
    }
}
