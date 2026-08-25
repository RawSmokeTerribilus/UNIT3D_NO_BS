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

namespace App\Services\Metadata;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\IgdbGame;
use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use Illuminate\Database\Eloquent\Model;

/**
 * El bloque `meta` de la API, con un único sitio donde discutirlo.
 *
 * Existe porque había DOS implementaciones del mismo contrato y divergían:
 * `TorrentResource` proyectaba desde el modelo hidratado y
 * `TorrentController::filter()` montaba el array a mano desde el documento de
 * Meilisearch mirando sólo `tmdb_movie`/`tmdb_tv`. Resultado medido el
 * 2026-08-25 en producción: un audiolibro devolvía `genres: ", , "` por la
 * primera ruta y `genres: ""` por la segunda, y ni libro ni juego traían año.
 *
 * Las dos rutas parten de datos distintos —un modelo Eloquent y un hit del
 * índice— así que son dos entradas, pero la salida y las reglas por tipo son
 * las mismas. Si se añade un tipo de obra, se añade aquí y en los dos sitios
 * a la vez.
 *
 * Tres decisiones que no son obvias:
 *
 * 1. `genres` sale como **cadena separada por comas**, no como lista. Es un
 *    contrato público y el problema era el contenido, no el tipo: cambiar la
 *    forma rompería a cualquier consumidor por un beneficio estético.
 *
 * 2. `poster` es la URL **del proveedor**, no la del art proxy. `tmdb_image()`
 *    devuelve `/authenticated-images/art/...`, que exige sesión: medido, un
 *    GET sin cookie da 302 a `/login`. O sea el campo no servía para ningún
 *    consumidor externo, ni siquiera en películas. Se devuelve lo que se
 *    puede descargar.
 *
 * 3. Cuando no hay dato, `poster` y `genres` salen como cadena vacía y nunca
 *    como `null`, para no cambiarle el tipo a quien ya los lee. Lo que se
 *    fue es `https://via.placeholder.com/...`: ese dominio está muerto —
 *    medido, HTTP 000 con fallo de TLS.
 *
 * El audiolibro es el único con trampa: su `genres` es la columna json que
 * manda Audnexus, texto plano sin id. Los navegables son `bookGenres` (mismo
 * catálogo que los e-books) y en el índice, `genres_resolved`.
 */
final class TorrentMetaProjection
{
    /**
     * IGDB guarda un id de imagen, no una URL. El tamaño va en la ruta.
     */
    private const IGDB_COVER = 'https://images.igdb.com/igdb/image/upload/t_cover_big/';

    /**
     * Desde el modelo ya hidratado por `TorrentMeta::scopeMeta()`.
     *
     * @return array{poster: string, genres: string, year: ?int}
     */
    public static function fromModel(?Model $meta): array
    {
        return match (true) {
            $meta instanceof TmdbMovie => self::build($meta->poster, $meta->genres, $meta->release_date?->format('Y')),
            $meta instanceof TmdbTv    => self::build($meta->poster, $meta->genres, $meta->first_air_date?->format('Y')),
            $meta instanceof IgdbGame  => self::build(
                $meta->cover_image_id === null ? null : self::IGDB_COVER.$meta->cover_image_id.'.jpg',
                $meta->genres,
                $meta->first_release_date?->format('Y'),
            ),
            $meta instanceof Book      => self::build($meta->cover_url, $meta->genres, $meta->first_publish_year),
            $meta instanceof Audiobook => self::build($meta->cover_url, $meta->bookGenres, $meta->release_date?->format('Y')),
            default                    => self::build(null, null, null),
        };
    }

    /**
     * Desde un documento del índice de Meilisearch.
     *
     * El orden importa: un audiolibro con ISBN además de ASIN trae los dos
     * subdocumentos, y gana el audiolibro — el mismo criterio que aplica
     * `TorrentMeta::scopeMeta()` al hidratar.
     *
     * @param  array<string, mixed>  $hit
     * @return array{poster: string, genres: string, year: ?int}
     */
    public static function fromSearchHit(array $hit): array
    {
        foreach (['tmdb_movie', 'tmdb_tv'] as $clave) {
            if (\is_array($hit[$clave] ?? null)) {
                return self::build($hit[$clave]['poster'] ?? null, $hit[$clave]['genres'] ?? null, $hit[$clave]['year'] ?? null);
            }
        }

        if (\is_array($hit['igdb_game'] ?? null)) {
            $portada = $hit['igdb_game']['cover'] ?? null;

            return self::build(
                $portada === null ? null : self::IGDB_COVER.$portada.'.jpg',
                $hit['igdb_game']['genres'] ?? null,
                $hit['igdb_game']['year'] ?? null,
            );
        }

        if (\is_array($hit['audiobook'] ?? null)) {
            return self::build(
                $hit['audiobook']['cover'] ?? null,
                $hit['audiobook']['genres_resolved'] ?? null,
                $hit['audiobook']['year'] ?? null,
            );
        }

        if (\is_array($hit['book'] ?? null)) {
            return self::build($hit['book']['cover'] ?? null, $hit['book']['genres'] ?? null, $hit['book']['year'] ?? null);
        }

        return self::build(null, null, null);
    }

    /**
     * @return array{poster: string, genres: string, year: ?int}
     */
    private static function build(?string $portada, mixed $generos, int|string|null $anyo): array
    {
        return [
            'poster' => $portada ?? '',
            'genres' => self::unirGeneros($generos),
            'year'   => $anyo === null || $anyo === '' ? null : (int) $anyo,
        ];
    }

    /**
     * Acepta las dos formas que existen: modelos con `->name` (relación
     * Eloquent) y arrays `['name' => ...]` (subdocumento del índice).
     */
    private static function unirGeneros(mixed $generos): string
    {
        if ($generos === null) {
            return '';
        }

        return collect($generos)
            ->map(static fn ($genero): ?string => \is_array($genero)
                ? ($genero['name'] ?? null)
                : ($genero->name ?? null))
            ->filter()
            ->implode(', ');
    }
}
