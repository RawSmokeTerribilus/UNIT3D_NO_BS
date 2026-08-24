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

use App\Services\Books\Support\Isbn;
use App\Services\Metadata\Support\Normalize;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpenLibrary — enrichment only. It does not identify and it does not vote.
 *
 * Measured against the live API on 2026-08-20 with "El nombre del viento":
 * /isbn/ answered 404 for both Spanish ISBNs while the English edition
 * returned 200; a title search returned a different book altogether (Marcelo
 * Birmajer); adding the author dropped the single hit to zero. Its Spanish
 * coverage is not thin, it is actively wrong, so letting it vote would only
 * inject false positives into a Spanish-language catalogue.
 *
 * What it is still good for is filling gaps on a record Google Books has
 * already identified: subject headings, a longer synopsis, a page count and a
 * higher-resolution cover. All of that is keyed off a confirmed ISBN-13, so a
 * miss here costs nothing.
 *
 * No API key. OpenLibrary asks for a contact address in the User-Agent as a
 * courtesy, and that is cheap to honour.
 */
final class OpenLibraryClient
{
    private const BASE = 'https://openlibrary.org';

    private const COVERS = 'https://covers.openlibrary.org';

    private const TIMEOUT = 12;

    private const FAILURE_THRESHOLD = 8;

    /**
     * Below this a name match is not the same author. Author names are short,
     * so a loose threshold attaches the wrong biography to a book.
     */
    private const MIN_AUTHOR_SCORE = 0.85;

    /**
     * OpenLibrary has no published limit but does start refusing when called
     * in a tight loop -- measured on 2026-08-21, it stopped answering after
     * two back-to-back requests. This paces every call from this client.
     */
    private const THROTTLE_MICROSECONDS = 350_000;

    /**
     * Intentos por peticion. Ver la nota de MTU en get(): la red de esta caja
     * pierde respuestas grandes de forma intermitente.
     */
    private const ATTEMPTS = 3;

    private int $failures = 0;

    public function isEnabled(): bool
    {
        return $this->failures < self::FAILURE_THRESHOLD;
    }

    /**
     * Extra fields for an already-identified edition. Returns an empty array
     * when OpenLibrary does not know this ISBN, which for Spanish editions is
     * the common case.
     *
     * @return array{olid?: string, subjects?: list<string>, description?: string, page_count?: int, cover_url?: string}
     */
    public function enrich(string $isbn): array
    {
        $isbn13 = Isbn::toIsbn13($isbn);

        if (!$this->isEnabled() || $isbn13 === '') {
            return [];
        }

        $edition = $this->get('/isbn/'.$isbn13.'.json');

        if ($edition === null) {
            return [];
        }

        $out = [];

        if (isset($edition['number_of_pages']) && (int) $edition['number_of_pages'] > 0) {
            $out['page_count'] = (int) $edition['number_of_pages'];
        }

        foreach ($edition['covers'] ?? [] as $coverId) {
            if ((int) $coverId > 0) {
                $out['cover_url'] = self::COVERS.'/b/id/'.((int) $coverId).'-L.jpg';

                break;
            }
        }

        // Subjects and the long synopsis live on the work, not the edition.
        $workKey = $edition['works'][0]['key'] ?? null;

        if (\is_string($workKey) && $workKey !== '') {
            $out['olid'] = basename($workKey);
            $work = $this->get($workKey.'.json');

            if ($work !== null) {
                $subjects = array_values(array_filter(array_map(
                    'strval',
                    \is_array($work['subjects'] ?? null) ? $work['subjects'] : []
                )));

                if ($subjects !== []) {
                    $out['subjects'] = \array_slice($subjects, 0, 25);
                }

                $description = $work['description'] ?? null;

                // OpenLibrary returns description either as a bare string or
                // as {type, value}, depending on how old the record is.
                if (\is_array($description)) {
                    $description = $description['value'] ?? null;
                }

                if (\is_string($description) && trim($description) !== '') {
                    $out['description'] = trim($description);
                }
            }
        }

        return $out;
    }

    /**
     * Find an author record by name.
     *
     * OpenLibrary's edition coverage in Spanish is bad enough that it is not
     * allowed to identify books, but its AUTHOR catalogue is good and is the
     * only free source with a photo and a biography. That is why this lives
     * here and not in the resolver.
     *
     * @return array{olid: string, name: string, work_count: ?int}|null
     */
    public function findAuthor(string $name): ?array
    {
        if (!$this->isEnabled() || trim($name) === '') {
            return null;
        }

        $json = $this->get('/search/authors.json?q='.rawurlencode(trim($name)));
        $docs = \is_array($json) ? ($json['docs'] ?? []) : [];

        if (!\is_array($docs) || $docs === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach (\array_slice($docs, 0, 5) as $doc) {
            $candidate = (string) ($doc['name'] ?? '');
            $score = Normalize::titleScore($name, $candidate);

            // Un empate se rompe por obra publicada: OpenLibrary tiene fichas
            // duplicadas del mismo autor y la buena suele ser la que acumula
            // el catalogo.
            if ($score > $bestScore || ($score === $bestScore && (int) ($doc['work_count'] ?? 0) > (int) ($best['work_count'] ?? 0))) {
                $bestScore = $score;
                $best = $doc;
            }
        }

        if ($best === null || $bestScore < self::MIN_AUTHOR_SCORE) {
            return null;
        }

        $olid = basename((string) ($best['key'] ?? ''));

        return $olid === '' ? null : [
            'olid'       => $olid,
            'name'       => (string) ($best['name'] ?? $name),
            'work_count' => isset($best['work_count']) ? (int) $best['work_count'] : null,
        ];
    }

    /**
     * Full author record: bio, dates, photo, external ids.
     *
     * @return array<string, mixed>|null
     */
    public function author(string $olid): ?array
    {
        $olid = trim($olid);

        if (!$this->isEnabled() || $olid === '') {
            return null;
        }

        $d = $this->get('/authors/'.$olid.'.json');

        if ($d === null || ($d['name'] ?? '') === '') {
            return null;
        }

        // La bio llega como texto plano o como {type, value}, segun la
        // antiguedad del registro.
        $bio = $d['bio'] ?? null;

        if (\is_array($bio)) {
            $bio = $bio['value'] ?? null;
        }

        $photos = array_values(array_filter(
            (array) ($d['photos'] ?? []),
            static fn ($id): bool => (int) $id > 0
        ));

        return [
            'olid'            => $olid,
            'name'            => (string) $d['name'],
            'personal_name'   => (string) ($d['personal_name'] ?? '') ?: null,
            'alternate_names' => array_values(array_filter(array_map('strval', $d['alternate_names'] ?? []))) ?: null,
            'bio'             => \is_string($bio) && trim($bio) !== '' ? trim($bio) : null,
            'birth_date'      => (string) ($d['birth_date'] ?? '') ?: null,
            'death_date'      => (string) ($d['death_date'] ?? '') ?: null,
            'photo_url'       => $photos === [] ? null : self::COVERS.'/a/id/'.((int) $photos[0]).'-M.jpg',
            'remote_ids'      => \is_array($d['remote_ids'] ?? null) && $d['remote_ids'] !== [] ? $d['remote_ids'] : null,
        ];
    }

    /**
     * Cover URL straight from an ISBN, without a metadata round trip. Returns
     * '' when OpenLibrary has no image, thanks to default=false.
     */
    public function coverUrl(string $isbn, string $size = 'L'): string
    {
        $isbn13 = Isbn::toIsbn13($isbn);

        return $isbn13 === '' ? '' : self::COVERS.'/b/isbn/'.$isbn13.'-'.$size.'.jpg?default=false';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get(string $path): ?array
    {
        // Los fallos aqui suelen ser de red, no del proveedor: los
        // contenedores de esta caja salen con MTU 1500 mientras la WAN esta a
        // 1492, asi que las respuestas grandes se pierden sin ICMP y la
        // conexion se resetea. Medido el 2026-08-21: 4/4 desde el host, 1/3
        // desde el contenedor. Hasta que eso se arregle, un intento unico
        // descarta autores que si existen.
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            usleep(self::THROTTLE_MICROSECONDS);

            try {
                $response = Http::timeout(self::TIMEOUT)
                    ->withHeaders(['User-Agent' => $this->userAgent()])
                    ->get(self::BASE.$path);

                // Un 404 es la respuesta esperada para la mayoria de ISBN
                // espanoles: es una ausencia, no un fallo, y no reintenta ni
                // cuenta para el cortacircuitos.
                if ($response->notFound()) {
                    return null;
                }

                $json = $response->throw()->json();

                $this->failures = 0;

                return \is_array($json) ? $json : null;
            } catch (Throwable $e) {
                $last = $e->getMessage();

                if ($attempt === self::ATTEMPTS - 1) {
                    $this->failures++;
                    Log::warning('openlibrary '.$path.' failed after '.self::ATTEMPTS.' attempts: '.$last);

                    return null;
                }

                usleep(self::THROTTLE_MICROSECONDS * ($attempt + 2));
            }
        }

        return null;
    }

    private function userAgent(): string
    {
        return config('app.name', 'UNIT3D').' metadata enrichment ('.config('mail.from.address', 'admin@localhost').')';
    }
}
