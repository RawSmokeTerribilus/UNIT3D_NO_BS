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

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Proxy de imágenes para las DESCRIPCIONES (BBCode).
 *
 * Hermano de ArtImageProxyController, con otro cometido: aquél sirve posters y
 * backdrops de proveedores de metadatos conocidos, con tamaños fijos. Éste sirve
 * las imágenes que los usuarios meten en sus descripciones, cuyo origen no
 * podemos enumerar de antemano.
 *
 * Sustituye a wsrv.nl (images.weserv.nl). Se venía dependiendo de un tercero
 * gratuito para que se vieran las imágenes de TODA la web: cuando falla con un
 * origen, la página sale en blanco sin error y sin forma de saber por qué.
 *
 * Defensa contra SSRF, que aquí es el riesgo de verdad porque la URL la elige
 * un usuario:
 *   - la ruta va FIRMADA: sólo valen URLs que generó el propio servidor al
 *     renderizar un BBCode, no rutas arbitrarias que alguien invente;
 *   - sólo http/https;
 *   - se resuelve el host y se rechaza cualquier IP privada, de loopback o
 *     reservada, para que nadie use el tracker como puerta a la red interna;
 *   - el cuerpo tiene tope de tamaño y sólo se acepta si es una imagen.
 */
final class DescriptionImageProxyController extends Controller
{
    /** Tope de descarga (bytes). Una captura de 1080p con holgura. */
    private const int MAX_BYTES = 12_582_912;

    private const array TIPOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public function show(Request $request): BinaryFileResponse
    {
        $url = (string) $request->query('url', '');

        abort_if($url === '' || mb_strlen($url) > 2048, 404);

        $partes = parse_url($url);
        abort_if($partes === false || !isset($partes['scheme'], $partes['host']), 404);
        abort_unless(\in_array(strtolower($partes['scheme']), ['http', 'https'], true), 404);
        abort_if($this->esDestinoInterno($partes['host']), 404);

        $cacheDir  = storage_path('app/desc-proxy');
        $cachePath = $cacheDir.'/'.sha1($url);

        if (!file_exists($cachePath)) {
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0o755, true);
            }

            $response = Http::timeout(12)
                ->withOptions(['stream' => false, 'allow_redirects' => ['max' => 3]])
                ->get($url);

            abort_unless($response->successful(), 404);

            $tipo = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            abort_unless(isset(self::TIPOS[$tipo]), 404);

            $cuerpo = $response->body();
            abort_if($cuerpo === '' || \strlen($cuerpo) > self::MAX_BYTES, 404);

            file_put_contents($cachePath, $cuerpo);
            file_put_contents($cachePath.'.type', $tipo);
        }

        $tipo = is_file($cachePath.'.type')
            ? (string) file_get_contents($cachePath.'.type')
            : 'image/jpeg';

        return response()->file($cachePath, [
            'Content-Type'                 => $tipo,
            'Cache-Control'                => 'public, max-age=2592000, immutable',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    /**
     * ¿El host apunta a la propia máquina o a la red interna?
     *
     * Se comprueba sobre la IP resuelta, no sobre el texto: `nombre.local` o un
     * dominio público que resuelva a 127.0.0.1 son el mismo ataque.
     */
    private function esDestinoInterno(string $host): bool
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = array_merge(
                gethostbynamel($host) ?: [],
                array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
            );
        }

        if ($ips === []) {
            return true;    // no resuelve: no se toca
        }

        foreach ($ips as $ip) {
            $publica = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ($publica === false) {
                return true;
            }
        }

        return false;
    }
}
