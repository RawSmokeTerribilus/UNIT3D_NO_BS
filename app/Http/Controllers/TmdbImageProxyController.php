<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Proxy de imágenes de TMDB. Re-emite la imagen desde nuestro propio origen
 * con CORP same-origin, lo que la hace válida bajo `Cross-Origin-Embedder-Policy:
 * require-corp` (activo en /gaming/*) y bajo el CSP estricto del sitio
 * (img-src 'self').
 *
 * Defensas SSRF:
 *   - Host upstream codificado a image.tmdb.org (no se acepta URL del cliente).
 *   - Tamaño y nombre de archivo restringidos por regex en la ruta.
 *   - Sólo extensiones de imagen permitidas.
 */
class TmdbImageProxyController extends Controller
{
    private const string TMDB_BASE = 'https://image.tmdb.org/t/p/';

    private const array MIME_BY_EXT = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    public function show(string $size, string $file): BinaryFileResponse|\Illuminate\Http\Response
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        abort_unless(isset(self::MIME_BY_EXT[$ext]), 404);

        $cacheDir  = storage_path('app/tmdb-proxy/' . $size);
        $cachePath = $cacheDir . '/' . $file;

        if (!file_exists($cachePath)) {
            $response = Http::timeout(10)->get(self::TMDB_BASE . $size . '/' . $file);
            abort_unless($response->successful(), 404);

            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0o755, true);
            }
            file_put_contents($cachePath, $response->body());
        }

        return response()->file($cachePath, [
            'Content-Type'                => self::MIME_BY_EXT[$ext],
            'Cache-Control'               => 'public, max-age=2592000, immutable',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }
}
