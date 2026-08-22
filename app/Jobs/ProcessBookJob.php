<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GlobalRateLimit;
use App\Models\Book;
use App\Services\Metadata\CoverLadder;
use App\Models\BookGenre;
use App\Services\Books\GoogleBooksClient;
use App\Services\Books\OpenLibraryClient;
use App\Services\Books\Support\Isbn;
use App\Services\Books\TaxonomyNormalizer;
use App\Services\Translation\LibreTranslateClient;
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

    public function handle(GoogleBooksClient $google, OpenLibraryClient $openLibrary, LibreTranslateClient $translator): void
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

        // La sinopsis del propio volumen puede venir en ingles o no venir. Se
        // prefiere una en el idioma del sitio tomada de otra edicion de la
        // misma obra; ver GoogleBooksClient::descriptionFor().
        $idioma = substr((string) config('app.meta_locale', 'es_ES'), 0, 2);
        $sinopsis = $candidate->description;

        $original = null;
        $idiomaOriginal = null;

        // OpenLibrary entra por el MISMO embudo, no por detras. Antes se
        // aplicaba como respaldo al final, asi que su texto en ingles se
        // guardaba sin pasar por la comprobacion de idioma ni por la
        // traduccion, y la ficha acababa en ingles sin marcar.
        if ($sinopsis === '' && ($extra['description'] ?? '') !== '') {
            $sinopsis = $extra['description'];
            $provenance['description'] = 'openlibrary';
        }

        if ($sinopsis === '' || !self::pareceIdioma($sinopsis, $idioma)) {
            // Primero se busca una sinopsis nativa en otra edicion: siempre es
            // mejor la del editor que una traduccion automatica.
            $prestada = $google->descriptionFor($candidate->title, $candidate->authors, $idioma);

            if ($prestada !== '') {
                $sinopsis = $prestada;
                $provenance['description'] = 'google:otra-edicion';
            } elseif ($sinopsis !== '') {
                // No hay ninguna nativa. Se traduce, se marca como traducida y
                // se conserva el original para poder rehacerlo si cambia el
                // motor.
                $traducida = $translator->translate($sinopsis, 'en', $idioma);

                if ($traducida !== '') {
                    $original = $sinopsis;
                    $idiomaOriginal = 'en';
                    $sinopsis = $traducida;
                    $provenance['description'] = 'libretranslate:en-'.$idioma;
                }
            }
        }

        // OpenLibrary va DETRAS, no delante. Medido el 2026-08-21 con el
        // Tanenbaum: su portada -L son 128x164 px, mientras que Google con
        // zoom devuelve 575x750 del mismo libro. La suposicion inicial de que
        // OpenLibrary tenia mejores portadas era falsa; sirve de respaldo
        // para volumenes que Google no ilustra.
        if (isset($extra['cover_url'])) {
            $covers[] = $extra['cover_url'];

            if ($covers === [] || $covers[0] === $extra['cover_url']) {
                $provenance['cover'] = 'openlibrary';
            }
        }

        if ($extra !== []) {
            $provenance['enrichment'] = 'openlibrary';
        }

        // La misma portada en cuatro tamaños, sin una peticion mas: Google la
        // sirve por su parametro `zoom` y OpenLibrary por la letra final. Se
        // guardan todas porque quien la consume no quiere "la caratula", quiere
        // una de un tamano: el hook de Telegram manda ~1280 px, el listado
        // pinta ~300 y la ficha quiere la mayor que haya.
        //
        // Lo que devolvia la API se guardaba tal cual, y traia dos defectos:
        // un `zoom=5` que da los MISMOS 128 px que `zoom=1` --una entrada
        // duplicada que hacia que la rotacion ensenara dos veces la
        // miniatura-- y un `edge=curl` que le pinta a la portada una esquina
        // doblada falsa.
        // OpenLibrary sólo entra si de verdad conoce el libro, y eso se sabe
        // por el `olid`: sin él no ha contestado. Medido sobre cuatro ISBN
        // españoles reales, los CUATRO dan 404 en la portada y tres de ellos
        // también en la página. Meterla siempre llenaba el pool de entradas
        // muertas, y la rotación acababa enseñando una imagen rota.
        $pool = CoverLadder::merge(
            CoverLadder::googleBooks((string) ($candidate->googleVolumeId ?: '')),
            isset($extra['olid']) ? CoverLadder::openLibrary($isbn13) : [],
        );

        // Si ningun proveedor da escalera, al menos que no se pierda lo que
        // hubiera: se conserva como una entrada suelta de tamano desconocido.
        if ($pool === [] && $covers !== []) {
            $pool = array_map(
                static fn (string $u): array => [
                    'url' => $u, 'source' => 'google', 'tier' => 'xl', 'w' => null, 'h' => null,
                ],
                array_values(array_unique(array_filter($covers))),
            );
        }

        $book = Book::query()->updateOrCreate(['isbn13' => $isbn13], [
            'isbn10'                      => $candidate->isbn10 ?: null,
            'olid'                        => $extra['olid'] ?? null,
            'google_volume_id'            => $candidate->googleVolumeId ?: null,
            'title'                       => $candidate->title,
            'subtitle'                    => $candidate->subtitle ?: null,
            'authors'                     => $candidate->authors,
            'subjects'                    => $extra['subjects'] ?? null,
            'languages'                   => $candidate->language === '' ? null : [$candidate->language],
            'first_publish_year'          => $candidate->year,
            'page_count'                  => $extra['page_count'] ?? $candidate->pageCount,
            'publisher'                   => $candidate->publisher ?: null,
            'description'                 => $sinopsis ?: null,
            'description_original'        => $original,
            'description_source_language' => $idiomaOriginal,
            'cover_url'                   => CoverLadder::pick($pool, 800) ?? ($covers[0] ?? null),
            'cover_urls'                  => $pool,
            'average_rating'              => $candidate->averageRating,
            'ratings_count'               => $candidate->ratingsCount,
            'maturity_rating'             => $candidate->maturityRating ?: null,
            'print_type'                  => $candidate->printType ?: null,
            'preview_link'                => $candidate->previewLink ?: null,
            'info_link'                   => $candidate->infoLink ?: null,
            'provenance'                  => $provenance,
        ]);

        $this->syncGenres($book, $candidate->categories);

        // Editorial y saga se guardan como texto arriba, que es lo que da el
        // proveedor, y aquí se enlazan con su tabla. Sin esto quedan invisibles
        // para el MediaHub y para cualquier listado.
        app(TaxonomyNormalizer::class)->enlazarEditorialYSaga($book);

        // Same window TMDB and IGDB use, so a burst of uploads of the same
        // edition does not re-ask the provider.
        cache()->put("book-scraper:{$isbn13}", now(), 8 * 3600);
    }

    /**
     * Heuristica barata de idioma: cuenta palabras vacias del idioma buscado
     * frente a las inglesas. No hace falta mas precision -- se aplica a un
     * parrafo entero, no a una frase suelta, y el coste de fallar es pedir
     * una sinopsis alternativa que quiza no exista.
     */
    private static function pareceIdioma(string $texto, string $idioma): bool
    {
        if ($idioma !== 'es') {
            return true;   // solo se sabe distinguir castellano de ingles
        }

        $t = ' '.mb_strtolower($texto).' ';
        $es = 0;
        $en = 0;

        foreach ([' de ', ' la ', ' el ', ' que ', ' los ', ' las ', ' una ', ' con ', ' por ', ' para '] as $w) {
            $es += substr_count($t, $w);
        }

        foreach ([' the ', ' of ', ' and ', ' to ', ' in ', ' is ', ' for ', ' with ', ' this ', ' that '] as $w) {
            $en += substr_count($t, $w);
        }

        return $es >= $en;
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
