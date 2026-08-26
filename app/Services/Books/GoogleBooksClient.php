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

namespace App\Services\Books;

use App\Services\Books\Support\BookCandidate;
use App\Services\Books\Support\Isbn;
use App\Services\Metadata\Support\Normalize;
use Illuminate\Http\Client\RequestException;
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

    /**
     * Cuanto tiene que parecerse el titulo de otra edicion para aceptar su
     * sinopsis. Alto a proposito: es texto que se muestra como si fuera del
     * libro, y equivocarse es peor que no tener sinopsis.
     */
    private const MIN_SIBLING_SCORE = 0.90;

    /**
     * Y cuanto el autor. Es el filtro que descarta el spam de titulo parecido.
     */
    private const MIN_AUTHOR_MATCH = 0.85;

    private int $failures = 0;

    /**
     * Codigo HTTP del ultimo fallo, para poder distinguir "este ISBN no existe"
     * de "no pudimos preguntar". Con la cuota diaria agotada la API contesta
     * 429 y el volumen se daba por inexistente, que es justo lo contrario.
     */
    private ?int $lastStatus = null;

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
     * Una sinopsis en el idioma del sitio para un libro ya identificado.
     *
     * Por que existe: el volumen que casa con un ISBN espanol puede no tener
     * descripcion ninguna, o tenerla en ingles. Medido el 2026-08-21 con el
     * Tanenbaum: su volumen declara `language: es` y devuelve la sinopsis en
     * ingles desde la busqueda y ninguna desde el endpoint de volumen.
     *
     * Otras ediciones de la misma obra SI la tienen en castellano, asi que se
     * buscan y se toma prestada. Con un cuidado importante: entre las
     * "hermanas" aparecen libros distintos que comparten palabras del titulo
     * ("3 manuscritos en 1 libro", "55% off bookstores"). Copiar la sinopsis
     * de una de esas pegaria el texto equivocado, asi que se exige que la
     * edicion puntue muy alto en titulo Y autor antes de aceptarla.
     *
     * @param list<string> $authors
     */
    public function descriptionFor(string $title, array $authors, string $language = 'es'): string
    {
        if (!$this->isEnabled() || trim($title) === '') {
            return '';
        }

        $author = $authors[0] ?? null;
        $items = $this->get([
            'q'            => 'intitle:'.$title.($author !== null ? ' inauthor:'.$author : ''),
            'maxResults'   => 10,
            'langRestrict' => $language,
            'country'      => 'ES',
        ]);

        $mejor = '';
        $mejorPuntuacion = 0.0;

        foreach ($items as $item) {
            $v = $item['volumeInfo'] ?? [];
            $descripcion = trim((string) ($v['description'] ?? ''));

            if ($descripcion === '' || ($v['language'] ?? '') !== $language) {
                continue;
            }

            $puntuacion = Normalize::titleScore($title, (string) ($v['title'] ?? ''));

            // El titulo por si solo no basta: hay libros distintos que
            // comparten palabras. El autor es el que descarta el spam.
            if ($author !== null && $v['authors'] ?? false) {
                $autorMax = 0.0;

                foreach ($v['authors'] as $candidato) {
                    $autorMax = max($autorMax, Normalize::titleScore($author, (string) $candidato));
                }

                if ($autorMax < self::MIN_AUTHOR_MATCH) {
                    continue;
                }
            }

            if ($puntuacion > $mejorPuntuacion) {
                $mejorPuntuacion = $puntuacion;
                $mejor = $descripcion;
            }
        }

        return $mejorPuntuacion >= self::MIN_SIBLING_SCORE ? $mejor : '';
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
        $volumeId = (string) ($item['id'] ?? '');

        // imageLinks devuelve miniaturas de 128 px, que en una ficha se ven
        // lamentables. El parametro `zoom` de books.google.com no esta
        // documentado pero funciona, y da esto (medido el 2026-08-21):
        //   zoom=1  128x170     zoom=3  575x750
        //   zoom=4  800x1018    zoom=6  2177x2771 (segun el volumen)
        // El parametro se AUTOLIMITA a lo que el editor subio, asi que pedir
        // zoom=6 devuelve siempre la mejor disponible y nunca falla por pedir
        // de mas. El art proxy la reduce al servirla y cachea el resultado.
        if ($volumeId !== '') {
            $covers[] = 'https://books.google.com/books/content?id='.$volumeId
                .'&printsec=frontcover&img=1&zoom=6';
        }

        // Las de imageLinks quedan detras como respaldo, por si el volumen no
        // tiene portada servida por el endpoint de contenido.
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
    /**
     * Codigo HTTP del ultimo fallo, o null si el ultimo intento no fallo o
     * fallo sin respuesta (timeout, DNS).
     */
    public function lastStatus(): ?int
    {
        return $this->lastStatus;
    }

    /**
     * Un fallo pasajero --cuota agotada o la API caida-- no dice nada sobre si
     * el volumen existe.
     */
    public function lastFailureWasTransient(): bool
    {
        return \in_array($this->lastStatus, [429, 500, 502, 503, 504], true);
    }

    private function get(array $params): array
    {
        $params['key'] = $this->key;

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            try {
                $json = Http::timeout(self::TIMEOUT)->get(self::BASE, $params)->throw()->json();

                $items = \is_array($json) ? ($json['items'] ?? []) : [];

                if (\is_array($items) && $items !== []) {
                    $this->failures = 0;
                    $this->lastStatus = null;

                    return array_values($items);
                }
            } catch (Throwable $e) {
                $this->failures++;
                $this->lastStatus = $e instanceof RequestException ? $e->response->status() : null;
                Log::warning('google-books.search failed: '.$e->getMessage());
            }

            if ($attempt < self::ATTEMPTS - 1) {
                usleep(400_000 * ($attempt + 1));
            }
        }

        return [];
    }
}
