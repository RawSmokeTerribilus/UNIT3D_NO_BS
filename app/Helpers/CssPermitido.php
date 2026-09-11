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

namespace App\Helpers;

/**
 * Lista blanca para el CSS que un usuario puede enlazar en sus ajustes.
 *
 * Por qué existe: `custom_css` y `standalone_css` se pintan tal cual en un
 * <link rel="stylesheet"> de TODAS las páginas, y la validación de origen era
 * `nullable|url`, o sea cualquier URL. 84 usuarios tenían hojas de estilo de
 * terceros cargándose en el tracker.
 *
 * Y no es cosmético: con selectores de atributo y `background-image: url(...)`
 * se puede sacar hacia fuera lo que haya en la página, y quien controle ese
 * dominio decide qué ven esos usuarios.
 *
 * La comprobación se hace sobre el HOST PARSEADO, nunca sobre la cadena. El
 * truco clásico para saltarse un `str_contains` es
 * `https://nosoyani.github.io@evil.com/x.css`, donde el host real es evil.com.
 */
final class CssPermitido
{
    /**
     * Dominios desde los que se acepta una hoja de estilo.
     *
     * Para añadir uno hay que tocar este fichero y pasar por revisión: es a
     * propósito que no salga de la tabla `settings`, que se edita desde el
     * panel web.
     *
     * @var list<string>
     */
    public const DOMINIOS = [
        'nosoyani.github.io',
    ];

    /**
     * Devuelve la URL si se puede usar, o null si no.
     *
     * Se acepta:
     *   - una ruta del propio tracker: /css/lo-que-sea.css
     *   - https://<dominio de la lista>/lo-que-sea.css
     */
    public static function limpia(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Rutas del propio sitio. Se exige una sola barra: `//evil.com/x.css`
        // es una URL protocolo-relativa y apunta fuera.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return self::terminaEnCss($url) ? $url : null;
        }

        $partes = parse_url($url);

        if ($partes === false || !isset($partes['scheme'], $partes['host'])) {
            return null;
        }

        // Sólo https. Descarta de paso `javascript:`, `data:` y demás, que
        // filter_var deja pasar como URL válida.
        if (strtolower($partes['scheme']) !== 'https') {
            return null;
        }

        // Con credenciales en la URL el host que manda no es el que se lee.
        if (isset($partes['user']) || isset($partes['pass'])) {
            return null;
        }

        $host = rtrim(strtolower($partes['host']), '.');

        if (!\in_array($host, self::DOMINIOS, true)) {
            return null;
        }

        return self::terminaEnCss($partes['path'] ?? '') ? $url : null;
    }

    /**
     * Que el recurso sea una hoja de estilo y no cualquier otra cosa.
     */
    private static function terminaEnCss(string $ruta): bool
    {
        return str_ends_with(strtolower($ruta), '.css');
    }
}
