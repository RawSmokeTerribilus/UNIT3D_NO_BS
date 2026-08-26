<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D is open-sourced software licensed under the GNU General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 * @author     HDVinnie
 */

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Group;
use App\Models\History;
use App\Models\Post;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\TorrentRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class YearlyOverviewController extends Controller
{
    /** Si el año que se está mirando aún no ha terminado. */
    private bool $anioEnCurso = false;

    /**
     * Un año cerrado ya no cambia, así que se guarda para siempre. El año en
     * curso sí cambia --cada descarga lo mueve--, y guardarlo para siempre
     * congelaría un recuento a medias la primera vez que alguien entre.
     *
     * @param  \Closure(): mixed  $consulta
     */
    private function recordar(string $clave, \Closure $consulta): mixed
    {
        return $this->anioEnCurso
            ? cache()->remember($clave, 3600, $consulta)
            : cache()->rememberForever($clave, $consulta);
    }

    /**
     * Get All Overviews.
     */
    public function index(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        // Del año en curso hacia atrás hasta el nacimiento del sitio. Antes
        // arrancaba en `now()->subYear()`, así que un tracker nacido este mismo
        // año ofrecía el anterior --anterior a su propia existencia-- y esa
        // entrada daba 404 sí o sí.
        return view('stats.yearly-overviews.index', [
            'siteYears' => range(now()->year, Carbon::parse(config('other.birthdate'))->year),
        ]);
    }

    /**
     * Get A Year Overview.
     */
    public function show(int $year): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        // Year Validation
        $currentYear = now()->year;
        $birthYear = Carbon::parse(config('other.birthdate'))->year;

        // El año en curso también se puede mirar. Con `<` el primer resumen de
        // un tracker nacido este año no llegaría hasta enero del siguiente, y
        // mientras tanto la página entera --incluido su índice-- era un 404.
        abort_unless($birthYear <= $year && $year <= $currentYear, 404);

        $this->anioEnCurso = $year === $currentYear;

        return view('stats.yearly-overviews.show', [
            'topMovies' => $this->recordar(
                'yearly-overview:'.$year.':top-movies',
                fn () => Torrent::with('movie')
                    ->select([
                        'tmdb_movie_id',
                        DB::raw('COUNT(h.user_id) as download_count'),
                        DB::raw('MIN(category_id) as category_id'),
                    ])
                    ->leftJoinSub(
                        History::query()
                            ->whereNotNull('completed_at')
                            ->where('history.created_at', '>=', $year.'-01-01 00:00:00')
                            ->where('history.created_at', '<=', $year.'-12-31 23:59:59'),
                        'h',
                        fn ($join) => $join->on('torrents.id', '=', 'h.torrent_id')
                    )
                    ->where('tmdb_movie_id', '!=', 0)
                    ->whereNotNull('tmdb_movie_id')
                    ->whereRelation('category', 'movie_meta', '=', true)
                    ->groupBy('tmdb_movie_id')
                    ->orderByDesc('download_count')
                    ->take(10)
                    ->get()
            ),
            'bottomMovies' => $this->recordar(
                'yearly-overview:'.$year.':bottom-movies',
                fn () => Torrent::with('movie')
                    ->select([
                        'tmdb_movie_id',
                        DB::raw('COUNT(h.user_id) as download_count'),
                        DB::raw('MIN(category_id) as category_id'),
                    ])
                    ->leftJoinSub(
                        History::query()
                            ->whereNotNull('completed_at')
                            ->where('history.created_at', '>=', $year.'-01-01 00:00:00')
                            ->where('history.created_at', '<=', $year.'-12-31 23:59:59'),
                        'h',
                        fn ($join) => $join->on('torrents.id', '=', 'h.torrent_id')
                    )
                    ->where('tmdb_movie_id', '!=', 0)
                    ->whereNotNull('tmdb_movie_id')
                    ->whereRelation('category', 'movie_meta', '=', true)
                    ->groupBy('tmdb_movie_id')
                    ->orderBy('download_count')
                    ->take(5)
                    ->get()
            ),
            'topTv' => $this->recordar(
                'yearly-overview:'.$year.':top-tv',
                fn () => Torrent::with('tv')
                    ->select([
                        'tmdb_tv_id',
                        DB::raw('COUNT(h.user_id) as download_count'),
                        DB::raw('MIN(category_id) as category_id'),
                    ])
                    ->leftJoinSub(
                        History::query()
                            ->whereNotNull('completed_at')
                            ->where('history.created_at', '>=', $year.'-01-01 00:00:00')
                            ->where('history.created_at', '<=', $year.'-12-31 23:59:59'),
                        'h',
                        fn ($join) => $join->on('torrents.id', '=', 'h.torrent_id')
                    )
                    ->where('tmdb_tv_id', '!=', 0)
                    ->whereNotNull('tmdb_tv_id')
                    ->whereRelation('category', 'tv_meta', '=', true)
                    ->groupBy('tmdb_tv_id')
                    ->orderByDesc('download_count')
                    ->take(10)
                    ->get()
            ),
            'bottomTv' => $this->recordar(
                'yearly-overview:'.$year.':bottom-tv',
                fn () => Torrent::with('tv')
                    ->select([
                        'tmdb_tv_id',
                        DB::raw('COUNT(h.user_id) as download_count'),
                        DB::raw('MIN(category_id) as category_id'),
                    ])
                    ->leftJoinSub(
                        History::query()
                            ->whereNotNull('completed_at')
                            ->where('history.created_at', '>=', $year.'-01-01 00:00:00')
                            ->where('history.created_at', '<=', $year.'-12-31 23:59:59'),
                        'h',
                        fn ($join) => $join->on('torrents.id', '=', 'h.torrent_id')
                    )
                    ->where('tmdb_tv_id', '!=', 0)
                    ->whereNotNull('tmdb_tv_id')
                    ->whereRelation('category', 'tv_meta', '=', true)
                    ->groupBy('tmdb_tv_id')
                    ->orderBy('download_count')
                    ->take(5)
                    ->get()
            ),
            'uploaders' => cache()->remember(
                'yearly-overview:'.$year.':uploaders',
                3600,
                fn () => Torrent::with('user.group')
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->where('anon', '=', false)
                    ->select(DB::raw('user_id, COUNT(*) as value'))
                    ->groupBy('user_id')
                    ->orderByDesc('value')
                    ->take(10)
                    ->get()
            ),
            'posters' => $posters = cache()->remember(
                'yearly-overview:'.$year.':posts',
                3600,
                fn () => Post::with('user.group')
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->select(DB::raw('user_id, COUNT(*) as value'))
                    ->groupBy('user_id')
                    ->orderByDesc('value')
                    ->take(10)
                    ->get()
            ),
            'requesters' => cache()->remember(
                'yearly-overview:'.$year.':requesters',
                3600,
                fn () => TorrentRequest::with(['user.group'])
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->where('user_id', '!=', 1)
                    ->where('anon', '=', false)
                    ->select(DB::raw('user_id, COUNT(*) as value'))
                    ->groupBy('user_id')
                    ->orderByDesc('value')
                    ->take(10)
                    ->get()
            ),
            'fillers' => cache()->remember(
                'yearly-overview:'.$year.':fillers',
                3600,
                fn () => TorrentRequest::with('filler.group')
                    ->where('filled_when', '>=', $year.'-01-01 00:00:00')
                    ->where('filled_when', '<=', $year.'-12-31 23:59:59')
                    ->where('filled_by', '!=', 1)
                    ->where('filled_anon', '=', false)
                    ->select(DB::raw('filled_by, COUNT(*) as value'))
                    ->groupBy('filled_by')
                    ->orderByDesc('value')
                    ->take(10)
                    ->get()
            ),
            'commenters' => cache()->remember(
                'yearly-overview:'.$year.':commenters',
                3600,
                fn () => Comment::with('user.group')
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->where('user_id', '!=', 1)
                    ->where('anon', '=', false)
                    ->select(DB::raw('user_id, COUNT(*) as value'))
                    ->groupBy('user_id')
                    ->orderByDesc('value')
                    ->take(10)
                    ->get()
            ),
            'thankers' => cache()->remember(
                'yearly-overview:'.$year.':thankers',
                3600,
                fn () => Thank::with('user.group')
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->where('user_id', '!=', 1)
                    ->select(DB::raw('user_id, COUNT(*) as value'))
                    ->groupBy('user_id')
                    ->orderByDesc('value')
                    ->take(10)
                    ->get()
            ),
            'newUsers' => $this->recordar(
                'yearly-overview:'.$year.':new-users',
                fn () => User::query()
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->count()
            ),
            'movieUploads' => $this->recordar(
                'yearly-overview:'.$year.':movie-uploads',
                fn () => Torrent::query()
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->whereRelation('category', 'movie_meta', '=', true)
                    ->count()
            ),
            'tvUploads' => $this->recordar(
                'yearly-overview:'.$year.':tv-uploads',
                fn () => Torrent::query()
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->whereRelation('category', 'tv_meta', '=', true)
                    ->count()
            ),
            'totalUploads' => $this->recordar(
                'yearly-overview:'.$year.':total-uploads',
                fn () => Torrent::query()
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->count()
            ),
            'totalDownloads' => $this->recordar(
                'yearly-overview:'.$year.':total-downloads',
                fn () => History::query()
                    ->where('created_at', '>=', $year.'-01-01 00:00:00')
                    ->where('created_at', '<=', $year.'-12-31 23:59:59')
                    ->count()
            ),
            'staffers' => cache()->remember(
                'yearly-overview:'.$year.':staffers',
                3600,
                fn () => Group::query()
                    ->with('users.group')
                    ->where('is_modo', '=', 1)
                    ->orWhere('is_admin', '=', 1)
                    ->orderByDesc('position')
                    ->get()
            ),
            'birthYear' => $birthYear,
            'year'      => $year,
        ]);
    }
}
