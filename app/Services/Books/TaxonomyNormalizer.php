<?php

declare(strict_types=1);

namespace App\Services\Books;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\BookAuthor;
use App\Models\BookGenre;
use App\Models\BookNarrator;
use App\Models\BookPublisher;
use App\Models\BookSeries;
use Illuminate\Support\Str;

/**
 * Pasa a tablas lo que los proveedores entregan como texto suelto o json:
 * editorial, saga, narrador, autor y género.
 *
 * Un único sitio con las reglas de deduplicado, porque lo llaman tres:
 * ProcessBookJob y ProcessAudiobookJob al scrapear, y el comando
 * `books:sync-taxonomy` para el catálogo que ya existía. Si las reglas
 * viviesen en cada uno, el backfill y el scrape acabarían discrepando.
 *
 * No consulta a ningún proveedor.
 */
final class TaxonomyNormalizer
{
    /**
     * El nombre de saga suele traer el título original entre corchetes
     * («Crónica del asesino de reyes [Kingkiller Chronicles]»). Ese sufijo es
     * lo único que impedía agrupar dos ediciones de la misma saga, así que se
     * recorta para el slug. El nombre visible conserva la primera grafía vista,
     * que suele ser la más completa.
     */
    public function tituloCanonico(string $nombre): string
    {
        return trim(preg_replace('/\s*\[[^\]]*\]\s*$/u', '', $nombre) ?? $nombre);
    }

    public function editorial(?string $nombre): ?BookPublisher
    {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            return null;
        }

        return BookPublisher::query()->firstOrCreate(
            ['slug' => Str::slug($nombre)],
            ['name' => $nombre],
        );
    }

    public function saga(?string $nombre): ?BookSeries
    {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            return null;
        }

        return BookSeries::query()->firstOrCreate(
            ['slug' => Str::slug($this->tituloCanonico($nombre))],
            ['name' => $nombre],
        );
    }

    /**
     * Rellena las claves normalizadas de una fila ya guardada. Se llama después
     * del updateOrCreate porque las columnas de texto siguen siendo la fuente.
     */
    public function enlazarEditorialYSaga(Audiobook|Book $fila): void
    {
        $cambios = [];

        $editorial = $this->editorial($fila->publisher);

        if ($editorial !== null) {
            $cambios['book_publisher_id'] = $editorial->id;
        }

        $saga = $this->saga($fila->series);

        if ($saga !== null) {
            $cambios['book_series_id'] = $saga->id;
        }

        if ($cambios !== []) {
            $fila->forceFill($cambios)->save();
        }
    }

    /**
     * @param array<int, string> $nombres
     */
    public function sincronizarGeneros(Audiobook|Book $fila, array $nombres): void
    {
        $ids = [];

        foreach ($nombres as $nombre) {
            $nombre = trim($nombre);

            if ($nombre === '') {
                continue;
            }

            $ids[] = BookGenre::query()->firstOrCreate(
                ['slug' => Str::slug($nombre)],
                ['name' => $nombre],
            )->id;
        }

        $relacion = $fila instanceof Audiobook ? $fila->bookGenres() : $fila->genres();
        $relacion->sync(array_unique($ids));
    }

    /**
     * @param array<int, string> $nombres
     */
    public function sincronizarNarradores(Audiobook $audiolibro, array $nombres): void
    {
        $ids = [];

        foreach (array_values(array_filter($nombres)) as $posicion => $nombre) {
            $nombre = trim($nombre);

            if ($nombre === '') {
                continue;
            }

            $narrador = BookNarrator::query()->firstOrCreate(
                ['slug' => Str::slug($nombre)],
                ['name' => $nombre],
            );

            $ids[$narrador->id] = ['position' => $posicion];
        }

        $audiolibro->bookNarrators()->sync($ids);
    }

    /**
     * Enlaza un audiolibro con las fichas de autor que YA existen, emparejando
     * por nombre.
     *
     * Nunca las crea: la clave primaria de `book_authors` es el olid de
     * OpenLibrary y Audnexus sólo da el nombre en texto, así que inventarlo
     * sería inventar la clave. Los autores nuevos entran por
     * `books:sync-authors`, que sí pregunta a OpenLibrary.
     *
     * @param array<int, string> $nombres
     */
    public function enlazarAutores(Audiobook $audiolibro, array $nombres): void
    {
        /** @var array<string, string> $porNombre */
        $porNombre = BookAuthor::query()
            ->get(['olid', 'name'])
            ->mapWithKeys(fn (BookAuthor $a) => [Str::slug($a->name) => $a->olid])
            ->all();

        if ($porNombre === []) {
            return;
        }

        $ids = [];

        foreach (array_values(array_filter($nombres)) as $posicion => $nombre) {
            $olid = $porNombre[Str::slug(trim($nombre))] ?? null;

            if ($olid === null) {
                continue;
            }

            $ids[$olid] = ['position' => $posicion];
        }

        if ($ids !== []) {
            $audiolibro->bookAuthors()->sync($ids);
        }
    }
}
