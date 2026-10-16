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

use App\Jobs\ProcessMovieJob;
use App\Jobs\ProcessTvJob;
use App\Models\TmdbPerson;
use App\Services\Tmdb\Client\Person;
use App\Services\Translation\LibreTranslateClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recupera las fichas de TMDB que se quedaron a medias. Idempotente: si no
 * falta nada, no hace nada, y se puede pulsar las veces que haga falta.
 *
 * Desde el 16-10 los jobs ya no vuelven a pedir a TMDB las personas que
 * tenemos guardadas, así que esto es la vía de recuperación:
 *
 *   - Películas y series EN USO (con torrent) sin ficha, sin reparto o sin
 *     sinopsis en castellano (desde el 16-10 el job la saca de es-MX o la
 *     traduce del inglés): se encolan otra vez en meta-refresh con force. Se
 *     saltan las que ya están encoladas (marca de 20 min) y las que el último
 *     job terminó BIEN y siguen igual (TMDB no tiene reparto ni sinopsis
 *     que sirva; la marca de éxito del job dura 8 h). Una que falló se puede
 *     relanzar al momento.
 *   - Personas citadas en créditos y que no tienen ficha: se piden ya.
 *   - --persona=ID: vuelve a pedir esa persona aunque la tengamos (foto
 *     nueva, defunción...).
 */
class TmdbRecuperarFichas extends Command
{
    protected $signature = 'tmdb:recuperar-fichas
                            {--limit=20 : Cuántos títulos encolar como máximo, de cada tipo}
                            {--persona=* : Id de TMDB de una persona a refrescar aunque ya exista}';

    protected $description = 'Relanza las fichas de TMDB en uso que se quedaron sin reparto o sin sinopsis en castellano y pide las personas que faltan';

    private const int MARCA_ENCOLADA = 20 * 60;

    private int $saltadas = 0;

    final public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $series = $this->relanzar('tv', 'tmdb_tv_id', 'tmdb_tv', 'tmdb-tv-scraper', $limit);
        $pelis = $this->relanzar('movie', 'tmdb_movie_id', 'tmdb_movies', 'tmdb-movie-scraper', $limit);

        $huerfanas = DB::table('tmdb_credits')
            ->whereNotIn('tmdb_person_id', DB::table('tmdb_people')->select('id'))
            ->distinct()
            ->limit(200)
            ->pluck('tmdb_person_id');
        $pedidas = collect((array) $this->option('persona'))->map(fn ($id) => (int) $id)->filter();
        $personas = $this->personas($huerfanas->merge($pedidas)->unique()->values());

        if ($series + $pelis + $personas === 0) {
            $this->info($this->saltadas === 0
                ? '✓ Nada que recuperar: las fichas de TMDB en uso tienen reparto y sinopsis, y no falta ninguna persona.'
                : '✓ Nada nuevo que encolar: lo que falta ya está en la cola o TMDB no tiene reparto ni sinopsis que sirva.');

            return self::SUCCESS;
        }

        $this->info("Encoladas {$series} series y {$pelis} películas en meta-refresh; {$personas} personas pedidas a TMDB. Se ven en la página de colas.");

        return self::SUCCESS;
    }

    /**
     * Encola los títulos en uso sin ficha o sin reparto. Devuelve cuántos.
     */
    private function relanzar(string $tipo, string $columna, string $tabla, string $marcaExito, int $limit): int
    {
        $enUso = DB::table('torrents')->whereNull('deleted_at')->where($columna, '>', 0)->distinct()->pluck($columna);
        $conFicha = DB::table($tabla)->whereIn('id', $enUso)->pluck('overview', 'id');
        $conReparto = DB::table('tmdb_credits')->whereIn($columna, $enUso)->distinct()->pluck($columna)->flip();
        $enCastellano = fn ($id): bool => trim((string) $conFicha[$id]) !== '' && LibreTranslateClient::pareceCastellano((string) $conFicha[$id]);

        $faltan = $enUso->reject(fn ($id) => $conFicha->has($id) && isset($conReparto[$id]) && $enCastellano($id));
        $saltadas = 0;
        $encoladas = 0;

        foreach ($faltan as $id) {
            if ($encoladas >= $limit) {
                break;
            }

            $tieneFicha = $conFicha->has($id);

            if (cache()->has("tmdb-recuperar:{$tipo}:{$id}") || ($tieneFicha && cache()->has("{$marcaExito}:{$id}"))) {
                $saltadas++;

                continue;
            }

            $job = $tipo === 'tv' ? new ProcessTvJob((int) $id, true) : new ProcessMovieJob((int) $id, true);
            dispatch($job->onQueue('meta-refresh'));
            cache()->put("tmdb-recuperar:{$tipo}:{$id}", now(), self::MARCA_ENCOLADA);

            $nombre = DB::table($tabla)->where('id', $id)->value($tipo === 'tv' ? 'name' : 'title') ?? "#{$id}";
            $motivo = match (true) {
                !$tieneFicha               => 'sin ficha',
                !isset($conReparto[$id])   => 'sin reparto',
                default                    => 'sin sinopsis en castellano',
            };
            $this->line(($tipo === 'tv' ? '📺 ' : '🎬 ')."{$nombre} ({$motivo})");
            $encoladas++;
        }

        $this->saltadas += $saltadas;

        if ($saltadas > 0) {
            $this->line(($tipo === 'tv' ? 'Series' : 'Películas')." saltadas: {$saltadas} (ya encoladas, o TMDB no tiene reparto ni sinopsis que sirva).");
        }

        return $encoladas;
    }

    /**
     * Pide a TMDB estas personas y las guarda. Devuelve cuántas.
     *
     * @param Collection<int, int> $ids
     */
    private function personas(Collection $ids): int
    {
        $people = [];

        foreach ($ids as $id) {
            try {
                $person = (new Person($id))->getPerson();
            } catch (\Throwable $e) {
                $this->warn("Persona {$id}: {$e->getMessage()}");

                continue;
            }

            if (($person['id'] ?? null) !== null) {
                $people[] = $person;
                $this->line('👤 '.($person['name'] ?? "#{$id}"));
            }
        }

        if ($people !== []) {
            TmdbPerson::upsert($people, 'id');
        }

        return \count($people);
    }
}
