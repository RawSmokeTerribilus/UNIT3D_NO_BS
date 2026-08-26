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

use App\DTO\TorrentSearchFiltersDTO;
use App\Models\Category;
use App\Models\Distributor;
use App\Models\History;
use App\Models\Audiobook;
use App\Models\Book;
use App\Models\IgdbGame;
use App\Models\PlaylistCategory;
use App\Models\TmdbMovie;
use App\Models\Region;
use App\Models\Resolution;
use App\Models\Torrent;
use App\Models\TorrentRequest;
use App\Models\TmdbTv;
use App\Models\Type;
use App\Models\User;
use App\Notifications\TorrentsDeleted;
use App\Services\Unit3dAnnounce;
use App\Traits\CastLivewireProperties;
use App\Traits\LivewireSort;
use App\Traits\TorrentMeta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Url;
use Livewire\Component;

class SimilarTorrent extends Component
{
    use CastLivewireProperties;
    use LivewireSort;
    use TorrentMeta;

    public Category $category;

    public TmdbMovie|TmdbTv|IgdbGame|Book|Audiobook $work;

    public ?int $tmdbId;

    public ?int $igdbId;

    /**
     * El ISBN-13 de la obra, para agrupar libros y audiolibros.
     *
     * Va como cadena y no como entero a propósito: son trece dígitos con
     * ceros que importan, y un `int` se comería un ISBN que empiece por cero.
     */
    public ?string $isbn13 = null;

    public string $reason;

    #[Url(history: true)]
    public string $name = '';

    #[Url(history: true)]
    public string $description = '';

    #[Url(history: true)]
    public string $mediainfo = '';

    #[Url(history: true)]
    public string $uploader = '';

    #[Url(history: true)]
    public string $keywords = '';

    #[Url(history: true)]
    public ?int $minSize = null;

    #[Url(history: true)]
    public int $minSizeMultiplier = 1;

    #[Url(history: true)]
    public ?int $maxSize = null;

    #[Url(history: true)]
    public int $maxSizeMultiplier = 1;

    #[Url(history: true)]
    public ?int $episodeNumber = null;

    #[Url(history: true)]
    public ?int $seasonNumber = null;

    /**
     * @var array<int>
     */
    #[Url(history: true)]
    public array $typeIds = [];

    /**
     * @var array<int>
     */
    #[Url(history: true)]
    public array $resolutionIds = [];

    /**
     * @var array<int>
     */
    #[Url(history: true)]
    public array $regionIds = [];

    /**
     * @var array<int>
     */
    #[Url(history: true)]
    public array $distributorIds = [];

    #[Url(history: true)]
    public string $adult = 'any';

    #[Url(history: true)]
    public ?int $playlistId = null;

    /**
     * @var string[]
     */
    #[Url(history: true)]
    public array $free = [];

    #[Url(history: true)]
    public bool $doubleup = false;

    #[Url(history: true)]
    public bool $featured = false;

    #[Url(history: true)]
    public bool $refundable = false;

    #[Url(history: true)]
    public bool $highspeed = false;

    #[Url(history: true)]
    public bool $bookmarked = false;

    #[Url(history: true)]
    public bool $wished = false;

    #[Url(history: true)]
    public bool $internal = false;

    #[Url(history: true)]
    public bool $personalRelease = false;

    #[Url(history: true)]
    public bool $trumpable = false;

    #[Url(history: true)]
    public bool $alive = false;

    #[Url(history: true)]
    public bool $dying = false;

    #[Url(history: true)]
    public bool $dead = false;

    #[Url(history: true)]
    public bool $graveyard = false;

    #[Url(history: true)]
    public bool $notDownloaded = false;

    #[Url(history: true)]
    public bool $downloaded = false;

    #[Url(history: true)]
    public bool $seeding = false;

    #[Url(history: true)]
    public bool $leeching = false;

    #[Url(history: true)]
    public bool $incomplete = false;

    #TODO: Update URL attributes once Livewire 3 fixes upstream bug. See: https://github.com/livewire/livewire/discussions/7746

    /**
     * @var array<int, bool>
     */
    public array $checked = [];

    public bool $selectPage = false;

    public bool $hideFilledRequests = true;

    #[Url(history: true)]
    public string $sortField = 'bumped_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    /**
     * @var array<string>
     */
    protected $listeners = [
        'destroy' => 'deleteRecords'
    ];

    final public function boot(): void
    {
        $this->work->setAttribute('meta', match ($this->work::class) {
            TmdbMovie::class => 'movie',
            TmdbTv::class    => 'tv',
            IgdbGame::class  => 'game',
            // Los dos comparten etiqueta: se agrupan por el ISBN de la obra,
            // así que el e-book y el audiolibro del mismo libro caen en el
            // mismo cajón, que es justo lo que se quiere.
            Book::class      => 'book',
            Audiobook::class => 'book',
        });
    }

    final public function updating(string $field, mixed &$value): void
    {
        $this->castLivewireProperties($field, $value);
    }

    final public function updatedSelectPage(bool $value): void
    {
        $this->checked = $value ? collect($this->torrents)->flatten()->pluck('id')->toArray() : [];
    }

    final public function updatedChecked(): void
    {
        $this->selectPage = false;
    }

    /**
     * @var array<string, non-empty-list<Torrent>>|array{
     *         'Complete Pack'?: non-empty-array<string, non-empty-list<Torrent>>,
     *         'Specials'?: non-empty-array<string, array<string, non-empty-list<Torrent>>>,
     *         'Seasons'?: non-empty-array<string, array{
     *             'Season Pack': non-empty-array<string, non-empty-list<Torrent>>,
     *             'Episodes': non-empty-array<string, array<string, non-empty-list<Torrent>>>,
     *         }>,
     *         category_id?: int,
     *     }
     * }
     */
    final protected array $torrents {
        get {
            $user = auth()->user();

            $torrents = Torrent::query()
                ->with('type:id,name,position', 'resolution:id,name,position')
                ->withCount([
                    'comments',
                ])
                ->when(
                    !config('announce.external_tracker.is_enabled'),
                    fn ($query) => $query->withCount([
                        'seeds'   => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
                        'leeches' => fn ($query) => $query->where('active', '=', true)->where('visible', '=', true),
                    ]),
                )
                ->withExists([
                    'featured as featured',
                    'freeleechTokens'    => fn ($query) => $query->where('user_id', '=', auth()->id()),
                    'bookmarks'          => fn ($query) => $query->where('user_id', '=', $user->id),
                    'history as seeding' => fn ($query) => $query->where('user_id', '=', $user->id)
                        ->where('active', '=', 1)
                        ->where('seeder', '=', 1),
                    'history as leeching' => fn ($query) => $query->where('user_id', '=', $user->id)
                        ->where('active', '=', 1)
                        ->where('seeder', '=', 0),
                    'history as completed' => fn ($query) => $query->where('user_id', '=', $user->id)
                        ->where('active', '=', 0)
                        ->where('seeder', '=', 1),
                    'trump',
                ])
                ->when(
                    $this->category->movie_meta,
                    fn ($query) => $query
                        ->whereRelation('category', 'movie_meta', '=', true)
                        ->where('tmdb_movie_id', '=', $this->tmdbId)
                        ->selectRaw("'movie' as meta"),
                )
                ->when(
                    $this->category->tv_meta,
                    fn ($query) => $query
                        ->whereRelation('category', 'tv_meta', '=', true)
                        ->where('tmdb_tv_id', '=', $this->tmdbId)
                        ->selectRaw("'tv' as meta"),
                )
                ->when(
                    $this->category->game_meta,
                    fn ($query) => $query
                        ->whereRelation('category', 'game_meta', '=', true)
                        ->where('igdb', '=', $this->igdbId)
                        ->selectRaw("'game' as meta"),
                )
                // Libros y audiolibros se agrupan por el ISBN de la OBRA, así
                // que un audiolibro que lo lleve sale junto al e-book del
                // mismo libro. Es lo que uno espera de "similares".
                ->when(
                    $this->category->book_meta || $this->category->audiobook_meta,
                    fn ($query) => $query
                        ->where('isbn13', '=', $this->isbn13)
                        ->selectRaw("'book' as meta"),
                )
                ->where((new TorrentSearchFiltersDTO(
                    name: $this->name,
                    description: $this->description,
                    mediainfo: $this->mediainfo,
                    keywords: $this->keywords ? array_map('trim', explode(',', $this->keywords)) : [],
                    uploader: $this->uploader,
                    episodeNumber: $this->episodeNumber,
                    seasonNumber: $this->seasonNumber,
                    minSize: $this->minSize === null ? null : $this->minSize * $this->minSizeMultiplier,
                    maxSize: $this->maxSize === null ? null : $this->maxSize * $this->maxSizeMultiplier,
                    playlistId: $this->playlistId,
                    typeIds: $this->typeIds,
                    resolutionIds: $this->resolutionIds,
                    free: $this->free,
                    doubleup: $this->doubleup,
                    featured: $this->featured,
                    refundable: $this->refundable,
                    internal: $this->internal,
                    personalRelease: $this->personalRelease,
                    trumpable: $this->trumpable,
                    highspeed: $this->highspeed,
                    userBookmarked: $this->bookmarked,
                    userWished: $this->wished,
                    alive: $this->alive,
                    dying: $this->dying,
                    dead: $this->dead,
                    graveyard: $this->graveyard,
                    userDownloaded: match (true) {
                        $this->downloaded    => true,
                        $this->notDownloaded => false,
                        default              => null,
                    },
                    userSeeder: match (true) {
                        $this->seeding  => true,
                        $this->leeching => false,
                        default         => null,
                    },
                    userActive: match (true) {
                        $this->seeding  => true,
                        $this->leeching => true,
                        default         => null,
                    },
                ))->toSqlQueryBuilder())
                ->orderBy($this->sortField, $this->sortDirection)
                ->get();

            return match ($this->work::class) {
                TmdbMovie::class => self::groupTorrents($torrents)['movie'][$this->tmdbId]['Movie'] ?? [],
                TmdbTv::class    => self::groupTorrents($torrents)['tv'][$this->tmdbId] ?? [],
                IgdbGame::class  => self::groupTorrents($torrents)['game'][$this->igdbId]['Game'] ?? [],
                Book::class      => self::groupTorrents($torrents)['book'][$this->isbn13] ?? [],
                Audiobook::class => self::groupTorrents($torrents)['book'][$this->isbn13] ?? [],
            };
        }
    }

    /**
     * @var \Illuminate\Database\Eloquent\Collection<int, TorrentRequest>
     */
    final protected \Illuminate\Database\Eloquent\Collection $torrentRequests {
        get => TorrentRequest::with(['user:id,username,group_id', 'user.group', 'category', 'type', 'resolution'])
            ->withCount(['comments'])
            ->withExists('claim')
            ->when($this->category->movie_meta, fn ($query) => $query->where('tmdb_movie_id', '=', $this->tmdbId))
            ->when($this->category->tv_meta, fn ($query) => $query->where('tmdb_tv_id', '=', $this->tmdbId))
            ->when($this->category->game_meta, fn ($query) => $query->where('igdb', '=', $this->igdbId))
            ->when(
                $this->category->book_meta || $this->category->audiobook_meta,
                fn ($query) => $query->where('isbn13', '=', $this->isbn13)
            )
            ->where('category_id', '=', $this->category->id)
            ->when(
                $this->hideFilledRequests,
                fn ($query) => $query->where(fn ($query) => $query->whereNull('torrent_id')->orWhereNull('approved_when'))
            )
            ->latest()
            ->get();
    }

    /**
     * @var \Illuminate\Database\Eloquent\Collection<int, PlaylistCategory>
     */
    final protected \Illuminate\Database\Eloquent\Collection $playlistCategories {
        get => PlaylistCategory::query()
            ->with([
                'playlists' => fn ($query) => $query
                    ->withCount('torrents')
                    ->when(
                        ! auth()->user()->group->is_modo,
                        fn ($query) => $query
                            ->where(
                                fn ($query) => $query
                                    ->where('is_private', '=', 0)
                                    ->orWhere(fn ($query) => $query->where('is_private', '=', 1)->where('user_id', '=', auth()->id()))
                            )
                    )
                    ->when($this->category->movie_meta, fn ($query) => $query->whereRelation('torrents', 'tmdb_movie_id', '=', $this->tmdbId))
                    ->when($this->category->tv_meta, fn ($query) => $query->whereRelation('torrents', 'tmdb_tv_id', '=', $this->tmdbId))
                    ->when($this->category->game_meta, fn ($query) => $query->whereRelation('torrents', 'igdb', '=', $this->igdbId))
                    ->when(!($this->category->movie_meta || $this->category->tv_meta || $this->category->game_meta), fn ($query) => $query->whereRaw('0 = 1'))
            ])
            ->orderBy('position')
            ->get()
            ->filter(fn ($category) => $category->playlists->isNotEmpty());
    }

    /**
     * @var ?\Illuminate\Database\Eloquent\Collection<int, TmdbMovie>
     */
    final protected ?\Illuminate\Database\Eloquent\Collection $collectionMovies {
        // `withMin` + `has` es lo mismo que hace la ficha del torrent con
        // `collections.movies`: la categoría la pone cada peli --una entrega
        // puede vivir en "Anime Movies" y la siguiente en "Movies"--, y las
        // que no tienen torrent no se pintan, porque su enlace a similares
        // sería un 404 fijo.
        get => $this->work instanceof TmdbMovie
            ? $this->work->collections()->first()?->movies()->withMin('torrents', 'category_id')->has('torrents')->get()
            : null;
    }

    /**
     * Las demás obras del mismo autor o de la misma saga, con torrent.
     *
     * El equivalente de `collectionMovies` para libros. En vídeo la colección
     * la da TMDB ya montada; aquí hay que cruzarla, porque la saga y el autor
     * viven en dos tablas distintas y ninguna de las dos es obligatoria.
     *
     * Se cruza por las DOS y se unen los resultados: la saga es lo que uno
     * espera --abrir «Alas de fuego» y ver «Alas negras»-- pero está vacía en
     * casi todo el catálogo, porque ni Audnexus ni Google Books la devuelven
     * para las ediciones en castellano (medido: `seriesPrimary: null` en los
     * tres audiolibros de prod). El autor sí llega siempre, así que es el
     * cruce que de verdad sostiene el bloque hoy.
     *
     * Sólo salen obras que tengan torrent: el destino es la página de
     * similares y ésa aborta con 404 cuando no hay ninguno, así que sin este
     * filtro el bloque pintaría enlaces muertos.
     *
     * @var \Illuminate\Support\Collection<string, Audiobook|Book>
     */
    final protected \Illuminate\Support\Collection $relatedWorks {
        get {
            if (!($this->category->book_meta || $this->category->audiobook_meta)) {
                return collect();
            }

            /** @var array<int, string> $olids */
            $olids = $this->work->bookAuthors()->pluck('book_authors.olid')->all();
            $seriesId = $this->work->book_series_id;

            if ($olids === [] && $seriesId === null) {
                return collect();
            }

            $porAutorOSaga = function ($query, string $tablaPivote, string $clave) use ($olids, $seriesId) {
                $query->where(function ($query) use ($olids, $seriesId, $tablaPivote, $clave): void {
                    if ($olids !== []) {
                        $query->orWhereIn(
                            $clave,
                            DB::table($tablaPivote)->select($clave)->whereIn('author_olid', $olids)
                        );
                    }

                    if ($seriesId !== null) {
                        $query->orWhere('book_series_id', '=', $seriesId);
                    }
                });
            };

            $isbn13s = Book::query()->tap(fn ($q) => $porAutorOSaga($q, 'book_author', 'isbn13'))->pluck('isbn13')->all();
            $asins = Audiobook::query()->tap(fn ($q) => $porAutorOSaga($q, 'audiobook_author', 'asin'))->pluck('asin')->all();

            if ($isbn13s === [] && $asins === []) {
                return collect();
            }

            // Se parte de los TORRENTS y no de las obras porque de aquí salen
            // las tres cosas que hacen falta: que la obra exista en el
            // tracker, en qué categoría vive --un e-book y su audiolibro no
            // están en la misma-- y a dónde enlazar.
            $torrents = Torrent::query()
                ->select('id', 'category_id', 'isbn13', 'asin')
                ->with('category:id,book_meta,audiobook_meta')
                ->where(function ($query) use ($isbn13s, $asins): void {
                    if ($isbn13s !== []) {
                        $query->orWhereIn('isbn13', $isbn13s);
                    }

                    if ($asins !== []) {
                        $query->orWhereIn('asin', $asins);
                    }
                })
                ->oldest('id')
                ->get();

            $libros = $isbn13s === [] ? collect() : Book::query()->whereIn('isbn13', $isbn13s)->get()->keyBy('isbn13');
            $audiolibros = $asins === [] ? collect() : Audiobook::query()->whereIn('asin', $asins)->get()->keyBy('asin');

            $actual = $this->work instanceof Audiobook
                ? 'a:'.$this->work->asin
                : 'b:'.$this->work->isbn13;

            $obras = collect();

            foreach ($torrents as $torrent) {
                $esAudio = (bool) $torrent->category?->audiobook_meta;

                // La obra se resuelve igual que en la ficha del torrent:
                // primero el audiolibro por su ASIN y, si no hay, el libro
                // por el ISBN. Un audiolibro de lectura libre no tiene fila
                // en `audiobooks` --no tiene ASIN, sólo el ISBN de la obra--
                // y buscarlo únicamente por ASIN lo dejaba fuera del bloque.
                $obra = null;
                $clave = null;

                if ($esAudio && $torrent->asin !== null && isset($audiolibros[$torrent->asin])) {
                    $obra = $audiolibros[$torrent->asin];
                    $clave = 'a:'.$torrent->asin;
                } elseif ($torrent->isbn13 !== null && isset($libros[$torrent->isbn13])) {
                    $obra = $libros[$torrent->isbn13];
                    $clave = 'b:'.$torrent->isbn13;
                }

                if ($obra === null || $clave === $actual || $obras->has($clave)) {
                    continue;
                }

                // Un audiolibro sin ISBN de obra no tiene página de similares
                // --ésa agrupa por ISBN-- así que se enlaza su torrent, que es
                // mejor que esconderlo. Es el caso de los que vienen de
                // Audible con ASIN y nada más.
                $obra->setAttribute('enlace', $torrent->isbn13 === null
                    ? route('torrents.show', ['id' => $torrent->id])
                    : route('torrents.similar', ['category_id' => $torrent->category_id, 'tmdb' => $torrent->isbn13]));

                $obras->put($clave, $obra);
            }

            return $obras;
        }
    }

    final public function alertConfirm(): void
    {
        if (!auth()->user()->group->is_modo) {
            $this->dispatch('error', type: 'error', message: 'Permission denied!');

            return;
        }

        $torrents = Torrent::whereKey($this->checked)->pluck('name')->toArray();
        $names = $torrents;
        $this->dispatch(
            'swal:confirm',
            type: 'warning',
            message: 'Are you sure?',
            body: 'If deleted, you will not be able to recover the following files!'.nl2br("\n")
                        .nl2br(implode("\n", $names)),
        );
    }

    final public function deleteRecords(): void
    {
        if (!auth()->user()->group->is_modo) {
            $this->dispatch('error', type: 'error', message: 'Permission denied!');

            return;
        }

        $torrents = Torrent::whereKey($this->checked)->get();
        $users = [];
        $title = match (true) {
            $this->category->movie_meta => ($movie = TmdbMovie::find($this->tmdbId))->title.($movie->release_date === null ? '' : ' ('.$movie->release_date->format('Y').')'),
            $this->category->tv_meta    => ($tv = TmdbTv::find($this->tmdbId))->name.($tv->first_air_date === null ? '' : ' ('.$tv->first_air_date->format('Y').')'),
            $this->category->game_meta  => ($game = IgdbGame::find($this->igdbId))->name.($game->first_release_date === null ? '' : ' ('.$game->first_release_date->format('Y').')'),
            default                     => $torrents->pluck('name')->join(', '),
        };

        foreach ($torrents as $torrent) {
            foreach (History::where('torrent_id', '=', $torrent->id)->get() as $pm) {
                if (!\in_array($pm->user_id, $users)) {
                    $users[] = $pm->user_id;
                }
            }

            // Reset Requests
            $torrent->requests()->whereNull('approved_when')->update([
                'torrent_id' => null,
            ]);

            //Remove Torrent related info
            cache()->forget(\sprintf('torrent:%s', $torrent->info_hash));

            $torrent->comments()->delete();
            $torrent->peers()->delete();
            $torrent->history()->delete();
            $torrent->warnings()->delete();
            $torrent->files()->delete();
            $torrent->playlists()->detach();
            $torrent->subtitles()->delete();
            $torrent->resurrections()->delete();
            $torrent->featured()->delete();

            $freeleechTokens = $torrent->freeleechTokens();

            foreach ($freeleechTokens->get() as $freeleechToken) {
                cache()->forget('freeleech_token:'.$freeleechToken->user_id.':'.$torrent->id);
            }

            $freeleechTokens->delete();

            cache()->forget('announce-torrents:by-infohash:'.$torrent->info_hash);

            Unit3dAnnounce::removeTorrent($torrent);

            $torrent->delete();
        }

        Notification::send(
            array_map(fn ($userId) => new User(['id' => $userId]), $users),
            new TorrentsDeleted($torrents, $title, $this->reason)
        );

        $this->checked = [];
        $this->selectPage = false;

        $this->dispatch(
            'swal:modal',
            type: 'success',
            message: 'Torrents deleted successfully!',
            text: 'A personal message has been sent to all users that have downloaded these torrents.',
        );
    }

    final protected bool $personalFreeleech {
        get => cache()->get('personal_freeleech:'.auth()->id()) ?? false;
    }

    /**
     * @var \Illuminate\Database\Eloquent\Collection<int, Type>
     */
    final protected \Illuminate\Database\Eloquent\Collection $types {
        get => cache()->flexible(
            'types',
            [3600, 3600 * 2],
            fn () => Type::query()->orderBy('position')->get(),
        );
    }

    /**
     * @var \Illuminate\Database\Eloquent\Collection<int, Resolution>
     */
    final protected \Illuminate\Database\Eloquent\Collection $resolutions {
        get => cache()->flexible(
            'resolutions',
            [3600, 3600 * 2],
            fn () => Resolution::query()->orderBy('position')->get(),
        );
    }

    /**
     * @var \Illuminate\Database\Eloquent\Collection<int, Region>
     */
    final protected \Illuminate\Database\Eloquent\Collection $regions {
        get => cache()->flexible(
            'regions',
            [3600, 3600 * 2],
            fn () => Region::query()->orderBy('position')->get(),
        );
    }

    /**
     * @var \Illuminate\Database\Eloquent\Collection<int, Distributor>
     */
    final protected \Illuminate\Database\Eloquent\Collection $distributors {
        get => cache()->flexible(
            'distributors',
            [3600, 3600 * 2],
            fn () => Distributor::query()->orderBy('name')->get(),
        );
    }

    final public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.similar-torrent', [
            'user'               => auth()->user(),
            'similarTorrents'    => $this->torrents,
            'personalFreeleech'  => $this->personalFreeleech,
            'torrentRequests'    => $this->torrentRequests,
            'media'              => $this->work,
            'types'              => $this->types,
            'resolutions'        => $this->resolutions,
            'regions'            => $this->regions,
            'distributors'       => $this->distributors,
            'playlistCategories' => $this->playlistCategories,
            'collectionMovies'   => $this->collectionMovies,
            'relatedWorks'       => $this->relatedWorks,
        ]);
    }
}
