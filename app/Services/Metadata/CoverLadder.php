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

/**
 * La misma portada en varias calidades.
 *
 * Existe porque quien consume una carátula no quiere "la carátula", quiere una
 * de un tamaño concreto: el hook de Telegram manda imágenes de ~1280 px, el
 * listado pinta miniaturas de ~300 y la ficha quiere la mayor que haya.
 * Guardar una sola URL obliga a todos a conformarse con la que eligió el que
 * la guardó.
 *
 * Nada de esto cuesta una petición: los cuatro proveedores exponen sus
 * tamaños como transformaciones de la propia URL. Las medidas de abajo están
 * tomadas contra las APIs reales, no deducidas.
 */
final class CoverLadder
{
    /** Tamaños de IGDB, que sí son fijos y conocidos. */
    private const IGDB = [
        ['t_1080p', 810, 1080],
        ['t_cover_big_2x', 528, 704],
        ['t_cover_big', 264, 352],
        ['t_cover_small', 90, 120],
    ];

    /**
     * Google Books, por su parámetro `zoom` no documentado.
     *
     * Medido sobre un volumen real: zoom=1 da 128x159, zoom=3 575x732, zoom=4
     * 800x1018 y zoom=6 2177x2771. **zoom=5 devuelve lo mismo que zoom=1**, así
     * que no entra: era una entrada duplicada que sólo servía para que la
     * rotación enseñara dos veces la miniatura.
     *
     * Los anchos son orientativos: el parámetro se autolimita a lo que subió
     * el editor, así que un volumen mal escaneado devolverá menos en zoom=6.
     * Por eso el ancho va como pista de orden, no como promesa.
     */
    private const GOOGLE_ZOOM = [
        [6, 'xl', 2177],
        [4, 'l', 800],
        [3, 'm', 575],
        [1, 's', 128],
    ];

    /** Audible sirve desde Amazon, que acepta un sufijo con el lado mayor. */
    private const AMAZON_SL = [
        [2400, 'xl'],
        [1200, 'l'],
        [500, 'm'],
        [200, 's'],
    ];

    /**
     * @return list<array{url: string, source: string, tier: string, w: ?int, h: ?int}>
     */
    public static function googleBooks(string $volumeId): array
    {
        if ($volumeId === '') {
            return [];
        }

        $out = [];

        foreach (self::GOOGLE_ZOOM as [$zoom, $tier, $ancho]) {
            // Sin `edge=curl`: le pinta a la portada una esquina doblada falsa
            // que no está en el libro. Venía puesto en lo que devolvía la API.
            $out[] = [
                'url'    => "https://books.google.com/books/content?id={$volumeId}&printsec=frontcover&img=1&zoom={$zoom}",
                'source' => 'google',
                'tier'   => $tier,
                'w'      => $ancho,
                'h'      => null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{url: string, source: string, tier: string, w: ?int, h: ?int}>
     */
    public static function openLibrary(string $isbn13): array
    {
        if ($isbn13 === '') {
            return [];
        }

        // `default=false` no es opcional: sin él OpenLibrary responde 200 con
        // una imagen de relleno en vez de 404, y se acaba cacheando el relleno.
        return [[
            'url'    => "https://covers.openlibrary.org/b/isbn/{$isbn13}-L.jpg?default=false",
            'source' => 'openlibrary',
            'tier'   => 's',   // medido: su "L" son 128x164, peor que el peor de Google
            'w'      => 128,
            'h'      => null,
        ]];
    }

    /**
     * @return list<array{url: string, source: string, tier: string, w: ?int, h: ?int}>
     */
    public static function amazon(string $url): array
    {
        if ($url === '' || !preg_match('#^(https://m\.media-amazon\.com/images/I/[^./]+)(\.[^.]+)*\.(jpg|jpeg|png)$#i', $url, $m)) {
            return $url === '' ? [] : [[
                'url' => $url, 'source' => 'audible', 'tier' => 'xl', 'w' => null, 'h' => null,
            ]];
        }

        $base = $m[1];
        $ext = strtolower($m[3]);
        $out = [];

        foreach (self::AMAZON_SL as [$lado, $tier]) {
            $out[] = [
                'url'    => "{$base}._SL{$lado}_.{$ext}",
                'source' => 'audible',
                'tier'   => $tier,
                'w'      => $lado,
                'h'      => null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{url: string, source: string, tier: string, w: ?int, h: ?int}>
     */
    public static function igdb(string $imageId): array
    {
        if ($imageId === '') {
            return [];
        }

        $out = [];

        foreach (self::IGDB as [$variante, $w, $h]) {
            $out[] = [
                'url'    => "https://images.igdb.com/igdb/image/upload/{$variante}/{$imageId}.jpg",
                'source' => 'igdb',
                'tier'   => match ($variante) {
                    't_1080p'        => 'xl',
                    't_cover_big_2x' => 'l',
                    't_cover_big'    => 'm',
                    default          => 's',
                },
                'w' => $w,
                'h' => $h,
            ];
        }

        return $out;
    }

    /**
     * Ordena de mayor a menor y quita repetidos, conservando el primero.
     *
     * @param  list<array{url: string, source: string, tier: string, w: ?int, h: ?int}>  $entradas
     * @return list<array{url: string, source: string, tier: string, w: ?int, h: ?int}>
     */
    public static function merge(array ...$entradas): array
    {
        $orden = ['xl' => 0, 'l' => 1, 'm' => 2, 's' => 3];
        $todas = array_merge(...$entradas);

        usort($todas, fn ($a, $b) => [$orden[$a['tier']] ?? 9, -($a['w'] ?? 0)]
                                 <=> [$orden[$b['tier']] ?? 9, -($b['w'] ?? 0)]);

        $vistas = [];
        $out = [];

        foreach ($todas as $e) {
            if ($e['url'] === '' || isset($vistas[$e['url']])) {
                continue;
            }

            $vistas[$e['url']] = true;
            $out[] = $e;
        }

        return $out;
    }

    /**
     * La mejor que no baje de `$minAncho`, o la mayor que haya.
     *
     * Es lo que pide un consumidor de verdad: Telegram quiere ~1280 px y el
     * listado ~300, y ninguno de los dos quiere razonar sobre `zoom` ni sobre
     * sufijos de Amazon.
     *
     * @param  list<array{url: string, source: string, tier: string, w: ?int, h: ?int}>|list<string>  $pool
     */
    public static function pick(array $pool, int $minAncho = 0): ?string
    {
        if ($pool === []) {
            return null;
        }

        // Compatibilidad: las filas viejas guardan una lista plana de cadenas.
        if (\is_string($pool[0])) {
            return $pool[0];
        }

        foreach (array_reverse($pool) as $e) {
            if (($e['w'] ?? 0) >= $minAncho) {
                return $e['url'];
            }
        }

        return $pool[0]['url'] ?? null;
    }
}
