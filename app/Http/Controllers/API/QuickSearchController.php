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

namespace App\Http\Controllers\API;

use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;

class QuickSearchController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $request->input('query', '');

        $filters = [
            'deleted_at IS NULL',
            'status = '.ModerationStatus::APPROVED->value,
            [
                'category.movie_meta = true',
                'category.tv_meta = true',
            ],
            [
                'tmdb_movie.name EXISTS',
                'tmdb_tv.name EXISTS',
            ],
            [
                'tmdb_movie_id IS NOT NULL AND tmdb_movie_id != 0',
                'tmdb_tv_id IS NOT NULL AND tmdb_tv_id != 0',
            ]
        ];

        // Check if the query is an IMDb or TMDB ID
        $searchById = false;

        if (preg_match('/^(\d+)$/', $query, $matches)) {
            $filters[] = [
                'tmdb_movie_id = '.$matches[1],
                'tmdb_movie.name = '.$matches[1],
                'tmdb_tv_id = '.$matches[1],
                'tmdb_tv.name = '.$matches[1],
            ];
            $searchById = true;
        }

        if (preg_match('/tt0*(\d{7,})/', $query, $matches)) {
            $filters[] = 'imdb = '.$matches[1];
            $searchById = true;
        }

        $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));

        // Prepare the search queries
        $searchQueries = [
            (new SearchQuery())
                ->setIndexUid(config('scout.prefix').'torrents')
                ->setQuery($searchById ? '' : $query)
                ->setFilter($filters)
                ->setAttributesToRetrieve([
                    'id',
                    'name',
                    'tmdb_movie_id',
                    'tmdb_tv_id',
                    'category.id',
                    'category.name',
                    'category.movie_meta',
                    'category.tv_meta',
                    'tmdb_movie.name',
                    'tmdb_movie.year',
                    'tmdb_movie.poster',
                    'tmdb_tv.name',
                    'tmdb_tv.year',
                    'tmdb_tv.poster',
                ])
                ->setDistinct('imdb')
        ];

        // Los libros necesitan su propia consulta, no una rama mas en la de
        // arriba: aquella deduplica por `imdb`, y como ningun libro tiene uno,
        // todos colapsarian en un unico resultado.
        if (!$searchById) {
            $searchQueries[] = (new SearchQuery())
                ->setIndexUid(config('scout.prefix').'torrents')
                ->setQuery($query)
                ->setFilter([
                    'deleted_at IS NULL',
                    'status = '.ModerationStatus::APPROVED->value,
                    [
                        'category.book_meta = true',
                        'category.audiobook_meta = true',
                    ],
                ])
                ->setAttributesToRetrieve([
                    'id',
                    'name',
                    'category.id',
                    'category.name',
                    'category.book_meta',
                    'category.audiobook_meta',
                    'book.title',
                    'book.authors',
                    'book.year',
                    'book.cover',
                    'audiobook.title',
                    'audiobook.authors',
                    'audiobook.narrators',
                    'audiobook.year',
                    'audiobook.cover',
                ]);
        }

        // Los juegos, como los libros, necesitan su propia consulta: la
        // principal deduplica por `imdb` y ningun juego tiene uno, asi que
        // todos colapsarian en un unico resultado. Hasta ahora no tenian
        // ninguna, o sea que la busqueda rapida JAMAS devolvia un juego.
        if (!$searchById) {
            $searchQueries[] = (new SearchQuery())
                ->setIndexUid(config('scout.prefix').'torrents')
                ->setQuery($query)
                ->setFilter([
                    'deleted_at IS NULL',
                    'status = '.ModerationStatus::APPROVED->value,
                    'category.game_meta = true',
                ])
                ->setAttributesToRetrieve([
                    'id',
                    'name',
                    'igdb',
                    'category.id',
                    'category.name',
                    'category.game_meta',
                    'igdb_game.name',
                    'igdb_game.year',
                    'igdb_game.cover',
                    'igdb_game.platforms',
                ]);
        }

        // Add the people search query only if it's not an ID search
        if (!$searchById) {
            $searchQueries[] = (new SearchQuery())
                ->setIndexUid(config('scout.prefix').'people')
                ->setQuery($query)
                ->setAttributesToRetrieve([
                    'id',
                    'name',
                    'birthday',
                    'still',
                ]);
        }

        // Perform multi-search with MultiSearchFederation

        $searchQuery = fn () => $client->multiSearch($searchQueries, ((new MultiSearchFederation()))->setLimit(20));

        if (preg_match("/^[a-zA-Z0-9-_ .'@:\\[\\]+&\\/,!#()?\"]{1,2}$/", $query)) {
            $multiSearchResults = cache()->flexible('quick-search:'.strtolower($query), [3600 * 24, 3600 * 24 * 2], $searchQuery);
        } else {
            $multiSearchResults = $searchQuery();
        }

        $results = [];

        // Process the hits from the multiSearchResults
        foreach ($multiSearchResults['hits'] as $hit) {
            if (
                $hit['_federation']['indexUid'] === config('scout.prefix').'torrents'
                && (($hit['category']['book_meta'] ?? false) || ($hit['category']['audiobook_meta'] ?? false))
            ) {
                $isBook = (bool) ($hit['category']['book_meta'] ?? false);

                // Una lectura libre no tiene grabacion en ningun catalogo
                // comercial, asi que `audiobook` viene nulo, pero la OBRA que
                // lee si esta resuelta. Sin esta caida el resultado salia sin
                // titulo, sin autor y sin portada: el PDF del mismo libro
                // aparecia y el audiolibro no.
                $meta = ($isBook ? $hit['book'] : ($hit['audiobook'] ?? $hit['book'])) ?? null;
                $authors = implode(', ', $meta['authors'] ?? []);

                $results[] = [
                    'id'   => $hit['id'],
                    'name' => $meta['title'] ?? $hit['name'],
                    // El autor ocupa aqui el hueco del anio: identifica una
                    // edicion mucho mejor que la fecha.
                    'year'  => $authors !== '' ? $authors : ($meta['year'] ?? null),
                    'image' => ($meta['cover'] ?? null)
                        ? tmdb_image('poster_small', $meta['cover'])
                        : mb_substr($meta['title'] ?? $hit['name'], 0, 2),
                    // No hay ruta de "similares" para libros: se enlaza el
                    // torrent directamente.
                    'url'  => route('torrents.show', ['id' => $hit['id']]),
                    'type' => $hit['category']['name'],
                ];
            } elseif (
                $hit['_federation']['indexUid'] === config('scout.prefix').'torrents'
                && ($hit['category']['game_meta'] ?? false)
            ) {
                $juego = $hit['igdb_game'] ?? null;
                $plataformas = implode(', ', array_column($juego['platforms'] ?? [], 'name'));

                $results[] = [
                    'id'   => $hit['id'],
                    'name' => $juego['name'] ?? $hit['name'],
                    // La plataforma identifica una copia mejor que el anio, que
                    // es el mismo para todas las ediciones de un juego.
                    'year'  => $plataformas !== '' ? $plataformas : ($juego['year'] ?? null),
                    'image' => ($juego['cover'] ?? null)
                        ? 'https://images.igdb.com/igdb/image/upload/t_cover_small/'.$juego['cover'].'.jpg'
                        : mb_substr($juego['name'] ?? $hit['name'], 0, 2),
                    // Los juegos SI tienen ruta de similares, por id de IGDB.
                    'url' => $hit['igdb']
                        ? route('torrents.similar', ['category_id' => $hit['category']['id'], 'tmdb' => $hit['igdb']])
                        : route('torrents.show', ['id' => $hit['id']]),
                    'type' => $hit['category']['name'],
                ];
            } elseif ($hit['_federation']['indexUid'] === config('scout.prefix').'torrents') {
                $type = $hit['category']['movie_meta'] === true ? 'tmdb_movie' : 'tmdb_tv';

                $results[] = [
                    'id'    => $hit['id'],
                    'name'  => $hit[$type]['name'],
                    'year'  => $hit[$type]['year'],
                    'image' => $hit[$type]['poster'] ? tmdb_image('poster_small', $hit[$type]['poster']) : mb_substr($hit['name'], 0, 2),
                    'url'   => route('torrents.similar', ['category_id' => $hit['category']['id'], 'tmdb' => $hit["{$type}_id"]]),
                    'type'  => $hit['category']['name'],
                ];
            } elseif ($hit['_federation']['indexUid'] === config('scout.prefix').'people') {
                $results[] = [
                    'id'    => $hit['id'],
                    'name'  => $hit['name'],
                    'year'  => $hit['birthday'],
                    'image' => $hit['still'] ? tmdb_image('poster_small', $hit['still']) : mb_substr($hit['name'], 0, 1).mb_substr(str($hit['name'])->explode(' ')->last() ?? '', 0, 1),
                    'url'   => route('mediahub.persons.show', ['id' => $hit['id']]),
                    'type'  => 'Person',
                ];
            }
        }

        return response()->json(['results' => $results]);
    }
}
