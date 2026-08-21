<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\BookAuthor;
use App\Services\Books\OpenLibraryClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cachea la ficha de autor de cada libro: foto, biografía, fechas e ids
 * externos.
 *
 * Por qué existe aparte de books:sync: la identidad de un libro la resuelve
 * Google Books, que del autor sólo da el nombre en texto. Sin foto no se puede
 * pintar la columna de chips que sí tiene una ficha de película. OpenLibrary
 * publica registros de autor completos y es la única fuente gratuita con
 * imagen, así que aporta esto aunque no se le deje identificar ediciones.
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
                            {--limit=50 : Cuántos libros procesar como máximo}
                            {--force : Rehacer también los que ya tienen autores resueltos}';

    protected $description = 'Cachea las fichas de autor (foto, biografía, fechas) de los libros del catálogo';

    final public function handle(OpenLibraryClient $openLibrary): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');

        $query = Book::query()->whereNotNull('authors');

        if (!$force) {
            // Sólo los que aún no tienen ninguna fila en el pivote.
            $query->whereNotIn('isbn13', DB::table('book_author')->select('isbn13'));
        }

        $books = $query->limit($limit)->get();

        $this->info(sprintf('%d libro(s) por procesar.', $books->count()));

        $resueltos = 0;
        $sinFicha = 0;
        $fallos = 0;

        foreach ($books as $book) {
            $names = array_values(array_filter($book->authors ?? []));

            if ($names === []) {
                continue;
            }

            $pivot = [];

            foreach ($names as $position => $name) {
                try {
                    $hit = $openLibrary->findAuthor($name);

                    if ($hit === null) {
                        $sinFicha++;
                        $this->line(sprintf('  <comment>?</comment> %s — sin ficha en OpenLibrary', $name));

                        continue;
                    }

                    $full = $openLibrary->author($hit['olid']);

                    if ($full === null) {
                        $fallos++;
                        $this->line(sprintf('  <comment>!</comment> %s — %s no se pudo leer', $name, $hit['olid']));

                        continue;
                    }

                    BookAuthor::query()->updateOrCreate(
                        ['olid' => $full['olid']],
                        $full + ['work_count' => $hit['work_count']],
                    );

                    $pivot[$full['olid']] = ['position' => $position];
                    $resueltos++;

                    $this->line(sprintf(
                        '  <info>ok</info> %-28s %-12s %s',
                        mb_substr($name, 0, 28),
                        $full['olid'],
                        $full['photo_url'] ? 'con foto' : 'sin foto',
                    ));
                } catch (Throwable $e) {
                    $fallos++;
                    $this->warn(sprintf('  fallo %s: %s', $name, mb_substr($e->getMessage(), 0, 90)));
                }
            }

            if ($pivot !== []) {
                $book->bookAuthors()->sync($pivot);
            }
        }

        $this->info(sprintf(
            'Hecho. %d autor(es) resuelto(s), %d sin ficha, %d fallo(s) de lectura.',
            $resueltos,
            $sinFicha,
            $fallos,
        ));

        return self::SUCCESS;
    }
}
