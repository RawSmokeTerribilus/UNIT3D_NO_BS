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

namespace App\Services\Translation;

/**
 * Sinopsis en castellano para las fichas de TMDB y MAL.
 *
 * TMDB, por orden: la es-ES si está y es castellano; si no, otra variante del
 * castellano (es-MX casi siempre), que está escrita por personas y no se marca;
 * si no, la inglesa traducida con el LibreTranslate local, marcada y con el
 * original guardado. Las variantes vienen en la misma llamada
 * (`append_to_response=translations`), sin pedir nada más a TMDB.
 *
 * MAL sólo da inglés: se traduce siempre.
 *
 * Si el original no ha cambiado desde la última vez, se reutiliza la
 * traducción guardada: las fichas se refrescan cada pocas horas y no hay que
 * retraducir lo mismo. Si el traductor no responde, se queda el inglés: peor
 * que castellano, mejor que nada.
 */
final class SinopsisEnCastellano
{
    /**
     * @param  array<string, mixed>|null $data           Respuesta cruda de TMDB, con `translations`
     * @param  array<int, string|null>   $proteger       Títulos que el motor no debe traducir
     * @return array{overview: ?string, overview_original: ?string, overview_source_language: ?string}
     */
    public static function tmdb(?array $data, ?string $previaTexto, ?string $previaOriginal, array $proteger): array
    {
        $es = trim((string) ($data['overview'] ?? ''));
        $porIdioma = [];

        foreach ($data['translations']['translations'] ?? [] as $t) {
            $texto = trim((string) ($t['data']['overview'] ?? ''));

            if ($texto !== '') {
                $porIdioma[($t['iso_639_1'] ?? '').'-'.($t['iso_3166_1'] ?? '')] = $texto;
                $proteger[] = ($t['iso_639_1'] ?? '') === 'en' ? ($t['data']['title'] ?? $t['data']['name'] ?? null) : null;
            }
        }

        [$texto, $original, $idioma] = self::elegir($es, $porIdioma, $previaTexto, $previaOriginal, $proteger);

        return ['overview' => $texto, 'overview_original' => $original, 'overview_source_language' => $idioma];
    }

    /**
     * @param  array<int, string|null> $proteger
     * @return array{synopsis: ?string, synopsis_original: ?string, synopsis_source_language: ?string}
     */
    public static function mal(?string $synopsis, ?string $previaTexto, ?string $previaOriginal, array $proteger): array
    {
        $en = trim((string) $synopsis);

        [$texto, $original, $idioma] = self::elegir('', $en === '' ? [] : ['en-US' => $en], $previaTexto, $previaOriginal, $proteger);

        return ['synopsis' => $texto, 'synopsis_original' => $original, 'synopsis_source_language' => $idioma];
    }

    /**
     * @param  array<string, string>     $porIdioma Sinopsis por código («es-MX», «en-US»…)
     * @param  array<int, string|null>   $proteger
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private static function elegir(string $es, array $porIdioma, ?string $previaTexto, ?string $previaOriginal, array $proteger): array
    {
        if ($es !== '' && LibreTranslateClient::pareceCastellano($es)) {
            return [$es, null, null];
        }

        foreach ($porIdioma as $codigo => $texto) {
            if (str_starts_with($codigo, 'es-') && LibreTranslateClient::pareceCastellano($texto)) {
                return [$texto, null, null];
            }
        }

        // La inglesa de EE. UU., cualquier otra inglesa, o lo que TMDB tenga
        // en el hueco de es-ES si alguien escribió ahí en inglés.
        $ingles = $porIdioma['en-US'] ?? null;

        foreach ($porIdioma as $codigo => $texto) {
            $ingles ??= str_starts_with($codigo, 'en-') ? $texto : null;
        }

        $ingles ??= $es;

        if ($ingles === '') {
            return [null, null, null];
        }

        if ($previaOriginal === $ingles && trim((string) $previaTexto) !== '') {
            return [$previaTexto, $ingles, 'en'];
        }

        $traducido = app(LibreTranslateClient::class)->translateKeeping($ingles, array_filter($proteger), 'en', 'es');

        return $traducido === '' ? [$ingles, null, null] : [$traducido, $ingles, 'en'];
    }
}
