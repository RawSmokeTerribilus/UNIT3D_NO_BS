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

    /**
     * Un origen lento no puede secuestrar un worker de PHP-FPM.
     *
     * Una descripción con veinte capturas dispara veinte peticiones en
     * paralelo; con el timeout alto, un host que no responde deja veinte
     * workers ocupados por visita. Seis segundos es de sobra para una
     * imagen y acota el daño.
     */
    private const int TIMEOUT = 6;

    /**
     * Cuánto se recuerda que un origen falló, en segundos.
     *
     * Sin esto, una descripción que apunta a un host caído reintenta la
     * descarga COMPLETA en cada visita, para siempre. Es el escenario real:
     * imgbox cerró y dejó cientos de descripciones apuntando al vacío.
     */
    private const int TTL_FALLO = 21_600;

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
            // Si ya se supo que este origen falla, se responde 404 sin salir a
            // la red. Es lo que evita que una galería muerta cueste una
            // descarga por imagen y por visita.
            abort_if($this->falloReciente($cachePath), 404);

            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0o755, true);
            }

            try {
                $response = Http::timeout(self::TIMEOUT)
                    ->withOptions(['stream' => false, 'allow_redirects' => ['max' => 3]])
                    ->get($url);
            } catch (\Throwable) {
                // Un timeout o un DNS que no resuelve llegan como excepción, no
                // como respuesta fallida, y sin capturarlos el 500 se lo comía
                // la página entera en vez de este único <img>.
                $this->anotarFallo($cachePath);

                abort(404);
            }

            if (!$response->successful()) {
                $this->anotarFallo($cachePath);

                abort(404);
            }

            $tipo = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

            if (!isset(self::TIPOS[$tipo])) {
                $this->anotarFallo($cachePath);

                abort(404);
            }

            $cuerpo = $response->body();

            if ($cuerpo === '' || \strlen($cuerpo) > self::MAX_BYTES) {
                $this->anotarFallo($cachePath);

                abort(404);
            }

            file_put_contents($cachePath, $cuerpo);
            file_put_contents($cachePath.'.type', $tipo);
            @unlink($cachePath.'.fail');
        }

        $this->marcarUso($cachePath);

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
     * Deja constancia de que esta imagen se ha usado, para que la poda borre
     * lo MENOS pedido y no lo más antiguo.
     *
     * Hace falta porque el volumen está montado con `noatime`: el sistema no
     * actualiza la fecha de acceso al leer, así que ordenar por atime daría un
     * orden inventado. Se usa la fecha de modificación como «último uso».
     *
     * Se marca como mucho una vez por hora. Un `touch` por petición sería una
     * escritura de inodo por imagen servida y no aporta nada: para decidir qué
     * sobra basta con la resolución de horas.
     */
    private function marcarUso(string $cachePath): void
    {
        if (time() - (int) filemtime($cachePath) > 3600) {
            @touch($cachePath);
        }
    }

    private function falloReciente(string $cachePath): bool
    {
        $marca = $cachePath.'.fail';

        if (!is_file($marca)) {
            return false;
        }

        if (time() - (int) filemtime($marca) < self::TTL_FALLO) {
            return true;
        }

        // Caducó: se borra para que este intento vuelva a probar de verdad. Un
        // host puede volver, y si no vuelve se anota otra vez.
        @unlink($marca);

        return false;
    }

    private function anotarFallo(string $cachePath): void
    {
        $dir = \dirname($cachePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        @file_put_contents($cachePath.'.fail', (string) time());
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
