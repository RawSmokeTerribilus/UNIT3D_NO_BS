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
use App\Models\BookGenre;
use App\Models\BookNarrator;
use App\Models\BookPublisher;
use App\Models\BookSeries;
use App\Services\Books\TaxonomyNormalizer;
use Illuminate\Console\Command;

/**
 * Normaliza en tablas lo que los proveedores entregan como texto suelto o json:
 * editorial, saga, narrador, autor de audiolibro y género de audiolibro.
 *
 * Por qué existe si los scrapers ya lo hacen: el catálogo que había se escribió
 * antes de que las tablas existieran. Este comando es el puente, y sirve además
 * para rehacer la normalización cuando cambien las reglas de deduplicado —de
 * ahí `--force`.
 *
 * Las reglas NO viven aquí, viven en TaxonomyNormalizer, que es el mismo que
 * usan los dos jobs. Si estuvieran duplicadas, backfill y scrape acabarían
 * discrepando.
 *
 * No consulta a ningún proveedor. Todo sale de la base.
 */
class SyncBookTaxonomy extends Command
{
    protected $signature = 'books:sync-taxonomy
                            {--force : Rehacer también los que ya están normalizados}';

    protected $description = 'Normaliza editoriales, sagas, narradores y géneros de libros y audiolibros a sus tablas';

    final public function handle(TaxonomyNormalizer $taxonomia): int
    {
        $force = (bool) $this->option('force');

        $this->line('Editoriales y sagas…');
        $filas = 0;

        foreach ([Book::query(), Audiobook::query()] as $query) {
            if (!$force) {
                $query->where(
                    fn ($q) => $q->whereNull('book_publisher_id')->orWhereNull('book_series_id')
                );
            }

            foreach ($query->get() as $fila) {
                $taxonomia->enlazarEditorialYSaga($fila);
                $filas++;
            }
        }

        $this->line('Narradores, géneros y autores de audiolibro…');
        $audiolibros = 0;

        foreach (Audiobook::query()->get() as $audiolibro) {
            if (!$force && $audiolibro->bookNarrators()->exists() && $audiolibro->bookGenres()->exists()) {
                continue;
            }

            $taxonomia->sincronizarNarradores($audiolibro, $audiolibro->narrators ?? []);
            $taxonomia->sincronizarGeneros($audiolibro, $audiolibro->genres ?? []);
            $taxonomia->enlazarAutores($audiolibro, $audiolibro->authors ?? []);
            $audiolibros++;
        }

        if (BookAuthor::query()->count() === 0) {
            $this->warn('No hay fichas de autor todavía; ejecuta books:sync-authors para poder enlazarlas.');
        }

        $this->newLine();
        $this->info(\sprintf('%d ficha(s) de edición y %d audiolibro(s) procesados.', $filas, $audiolibros));

        $this->table(
            ['Catálogo', 'Filas'],
            [
                ['Editoriales', BookPublisher::query()->count()],
                ['Sagas', BookSeries::query()->count()],
                ['Narradores', BookNarrator::query()->count()],
                ['Géneros', BookGenre::query()->count()],
                ['Autores', BookAuthor::query()->count()],
            ],
        );

        return Command::SUCCESS;
    }
}
