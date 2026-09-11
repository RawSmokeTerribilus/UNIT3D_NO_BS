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

namespace App\Console\Commands;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\BookAuthor;
use App\Services\Books\OpenLibraryClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cachea la ficha de autor de cada libro y audiolibro: foto, biografía, fechas
 * e ids externos.
 *
 * Por qué existe aparte de books:sync: la identidad de un libro la resuelve
 * Google Books, que del autor sólo da el nombre en texto. Sin foto no se puede
 * pintar la columna de chips que sí tiene una ficha de película. OpenLibrary
 * publica registros de autor completos y es la única fuente gratuita con
 * imagen, así que aporta esto aunque no se le deje identificar ediciones.
 *
 * Los audiolibros entran aquí por la misma razón, y además por una segunda:
 * es el ÚNICO camino que llena `audiobook_author`. `TaxonomyNormalizer::
 * enlazarAutores()` sólo enlaza con fichas que ya existan --no puede inventar
 * un olid a partir de un nombre-- así que un audiolibro cuyo autor no esté ya
 * cacheado se queda sin pivote para siempre. Medido en prod: los tres
 * audiolibros de Laura Gallego tenían `authors: ["Laura Gallego"]` y cero
 * filas en el pivote, y sin pivote no hay filtro por autor, ni cruce de obras,
 * ni chip en la ficha.
 *
 * Va despacio a propósito. OpenLibrary no publica un límite pero deja de
 * responder cuando se le llama en bucle cerrado, y además la red de esta caja
 * pierde respuestas grandes mientras los contenedores sigan en MTU 1500
 * (ver Hardening/docker-mtu-1500-blackhole.md). El cliente reintenta; este
 * comando sólo se asegura de no atragantarlo.
 */
class SyncBookAuthors extends Command
{
    protected $signature = 'books:sync-authors
                            {--limit=50 : Cuántas obras procesar como máximo, de cada tipo}
                            {--force : Rehacer también las que ya tienen autores resueltos}
                            {--only= : Limitar a un tipo: book o audiobook}';

    protected $description = 'Cachea las fichas de autor (foto, biografía, fechas) de libros y audiolibros del catálogo';

    /**
     * Fichas ya resueltas en esta ejecución, por nombre en crudo.
     *
     * Evita repetir la consulta cuando varias obras comparten autor, que es lo
     * normal: una saga entera es el mismo. `false` marca «ya se preguntó y no
     * está», para no reintentar un nombre ausente una vez por volumen.
     *
     * @var array<string, array{olid: string}|false>
     */
    private array $cache = [];

    private int $resueltos = 0;

    private int $sinFicha = 0;

    private int $fallos = 0;

    final public function handle(OpenLibraryClient $openLibrary): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $only = $this->option('only');

        if ($only !== null && !\in_array($only, ['book', 'audiobook'], true)) {
            $this->error('--only admite «book» o «audiobook».');

            return self::FAILURE;
        }

        if ($only !== 'audiobook') {
            $query = Book::query()->whereNotNull('authors');

            if (!$force) {
                // Sólo los que aún no tienen ninguna fila en el pivote.
                $query->whereNotIn('isbn13', DB::table('book_author')->select('isbn13'));
            }

            $books = $query->limit($limit)->get();
            $this->info(sprintf('%d libro(s) por procesar.', $books->count()));

            foreach ($books as $book) {
                $this->procesar($openLibrary, $book);
            }
        }

        if ($only !== 'book') {
            $query = Audiobook::query()->whereNotNull('authors');

            if (!$force) {
                $query->whereNotIn('asin', DB::table('audiobook_author')->select('asin'));
            }

            $audiobooks = $query->limit($limit)->get();
            $this->info(sprintf('%d audiolibro(s) por procesar.', $audiobooks->count()));

            foreach ($audiobooks as $audiobook) {
                $this->procesar($openLibrary, $audiobook);
            }
        }

        $this->info(sprintf(
            'Hecho. %d autor(es) resuelto(s), %d sin ficha, %d fallo(s) de lectura.',
            $this->resueltos,
            $this->sinFicha,
            $this->fallos,
        ));

        return self::SUCCESS;
    }

    /**
     * Resuelve los autores de una obra y sincroniza su pivote.
     *
     * Libro y audiolibro comparten cuerpo porque los dos exponen `authors` y
     * `bookAuthors()`; lo único distinto es la tabla pivote, y de eso ya se
     * encarga la relación.
     */
    private function procesar(OpenLibraryClient $openLibrary, Audiobook|Book $obra): void
    {
        $names = array_values(array_filter($obra->authors ?? []));

        if ($names === []) {
            return;
        }

        $pivot = [];

        foreach ($names as $position => $name) {
            $olid = $this->fichaDe($openLibrary, $name);

            if ($olid === null) {
                continue;
            }

            $pivot[$olid] = ['position' => $position];
        }

        if ($pivot !== []) {
            $obra->bookAuthors()->sync($pivot);
        }
    }

    /**
     * El olid de un autor por su nombre, cacheando el resultado --incluida la
     * ausencia-- para el resto de la ejecución.
     */
    private function fichaDe(OpenLibraryClient $openLibrary, string $name): ?string
    {
        $clave = mb_strtolower(trim($name));

        if (\array_key_exists($clave, $this->cache)) {
            $hit = $this->cache[$clave];

            return $hit === false ? null : $hit['olid'];
        }

        try {
            $hit = $openLibrary->findAuthor($name);

            if ($hit === null) {
                $this->cache[$clave] = false;
                $this->sinFicha++;
                $this->line(sprintf('  <comment>?</comment> %s — sin ficha en OpenLibrary', $name));

                return null;
            }

            $full = $openLibrary->author($hit['olid']);

            if ($full === null) {
                $this->cache[$clave] = false;
                $this->fallos++;
                $this->line(sprintf('  <comment>!</comment> %s — %s no se pudo leer', $name, $hit['olid']));

                return null;
            }

            BookAuthor::query()->updateOrCreate(
                ['olid' => $full['olid']],
                $full + ['work_count' => $hit['work_count']],
            );

            $this->cache[$clave] = ['olid' => $full['olid']];
            $this->resueltos++;

            $this->line(sprintf(
                '  <info>ok</info> %-28s %-12s %s',
                mb_substr($name, 0, 28),
                $full['olid'],
                $full['photo_url'] ? 'con foto' : 'sin foto',
            ));

            return $full['olid'];
        } catch (Throwable $e) {
            $this->cache[$clave] = false;
            $this->fallos++;
            $this->warn(sprintf('  fallo %s: %s', $name, mb_substr($e->getMessage(), 0, 90)));

            return null;
        }
    }
}
