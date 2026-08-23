<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GlobalRateLimit;
use App\Models\Audiobook;
use App\Services\Audiobooks\AudnexusClient;
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
use RuntimeException;

/**
 * Populate one row of `audiobooks` from its Audible ASIN.
 *
 * One provider, one request: Audnexus already returns the whole record,
 * narrator and runtime included.
 */
class ProcessAudiobookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 300;

    public function __construct(public string $asin, public string $region = 'es')
    {
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            Skip::when(cache()->has("audiobook-scraper:{$this->asin}")),
            new WithoutOverlapping($this->asin)->dontRelease()->expireAfter(30),
            new RateLimited(GlobalRateLimit::AUDNEX),
        ];
    }

    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    public function handle(AudnexusClient $audnexus, LibreTranslateClient $translator): void
    {
        $record = $audnexus->book($this->asin, $this->region);

        if ($record === null) {
            throw new RuntimeException(
                'Audnexus returned no book for ASIN '.$this->asin.' in region '.$this->region
            );
        }

        $asin = (string) $record['asin'];
        $record['provenance'] = ['identity' => 'audible', 'metadata' => 'audnexus'];

        // Empty strings would render as blank labels on the torrent page; the
        // columns are nullable so the blade can skip them instead.
        foreach (['subtitle', 'series', 'series_position', 'publisher', 'language', 'description', 'cover_url'] as $field) {
            if (($record[$field] ?? '') === '') {
                $record[$field] = null;
            }
        }

        // Audnexus devuelve lo que Audible publique en esa region, y no
        // siempre es castellano: los titulos importados traen la sinopsis en
        // ingles. Mismo trato que en `books` y en `igdb_games`: se marca lo
        // traducido y se guarda el original, para poder rehacerlo si cambia
        // el motor sin volver a pedir nada al proveedor.
        $idioma = substr((string) config('app.meta_locale', 'es_ES'), 0, 2);
        $sinopsis = (string) ($record['description'] ?? '');

        $record['description_original'] = null;
        $record['description_source_language'] = null;

        if ($sinopsis !== '' && $idioma !== 'en' && !LibreTranslateClient::pareceCastellano($sinopsis)) {
            $traducida = $translator->translate($sinopsis, 'en', $idioma);

            if ($traducida !== '') {
                $record['description'] = $traducida;
                $record['description_original'] = $sinopsis;
                $record['description_source_language'] = 'en';
            }
        }

        unset($record['asin']);

        $audiobook = Audiobook::query()->updateOrCreate(['asin' => $asin], $record);

        // Audnexus entrega narradores, generos, editorial y saga como texto o
        // json. Aqui pasan a sus tablas, que es lo unico navegable. Los
        // autores solo se ENLAZAN con fichas ya resueltas: la clave de
        // `book_authors` es el olid de OpenLibrary y Audnexus no lo da.
        $taxonomia = app(TaxonomyNormalizer::class);
        $taxonomia->enlazarEditorialYSaga($audiobook);
        $taxonomia->sincronizarNarradores($audiobook, $audiobook->narrators ?? []);
        $taxonomia->sincronizarGeneros($audiobook, $audiobook->genres ?? []);
        $taxonomia->enlazarAutores($audiobook, $audiobook->authors ?? []);

        cache()->put("audiobook-scraper:{$asin}", now(), 8 * 3600);
    }
}
