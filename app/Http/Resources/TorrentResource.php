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

namespace App\Http\Resources;

use App\Enums\AuthGuard;
use App\Services\Metadata\TorrentMetaProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Torrent
 * @property \App\Models\TmdbMovie|\App\Models\TmdbTv|\App\Models\IgdbGame $meta
 */
class TorrentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     type: 'torrent',
     *     id: string,
     *     attributes: array{
     *         meta: array{
     *             poster: string,
     *             genres: string,
     *         },
     *         name: string,
     *         release_year: string|null,
     *         category: string,
     *         type: string,
     *         resolution: string,
     *         distributor: string,
     *         region: string,
     *         media_info: string,
     *         bd_info: string,
     *         description: string,
     *         size: float,
     *         folder: string|null,
     *         num_file: int,
     *         files: \Illuminate\Support\Collection<
     *             int,
     *             array{
     *                 index: int,
     *                 name: string,
     *                 size: int,
     *             },
     *         >,
     *         freeleech: string,
     *         double_upload: bool,
     *         refundable: bool,
     *         internal: int,
     *         featured: \Illuminate\Http\Resources\MissingValue|mixed,
     *         personal_release: bool,
     *         uploader: string,
     *         seeders: int,
     *         leechers: int,
     *         times_completed: int,
     *         tmdb_id: int|null,
     *         imdb_id: int,
     *         tvdb_id: int,
     *         mal_id: int,
     *         igdb_id: int,
     *         category_id: int|null,
     *         type_id: int,
     *         resolution_id: \Illuminate\Http\Resources\MissingValue|mixed,
     *         distributor_id: \Illuminate\Http\Resources\MissingValue|mixed,
     *         region_id: \Illuminate\Http\Resources\MissingValue|mixed,
     *         created_at: \Illuminate\Support\Carbon|null,
     *         download_link: string,
     *         magnet_link: \Illuminate\Http\Resources\MissingValue|mixed,
     *         details_link: string,
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        // Un solo sitio decide portada, generos y anyo por tipo de obra. Ver
        // TorrentMetaProjection: la otra ruta de la API, `/torrents/filter`,
        // proyecta lo mismo desde el documento de Meilisearch.
        $meta = TorrentMetaProjection::fromModel(
            $this->resource->meta instanceof \Illuminate\Database\Eloquent\Model
                ? $this->resource->meta
                : null,
        );

        return [
            'type'       => 'torrent',
            'id'         => (string) $this->id,
            'attributes' => [
                'meta' => [
                    'poster' => $meta['poster'],
                    'genres' => $meta['genres'],
                ],
                'name'         => $this->name,
                'release_year' => $meta['year'],
                'category'     => $this->category->name,
                'type'         => $this->type->name,
                'resolution'   => $this->when(isset($this->resolution_id), $this->resolution->name ?? ''),
                'distributor'  => $this->when(isset($this->distributor_id), $this->distributor->name ?? ''),
                'region'       => $this->when(isset($this->region_id), $this->region->name ?? ''),
                'media_info'   => $this->mediainfo,
                'bd_info'      => $this->bdinfo,
                'description'  => $this->description,
                'size'         => $this->size,
                'folder'       => $this->folder,
                'num_file'     => $this->num_file,
                'files'        => $this->files->map(fn ($file, $index) => [
                    'index' => $index + 1,
                    'name'  => $file->name,
                    'size'  => $file->size,
                ]),
                'freeleech'        => $this->free.'%',
                'double_upload'    => $this->doubleup,
                'refundable'       => $this->refundable,
                'internal'         => $this->internal,
                'featured'         => $this->whenHas('featured'),
                'personal_release' => $this->personal_release,
                'uploader'         => $this->anon ? 'Anonymous' : $this->user->username,
                'seeders'          => $this->seeders,
                'leechers'         => $this->leechers,
                'times_completed'  => $this->times_completed,
                'tmdb_id'          => $this->tmdb_movie_id ?? $this->tmdb_tv_id,
                'imdb_id'          => $this->imdb,
                'tvdb_id'          => $this->tvdb,
                'mal_id'           => $this->mal,
                'igdb_id'          => $this->igdb,
                // Libro y audiolibro no tenian identificador en la respuesta:
                // habia tmdb/imdb/tvdb/mal/igdb y nada para la OBRA escrita.
                // Un consumidor no podia resolverla. Son campos NUEVOS, asi
                // que no rompen a nadie que ya lea los de arriba.
                'isbn13'           => $this->isbn13,
                'asin'             => $this->asin,
                'category_id'      => $this->category_id,
                'type_id'          => $this->type_id,
                'resolution_id'    => $this->when($this->resolution_id !== null, $this->resolution_id),
                'distributor_id'   => $this->when($this->distributor_id !== null, $this->distributor_id),
                'region_id'        => $this->when($this->region_id !== null, $this->region_id),
                'created_at'       => $this->created_at,
                'download_link'    => route('torrent.download.rsskey', ['id' => $this->id, 'rsskey' => auth(AuthGuard::API->value)->user()->rsskey]),
                'magnet_link'      => $this->when(config('torrent.magnet') === true, 'magnet:?dn='.$this->name.'&xt=urn:btih:'.bin2hex($this->info_hash).'&as='.route('torrent.download.rsskey', ['id' => $this->id, 'rsskey' => auth(AuthGuard::API->value)->user()->rsskey]).'&tr='.route('announce', ['passkey' => auth('api')->user()->passkey]).'&xl='.$this->size),
                'details_link'     => route('torrents.show', ['id' => $this->id]),
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
