<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Proxy + cache + normalización de imágenes de arte (posters / backdrops) de
 * todos los proveedores de metadata (TMDB, OMDb/Amazon, TVmaze, MAL/Jikan,
 * AniList).
 *
 * Por qué: al añadir proveedores ajenos a TMDB, la columna `poster` puede
 * apuntar a CDNs externos que sirven el original a resolución completa
 * (Amazon y MAL = varios MB). tmdb_image() sólo reescribe el segmento
 * `/original/` de URLs de TMDB, así que las URLs ajenas pasaban sin tocar:
 * pesadas y servidas directo desde terceros lentos → la página se atasca y a
 * veces hace falta recargar para que aparezcan.
 *
 * Este proxy descarga la imagen una vez, la reduce al ancho normalizado del
 * token de tamaño y la cachea en disco, re-emitiéndola same-origin con
 * cabeceras `immutable`. Las imágenes de TMDB se piden ya redimensionadas por
 * su propio CDN (no se re-encodean).
 *
 * Defensas SSRF:
 *   - Ruta firmada (`signed`): sólo son válidas las URLs que generó el propio
 *     servidor, lo que también evita que un tercero rellene el disco pidiendo
 *     rutas arbitrarias de los hosts permitidos.
 *   - Host upstream restringido a una allowlist (self::HOSTS).
 *   - Token de tamaño restringido al mapa conocido.
 *   - Sólo se emite `image/jpeg`.
 */
class ArtImageProxyController extends Controller
{
    /**
     * SSRF allowlist: only these upstream hosts are ever fetched. These are the
     * five metadata providers' image CDNs — code-driven, never user-tuned, so
     * they live here as a constant (no config dependency → prod needs no
     * config:cache to deploy this feature). Keep in sync with tmdb_image().
     *
     * @var list<string>
     */
    public const array HOSTS = [
        'image.tmdb.org',
        'm.media-amazon.com',
        'static.tvmaze.com',
        'cdn.myanimelist.net',
        's4.anilist.co',
    ];

    /**
     * Size token => normalized max width (px) + native TMDB size segment
     * (TMDB resizes server-side, so we just fetch that width).
     *
     * @var array<string, array{width: int, tmdb: string}>
     */
    public const array SIZES = [
        'back_big'     => ['width' => 1280, 'tmdb' => 'w1280'],
        'back_small'   => ['width' => 780,  'tmdb' => 'w780'],
        'poster_big'   => ['width' => 500,  'tmdb' => 'w500'],
        'poster_mid'   => ['width' => 342,  'tmdb' => 'w342'],
        'poster_small' => ['width' => 92,   'tmdb' => 'w92'],
    ];

    public function show(Request $request, string $size): BinaryFileResponse
    {
        $sizes = self::SIZES;
        abort_unless(isset($sizes[$size]), 404);

        $url  = (string) $request->query('u', '');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        abort_if($url === '' || $host === '', 404);
        abort_unless(\in_array($host, self::HOSTS, true), 404);

        $width     = $sizes[$size]['width'];
        $cacheDir  = storage_path('app/art-proxy/'.$size);
        $cachePath = $cacheDir.'/'.sha1($url).'.jpg';

        if (!file_exists($cachePath)) {
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0o755, true);
            }

            if ($host === 'image.tmdb.org') {
                // TMDB redimensiona en servidor: pedimos el ancho nativo y
                // cacheamos los bytes tal cual (ya optimizados).
                $fetch    = str_replace('/original/', '/'.$sizes[$size]['tmdb'].'/', $url);
                $response = Http::timeout(10)->get($fetch);
                abort_unless($response->successful(), 404);

                file_put_contents($cachePath, $response->body());
            } else {
                // Proveedor ajeno: descargamos el original y lo reducimos a
                // nuestro ancho normalizado, re-encodeando a jpg.
                $response = Http::timeout(10)->get($url);
                abort_unless($response->successful(), 404);

                $image = Image::decode($response->body());

                // Sólo reducir, nunca ampliar imágenes ya pequeñas.
                if ($image->width() > $width) {
                    $image->scaleDown(width: $width);
                }

                $image->encode(new JpegEncoder(quality: 85))->save($cachePath);
            }
        }

        return response()->file($cachePath, [
            'Content-Type'                 => 'image/jpeg',
            'Cache-Control'                => 'public, max-age=2592000, immutable',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }
}
