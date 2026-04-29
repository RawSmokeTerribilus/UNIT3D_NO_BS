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

namespace App\Traits;

use App\Models\IgdbGame;
use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use App\Models\Torrent;
use JsonException;
use ReflectionException;

trait TorrentMeta
{
    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Torrent>|\Illuminate\Pagination\CursorPaginator<int, Torrent>|\Illuminate\Pagination\LengthAwarePaginator<int, Torrent>|\Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Torrent> $torrents
     *
     * @throws \MarcReichel\IGDBLaravel\Exceptions\MissingEndpointException
     * @throws \MarcReichel\IGDBLaravel\Exceptions\InvalidParamsException
     * @throws ReflectionException
     * @throws JsonException
     * @return (
     *        $torrents is \Illuminate\Database\Eloquent\Collection<int, \App\Models\Torrent> ? \Illuminate\Support\Collection<int, \App\Models\Torrent>
     *     : ($torrents is \Illuminate\Pagination\CursorPaginator<int, \App\Models\Torrent> ? \Illuminate\Pagination\CursorPaginator<int, \App\Models\Torrent>
     *     : ($torrents is \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\Torrent> ? \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\Torrent>
     *     : \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\Torrent>
     * )))
     */
    public function scopeMeta(\Illuminate\Database\Eloquent\Collection|\Illuminate\Pagination\CursorPaginator|\Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Contracts\Pagination\LengthAwarePaginator $torrents, bool $withCredits = false): \Illuminate\Support\Collection|\Illuminate\Pagination\CursorPaginator|\Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if ($torrents instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator || $torrents instanceof \Illuminate\Contracts\Pagination\CursorPaginator) {
            $movieIds = collect($torrents->items())->where('meta', '=', 'movie')->pluck('tmdb_movie_id');
            $tvIds = collect($torrents->items())->where('meta', '=', 'tv')->pluck('tmdb_tv_id');
            $gameIds = collect($torrents->items())->where('meta', '=', 'game')->pluck('igdb');
        } else {
            $movieIds = $torrents->where('meta', '=', 'movie')->pluck('tmdb_movie_id');
            $tvIds = $torrents->where('meta', '=', 'tv')->pluck('tmdb_tv_id');
            $gameIds = $torrents->where('meta', '=', 'game')->pluck('igdb');
        }

        $movies = TmdbMovie::query()
            ->with('genres')
            ->when($withCredits, fn ($query) => $query->with([
                'actors'    => fn ($query) => $query->limit(3),
                'directors' => fn ($query) => $query->limit(3),
            ]))
            ->whereIntegerInRaw('id', $movieIds)
            ->get()
            ->keyBy('id');
        $tv = TmdbTv::query()
            ->with('genres')
            ->when($withCredits, fn ($query) => $query->with([
                'actors'   => fn ($query) => $query->limit(3),
                'creators' => fn ($query) => $query->limit(3),
            ]))
            ->whereIntegerInRaw('id', $tvIds)
            ->get()
            ->keyBy('id');
        $games = IgdbGame::query()
            ->with('genres')
            ->whereIntegerInRaw('id', $gameIds)
            ->get()
            ->keyBy('id');

        $setRelation = function ($torrent) use ($movies, $tv, $games) {
            $torrent->setAttribute(
                'meta',
                match ($torrent->meta) {
                    'movie' => $movies[$torrent->tmdb_movie_id] ?? null,
                    'tv'    => $tv[$torrent->tmdb_tv_id] ?? null,
                    'game'  => $games[$torrent->igdb] ?? null,
                    default => null,
                },
            );

            return $torrent;
        };

        if ($torrents instanceof \Illuminate\Database\Eloquent\Collection) {
            return $torrents->map($setRelation);
        }

        /**
         * Laravel's \Illuminate\Contracts\Pagination\LengthAwarePaginator does not have a through method
         * but we are passed a \Illuminate\Pagination\LengthAwarePaginator which does have such a method.
         * Seems to be caused by some Laravel type error that's returning an interface instead of the type
         * itself, or that the interface is missing the method.
         *
         * @phpstan-ignore method.notFound
         */
        return $torrents->through($setRelation);
    }

    /**
     * @param \Illuminate\Support\Collection<int, Torrent> $torrents
     * @return array{
     *     movie?: non-empty-array<int, array{
     *         Movie: non-empty-array<string, non-empty-list<Torrent>>,
     *         category_id: int,
     *     }>,
     *     tv?: non-empty-array<int, array{
     *         'Complete Pack'?: non-empty-array<string, non-empty-list<Torrent>>,
     *         Specials?: non-empty-array<string, array<string, non-empty-list<Torrent>>>,
     *         Seasons?: non-empty-array<string, array{
     *             'Season Pack'?: non-empty-array<string, non-empty-list<Torrent>>,
     *             Episodes?: non-empty-array<string, array<string, non-empty-list<Torrent>>>,
     *         }>,
     *         category_id: int,
     *     }>,
     *     game?: non-empty-array<int, array{
     *         Game: non-empty-array<string, non-empty-list<Torrent>>,
     *         category_id: int,
     *     }>,
     * }
     */
    public static function groupTorrents(\Illuminate\Support\Collection $torrents): array
    {
        $groupedTorrents = [];

        foreach ($torrents as &$torrent) {
            // Memoizing and avoiding eloquent casts reduces runtime duration from 70ms to 40ms.
            // If accessing laravel's attributes array directly, it's reduced to 11ms,
            // but the attributes array is marked as protected so we can't access it.

            $tmdb = (int) $torrent->getAttributeValue('tmdb_movie_id') ?: (int) $torrent->getAttributeValue('tmdb_tv_id') ?: (int) $torrent->getAttributeValue('igdb');
            $type = (string) $torrent->getRelationValue('type')->getAttributeValue('name');
            $categoryId = (int) $torrent->getAttributeValue('category_id');
            $meta = (string) $torrent->getAttributeValue('meta');

            switch ($meta) {
                case 'game':
                    $groupedTorrents['game'][$tmdb]['Game'][$type][] = $torrent;
                    $groupedTorrents['game'][$tmdb]['category_id'] = $categoryId;

                    break;
                case 'movie':
                    $groupedTorrents['movie'][$tmdb]['Movie'][$type][] = $torrent;
                    $groupedTorrents['movie'][$tmdb]['category_id'] = $categoryId;

                    break;
                case 'tv':
                    $episode = (int) $torrent->getAttributeValue('episode_number');
                    $season = (int) $torrent->getAttributeValue('season_number');

                    if ($season == 0) {
                        if ($episode == 0) {
                            /** @phpstan-ignore offsetAccess.nonOffsetAccessible (Phpstan is incorrectly treating the array shape as a general array) */
                            $groupedTorrents['tv'][$tmdb]['Complete Pack'][$type][] = $torrent;
                        } else {
                            /** @phpstan-ignore offsetAccess.nonOffsetAccessible (Phpstan is incorrectly treating the array shape as a general array) */
                            $groupedTorrents['tv'][$tmdb]['Specials']["Special {$episode}"][$type][] = $torrent;
                        }
                    } else {
                        if ($episode == 0) {
                            /** @phpstan-ignore offsetAccess.nonOffsetAccessible (Phpstan is incorrectly treating the array shape as a general array) */
                            $groupedTorrents['tv'][$tmdb]['Seasons']["Season {$season}"]['Season Pack'][$type][] = $torrent;
                        } else {
                            /** @phpstan-ignore offsetAccess.nonOffsetAccessible (Phpstan is incorrectly treating the array shape as a general array) */
                            $groupedTorrents['tv'][$tmdb]['Seasons']["Season {$season}"]['Episodes']["Episode {$episode}"][$type][] = $torrent;
                        }
                    }

                    $groupedTorrents['tv'][$tmdb]['category_id'] = $categoryId;

                    break;
            }
        }

        foreach ($groupedTorrents as $mediaType => &$workTorrents) {
            switch ($mediaType) {
                case 'game':
                    foreach ($workTorrents as &$gameTorrents) {
                        self::sortTorrentTypes($gameTorrents['Game']);
                    }

                    break;
                case 'movie':
                    foreach ($workTorrents as &$movieTorrents) {
                        self::sortTorrentTypes($movieTorrents['Movie']);
                    }

                    break;
                case 'tv':
                    foreach ($workTorrents as &$tvTorrents) {
                        foreach ($tvTorrents as $packOrSpecialOrSeasonsType => &$packOrSpecialOrSeasons) {
                            switch ($packOrSpecialOrSeasonsType) {
                                case 'Complete Pack':
                                    /** @phpstan-ignore argument.type (Phpstan is incorrectly treating the array shape as a general array) */
                                    self::sortTorrentTypes($packOrSpecialOrSeasons);

                                    break;
                                case 'Specials':
                                    /** @phpstan-ignore argument.type (Phpstan is incorrectly treating the array shape as a general array) */
                                    krsort($packOrSpecialOrSeasons, SORT_NATURAL);

                                    foreach ($packOrSpecialOrSeasons as &$specialTorrents) {
                                        self::sortTorrentTypes($specialTorrents);
                                    }

                                    break;
                                case 'Seasons':
                                    /** @phpstan-ignore argument.type (Phpstan is incorrectly treating the array shape as a general array) */
                                    krsort($packOrSpecialOrSeasons, SORT_NATURAL);

                                    foreach ($packOrSpecialOrSeasons as &$season) {
                                        foreach ($season as $packOrEpisodesType => &$packOrEpisodes) {
                                            switch ($packOrEpisodesType) {
                                                case 'Season Pack':
                                                    self::sortTorrentTypes($packOrEpisodes);

                                                    break;
                                                case 'Episodes':
                                                    krsort($packOrEpisodes, SORT_NATURAL);

                                                    foreach ($packOrEpisodes as &$episodeTorrents) {
                                                        self::sortTorrentTypes($episodeTorrents);
                                                    }

                                                    break;
                                            }
                                        }
                                    }
                            }
                        }
                    }
            }
        }

        /** @phpstan-ignore return.type (The nested array shapes confuse phpstan and cause it to simplify it incorrectly) */
        return $groupedTorrents;
    }

    /**
     * @param non-empty-array<string, non-empty-list<Torrent>> $torrentTypeTorrents
     */
    private static function sortTorrentTypes(&$torrentTypeTorrents): void
    {
        uasort(
            $torrentTypeTorrents,
            fn ($a, $b) => $a[0]->getRelationValue('type')->getAttributeValue('position')
                <=> $b[0]->getRelationValue('type')->getAttributeValue('position')
        );

        foreach ($torrentTypeTorrents as &$torrents) {
            usort(
                $torrents,
                fn ($a, $b) => [
                    $a->getRelationValue('resolution')->getAttributeValue('position'),
                    $a->getAttributeValue('name')
                ] <=> [
                    $b->getRelationValue('resolution')->getAttributeValue('position'),
                    $b->getAttributeValue('name')
                ]
            );
        }
    }
}
