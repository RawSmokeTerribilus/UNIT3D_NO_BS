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

/**
 * MODIFICADO PARA NOBS
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
 * Se distribuye bajo la misma licencia, GNU AGPL v3.0.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Http\Livewire;

use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use App\Models\Type;
use App\Traits\LivewireSort;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MissingMediaSearch extends Component
{
    use LivewireSort;
    use WithPagination;

    #TODO: Update URL attributes once Livewire 3 fixes upstream bug. See: https://github.com/livewire/livewire/discussions/7746

    #[Url(history: true)]
    public string $name = '';

    #[Url(history: true)]
    public ?int $year = null;

    /**
     * `all`, `movie` o `tv`. Por defecto salen las dos cosas, que es lo que
     * uno espera de una página que se llama "media" y no "películas".
     */
    #[Url(history: true)]
    public string $kind = 'all';

    #[Url(history: true)]
    public string $sortField = 'created_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    #[Url(history: true)]
    public int $perPage = 50;

    /**
     * Películas Y series. Antes sólo consultaba `TmdbMovie`, así que las 1.169
     * series del catálogo eran invisibles en esta página: buscar "Mr" no
     * devolvía Mr. Robot aunque el tracker tenga doce torrents suyos. La
     * página decía que no había nada donde sí lo había.
     *
     * Se paginan las dos a la vez con un UNION sobre lo mínimo --id, tipo,
     * título, fecha y peticiones-- y sólo se hidratan los modelos de la página
     * que se está viendo. Cargar las 4.379 obras enteras para ordenar y
     * quedarse con cincuenta sería tirar la memoria.
     *
     * @var LengthAwarePaginator<int, TmdbMovie|TmdbTv>
     */
    final protected LengthAwarePaginator $medias {
        get {
            $peliculas = DB::table('tmdb_movies')
                ->select([
                    'id',
                    DB::raw("'movie' as kind"),
                    'title as titulo',
                    'release_date as fecha',
                    'created_at',
                ])
                ->selectSub($this->conteoPeticiones('tmdb_movie_id'), 'requests_count')
                ->when($this->name, fn ($q) => $q->where('title', 'LIKE', '%'.$this->name.'%'))
                ->when($this->year, fn ($q) => $q->where('release_date', 'LIKE', '%'.$this->year.'%'));

            $series = DB::table('tmdb_tv')
                ->select([
                    'id',
                    DB::raw("'tv' as kind"),
                    'name as titulo',
                    'first_air_date as fecha',
                    'created_at',
                ])
                ->selectSub($this->conteoPeticiones('tmdb_tv_id'), 'requests_count')
                ->when($this->name, fn ($q) => $q->where('name', 'LIKE', '%'.$this->name.'%'))
                ->when($this->year, fn ($q) => $q->where('first_air_date', 'LIKE', '%'.$this->year.'%'));

            $union = match ($this->kind) {
                'movie' => $peliculas,
                'tv'    => $series,
                default => $peliculas->unionAll($series),
            };

            // El nombre de la columna cambia al unir: la vista ordena por
            // `title`, que aquí se llama `titulo`.
            $campo = match ($this->sortField) {
                'title'          => 'titulo',
                'requests_count' => 'requests_count',
                default          => 'created_at',
            };

            $pagina = DB::query()
                ->fromSub($union, 'obras')
                ->orderBy($campo, $this->sortDirection)
                ->paginate($this->perPage);

            return $pagina->setCollection($this->hidratar(collect($pagina->items())));
        }
    }

    /**
     * Peticiones sin cubrir y sin reclamar de una obra, para poder ordenar por
     * ellas dentro del UNION. Con `withCount` no valdría: eso vive en Eloquent
     * y aquí se ordena en SQL, antes de hidratar nada.
     */
    private function conteoPeticiones(string $columna): \Illuminate\Database\Query\Builder
    {
        $tabla = $columna === 'tmdb_movie_id' ? 'tmdb_movies' : 'tmdb_tv';

        return DB::table('requests')
            ->selectRaw('COUNT(*)')
            ->whereColumn('requests.'.$columna, $tabla.'.id')
            ->whereNull('requests.torrent_id')
            ->whereNotExists(
                fn ($q) => $q->select(DB::raw(1))
                    ->from('request_claims')
                    ->whereColumn('request_claims.request_id', 'requests.id')
            );
    }

    /**
     * Cambia las filas planas del UNION por sus modelos, conservando el orden.
     *
     * Cada modelo sale con tres atributos añadidos que la vista usa por igual
     * para película y serie: `titulo`, `anyo` y `kind`. Sin ellos la vista
     * tendría que preguntar de qué clase es cada fila en cada celda.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $filas
     * @return \Illuminate\Support\Collection<int, TmdbMovie|TmdbTv>
     */
    private function hidratar(\Illuminate\Support\Collection $filas): \Illuminate\Support\Collection
    {
        $porTipo = $filas->groupBy('kind')->map(fn ($g) => $g->pluck('id')->all());

        $peliculas = empty($porTipo['movie']) ? collect() : TmdbMovie::query()
            ->with(['torrents:tmdb_movie_id,tmdb_tv_id,resolution_id,type_id' => ['resolution:id,position,name']])
            ->withMin('torrents', 'category_id')
            ->whereIn('id', $porTipo['movie'])
            ->get()
            ->keyBy('id');

        $series = empty($porTipo['tv']) ? collect() : TmdbTv::query()
            ->with(['torrents:tmdb_movie_id,tmdb_tv_id,resolution_id,type_id' => ['resolution:id,position,name']])
            ->withMin('torrents', 'category_id')
            ->whereIn('id', $porTipo['tv'])
            ->get()
            ->keyBy('id');

        return $filas
            ->map(function (object $fila) use ($peliculas, $series) {
                $modelo = $fila->kind === 'movie'
                    ? ($peliculas[$fila->id] ?? null)
                    : ($series[$fila->id] ?? null);

                if ($modelo === null) {
                    return null;
                }

                $modelo->setAttribute('kind', $fila->kind);
                $modelo->setAttribute('titulo', $fila->titulo);
                $modelo->setAttribute('anyo', $fila->fecha === null ? null : substr((string) $fila->fecha, 0, 4));
                $modelo->setAttribute('requests_count', (int) $fila->requests_count);

                return $modelo;
            })
            ->filter()
            ->values();
    }

    /**
     * Los tipos de vídeo, que son los únicos que tienen sentido como columna.
     *
     * Al añadir libros y juegos entraron diez tipos nuevos --EPUB, PDF, MOBI,
     * AZW3, CBZ/CBR, M4B, MP3, ScummVM, ROM y PC-- y esta tabla los pintaba en
     * las 3.210 filas de película: más de 32.000 celdas rojas afirmando que a
     * una película le "falta" el EPUB.
     *
     * Se filtra por `types.meta`, la columna que dice a qué clase de obra
     * pertenece cada tipo. Antes se deducía de los datos --qué tipos aparecen
     * en torrents de vídeo-- y eso tenía un fallo: un tipo de vídeo que nadie
     * hubiera subido todavía no salía como columna, y es justo el que más
     * falta. Con la columna, sale igual.
     *
     * Los que sigan a NULL entran también: sin clasificar significa "vale para
     * todo", y es mejor una columna de más que esconder algo en silencio.
     *
     * @var \Illuminate\Database\Eloquent\Collection<int, Type>
     */
    final protected \Illuminate\Database\Eloquent\Collection $types {
        get => Type::query()
            ->select(['id', 'position', 'name'])
            ->where(fn ($query) => $query->where('meta', '=', 'video')->orWhereNull('meta'))
            ->orderBy('position')
            ->get();
    }

    final public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.missing-media-search', ['medias' => $this->medias, 'types' => $this->types]);
    }
}
