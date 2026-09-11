<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Http\Livewire;

use App\Models\Category;
use App\Models\History;
use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use App\Models\Torrent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

class Trending extends Component
{
    #TODO: Update URL attributes once Livewire 3 fixes upstream bug. See: https://github.com/livewire/livewire/discussions/7746

    #[Url(history: true)]
    #[Validate('in:movie_meta,tv_meta,game_meta,book_meta,audiobook_meta')]
    public string $metaType = 'movie_meta';

    #[Url(history: true)]
    #[Validate('in:day,week,weekly,month,monthly,year,release_year,all,custom')]
    public string $interval = 'day';

    #[Url(history: true)]
    #[Validate('sometimes|date_format:Y-m-d')]
    public string $from = '' {
        set(string $value) {
            try {
                $this->from = Carbon::parse($value)->format('Y-m-d');
            } catch (Throwable) {
                $this->from = now()->subDay()->format('Y-m-d');
            }
        }
    }

    #[Url(history: true)]
    #[Validate('sometimes|date_format:Y-m-d')]
    public string $until = '' {
        set(string $value) {
            try {
                $this->until = Carbon::parse($value)->format('Y-m-d');
            } catch (Throwable) {
                $this->until = now()->format('Y-m-d');
            }
        }
    }

    /**
     * Cada clase de obra en una fila: la columna del torrent que la identifica,
     * la relación con su ficha, y de dónde sale el año de estreno.
     *
     * Existía sólo para película y serie, con la columna elegida a mano en cada
     * consulta. Libros, audiolibros y juegos no salían en ninguna lista porque
     * su id no es `tmdb_movie_id` ni `tmdb_tv_id`.
     *
     * @return array{columna: string, relacion: string, tabla: string, clave: string, fecha: string, esFecha: bool}
     */
    private function fuente(): array
    {
        return match ($this->metaType) {
            'tv_meta'        => ['columna' => 'tmdb_tv_id', 'relacion' => 'tv', 'tabla' => 'tmdb_tv', 'clave' => 'id', 'fecha' => 'tmdb_tv.first_air_date', 'esFecha' => true],
            'game_meta'      => ['columna' => 'igdb', 'relacion' => 'game', 'tabla' => 'igdb_games', 'clave' => 'id', 'fecha' => 'igdb_games.first_release_date', 'esFecha' => true],
            'book_meta'      => ['columna' => 'isbn13', 'relacion' => 'book', 'tabla' => 'books', 'clave' => 'isbn13', 'fecha' => 'books.first_publish_year', 'esFecha' => false],
            'audiobook_meta' => ['columna' => 'asin', 'relacion' => 'audiobook', 'tabla' => 'audiobooks', 'clave' => 'asin', 'fecha' => 'audiobooks.release_date', 'esFecha' => true],
            default          => ['columna' => 'tmdb_movie_id', 'relacion' => 'movie', 'tabla' => 'tmdb_movies', 'clave' => 'id', 'fecha' => 'tmdb_movies.release_date', 'esFecha' => true],
        };
    }

    /**
     * El año de estreno, agregado o no. `books` guarda un entero y las demás
     * una fecha, así que la expresión no puede ser una sola.
     */
    private function expresionAnio(bool $agregado = false): string
    {
        $fuente = $this->fuente();
        $columna = $agregado ? 'MAX('.$fuente['fecha'].')' : $fuente['fecha'];

        return $fuente['esFecha'] ? 'EXTRACT(YEAR FROM '.$columna.')' : $columna;
    }

    /**
     * El suelo de tamaño existe porque hay quien descarga cosas diminutas sólo
     * para granjear bonus, y eso ensucia la estadística. Sólo vale para vídeo:
     * aplicárselo a un e-book de 4 MB deja la lista vacía para siempre.
     */
    private function tamanoMinimo(int $bytesDeVideo): int
    {
        return \in_array($this->metaType, ['movie_meta', 'tv_meta'], true) ? $bytesDeVideo : 0;
    }

    /**
     * @var Collection<int, Torrent>
     */
    final protected Collection $works {
        get {
            $this->validate();

            $metaIdColumn = $this->fuente()['columna'];

            return cache()->flexible(
                'trending-'.$this->interval.'-'.($this->from ?? '').'-'.($this->until ?? '').'-'.$this->metaType,
                [1800, 7200],
                fn () => Torrent::query()
                    ->with($this->fuente()['relacion'])
                    ->addSelect([
                        $metaIdColumn,
                        DB::raw('MIN(category_id) as category_id'),
                        DB::raw('COUNT(*) as download_count'),
                    ])
                    ->join('history', 'history.torrent_id', '=', 'torrents.id')
                    ->whereNotNull($metaIdColumn)->where($metaIdColumn, '!=', 0)->where($metaIdColumn, '!=', '')
                    ->when($this->interval === 'day', fn ($query) => $query->whereBetween('history.completed_at', [now()->subDay(), now()]))
                    ->when($this->interval === 'week', fn ($query) => $query->whereBetween('history.completed_at', [now()->subWeek(), now()]))
                    ->when($this->interval === 'month', fn ($query) => $query->whereBetween('history.completed_at', [now()->subMonth(), now()]))
                    ->when($this->interval === 'year', fn ($query) => $query->whereBetween('history.completed_at', [now()->subYear(), now()]))
                    ->when($this->interval === 'all', fn ($query) => $query->whereNotNull('history.completed_at'))
                    ->when($this->interval === 'custom', fn ($query) => $query->whereBetween('history.completed_at', [$this->from ?: now(), $this->until ?: now()]))
                    ->whereRelation('category', $this->metaType, '=', true)
                    // Small torrents screw the stats since users download them only to farm bon.
                    ->where('torrents.size', '>', $this->tamanoMinimo(1024 * 1024 * 1024))
                    ->groupBy($metaIdColumn)
                    ->orderByRaw('COUNT(*) DESC')
                    ->limit(250)
                    ->get($metaIdColumn)
            );
        }
    }

    /**
     * @var Collection<int|string, Collection<int, Torrent>>
     * @phpstan-ignore generics.notSubtype (I can't figure out the correct return type to silence this error)
     */
    final protected Collection $weekly {
        get {
            $this->validate();

            $metaIdColumn = $this->fuente()['columna'];

            return cache()->flexible(
                'weekly-charts:'.$this->metaType,
                [24 * 3600, 4 * 24 * 3600],
                fn () => Torrent::query()
                    ->withoutGlobalScopes()
                    ->with($this->fuente()['relacion'])
                    ->fromSub(
                        History::query()
                            ->withoutGlobalScopes()
                            ->join('torrents', 'torrents.id', '=', 'history.torrent_id')
                            ->join('categories', fn (JoinClause $join) => $join->on('torrents.category_id', '=', 'categories.id')->where($this->metaType, '=', true))
                            ->select([
                                DB::raw('FROM_DAYS(TO_DAYS(history.created_at) - MOD(TO_DAYS(history.created_at) - 1, 7)) AS week_start'),
                                $metaIdColumn,
                                DB::raw('MIN(categories.id) as category_id'),
                                DB::raw('COUNT(*) AS download_count'),
                                DB::raw('ROW_NUMBER() OVER (PARTITION BY FROM_DAYS(TO_DAYS(history.created_at) - MOD(TO_DAYS(history.created_at) - 1, 7)) ORDER BY COUNT(*) DESC) AS place'),
                            ])
                            ->whereNotNull($metaIdColumn)->where($metaIdColumn, '!=', 0)->where($metaIdColumn, '!=', '')
                            // Small torrents screw the stats since users download them only to farm bon.
                            ->where('torrents.size', '>', $this->tamanoMinimo(1024 * 1024 * 1024))
                            ->groupBy('week_start', $metaIdColumn),
                        'ranked_groups',
                    )
                    ->where('place', '<=', 10)
                    ->orderByDesc('week_start')
                    ->orderBy('place')
                    ->withCasts([
                        'week_start' => 'datetime',
                    ])
                    ->get()
                    ->groupBy('week_start')
            );
        }
    }

    /**
     * @var Collection<int|string, Collection<int, Torrent>>
     * @phpstan-ignore generics.notSubtype (I can't figure out the correct return type to silence this error)
     */
    final protected Collection $monthly {
        get {
            $this->validate();

            $metaIdColumn = $this->fuente()['columna'];

            return cache()->flexible(
                'monthly-charts:'.$this->metaType,
                [24 * 3600, 4 * 24 * 3600],
                fn () => Torrent::query()
                    ->withoutGlobalScopes()
                    ->with($this->fuente()['relacion'])
                    ->fromSub(
                        History::query()
                            ->withoutGlobalScopes()
                            ->join('torrents', 'torrents.id', '=', 'history.torrent_id')
                            ->join('categories', fn (JoinClause $join) => $join->on('torrents.category_id', '=', 'categories.id')->where($this->metaType, '=', true))
                            ->select([
                                DB::raw('EXTRACT(YEAR_MONTH FROM history.created_at) AS the_year_month'),
                                $metaIdColumn,
                                DB::raw('MIN(categories.id) as category_id'),
                                DB::raw('COUNT(*) AS download_count'),
                                DB::raw('ROW_NUMBER() OVER (PARTITION BY EXTRACT(YEAR_MONTH FROM history.created_at) ORDER BY COUNT(*) DESC) AS place'),
                            ])
                            ->whereNotNull($metaIdColumn)->where($metaIdColumn, '!=', 0)->where($metaIdColumn, '!=', '')
                            // Small torrents screw the stats since users download them only to farm bon.
                            ->where('torrents.size', '>', $this->tamanoMinimo(1024 * 1024 * 1024))
                            ->groupBy('the_year_month', $metaIdColumn),
                        'ranked_groups',
                    )
                    ->where('place', '<=', 10)
                    ->orderByDesc('the_year_month')
                    ->orderBy('place')
                    ->get()
                    ->groupBy('the_year_month')
            );
        }
    }

    /**
     * @var Collection<int|string, Collection<int, Torrent>>
     * @phpstan-ignore generics.notSubtype (I can't figure out the correct return type to silence this error)
     */
    final protected Collection $releaseYear {
        get {
            $this->validate();

            $fuente = $this->fuente();
            $metaIdColumn = $fuente['columna'];

            return cache()->flexible(
                'trending-by-release-year:'.$this->metaType,
                [24 * 3600, 4 * 24 * 3600],
                fn () => Torrent::query()
                    ->withoutGlobalScopes()
                    ->with($this->fuente()['relacion'])
                    ->fromSub(
                        Torrent::query()
                            ->withoutGlobalScopes()
                            ->whereRelation('category', $this->metaType, '=', true)
                            // Una sola tabla, la de la clase de obra que se está
                            // mirando, en vez de los dos leftJoin fijos a TMDB
                            // con su COALESCE: así el año sale igual de un libro
                            // que de una película, y la consulta no arrastra
                            // tablas que no pinta nada en esta pestaña.
                            ->join($fuente['tabla'], 'torrents.'.$metaIdColumn, '=', $fuente['tabla'].'.'.$fuente['clave'])
                            ->select([
                                'torrents.'.$metaIdColumn,
                                DB::raw('MIN(category_id) as category_id'),
                                DB::raw('SUM(times_completed) AS download_count'),
                                DB::raw($this->expresionAnio().' AS the_year'),
                                DB::raw('ROW_NUMBER() OVER (PARTITION BY '.$this->expresionAnio(true).' ORDER BY SUM(times_completed) DESC) AS place'),
                            ])
                            ->whereNotNull('torrents.'.$metaIdColumn)->where('torrents.'.$metaIdColumn, '!=', 0)->where('torrents.'.$metaIdColumn, '!=', '')
                            // Small torrents screw the stats since users download them only to farm bon.
                            ->where('torrents.size', '>', $this->tamanoMinimo(2 * 1024 * 1024 * 1024))
                            ->when($this->metaType === 'tv_meta', fn ($query) => $query->where('episode_number', '=', 0))
                            ->havingNotNull('the_year')
                            ->groupBy('the_year', 'torrents.'.$metaIdColumn),
                        'ranked_groups',
                    )
                    ->where('place', '<=', 10)
                    ->orderByDesc('the_year')
                    ->orderBy('place')
                    ->get()
                    ->groupBy('the_year')
            );
        }
    }

    /**
     * @var array<string, string>
     */
    final protected array $metaTypes {
        get {
            $metaTypes = [];

            if (Category::where('movie_meta', '=', true)->exists()) {
                $metaTypes[(string) __('mediahub.movie')] = 'movie_meta';
            }

            if (Category::where('tv_meta', '=', true)->exists()) {
                $metaTypes[(string) __('mediahub.show')] = 'tv_meta';
            }

            // Las tres clases nuevas sólo aparecen si el sitio tiene categorías
            // suyas, igual que las dos de vídeo: un tracker sin libros no gana
            // nada con una pestaña vacía.
            if (Category::where('game_meta', '=', true)->exists()) {
                $metaTypes['Juegos'] = 'game_meta';
            }

            if (Category::where('book_meta', '=', true)->exists()) {
                $metaTypes['Libros'] = 'book_meta';
            }

            if (Category::where('audiobook_meta', '=', true)->exists()) {
                $metaTypes['Audiolibros'] = 'audiobook_meta';
            }

            return $metaTypes;
        }
    }

    final public function placeholder(): string
    {
        return <<<'HTML'
        <section class="panelV2">
            <h2 class="panel__heading">Top Titles</h2>
            <div class="panel__body">Loading...</div>
        </section>
        HTML;
    }

    final public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.trending', [
            'user'  => auth()->user(),
            'works' => match ($this->interval) {
                'weekly'       => $this->weekly,
                'monthly'      => $this->monthly,
                'release_year' => $this->releaseYear,
                default        => $this->works,
            },
            'metaTypes' => $this->metaTypes,
        ]);
    }
}
