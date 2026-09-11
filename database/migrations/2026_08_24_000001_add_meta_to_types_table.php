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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A qué clase de obra pertenece cada tipo.
     *
     * `types` es global en UNIT3D --sólo `id`, `name` y `position`-- y no dice
     * si un tipo es de vídeo, de libro o de juego. Mientras el tracker fue
     * sólo de vídeo daba igual. Al entrar libros y juegos aparecieron diez
     * tipos nuevos y el agujero se hizo visible en dos sitios:
     *
     *   1. `/missing` pintaba los 16 tipos como columna en las 3.210 filas de
     *      película: más de 32.000 celdas rojas diciendo que a una película le
     *      "falta" el EPUB.
     *   2. El formulario de subida ofrece "Full Disc" y "HDTV" para un e-book,
     *      y "EPUB" para una película.
     *
     * NULL significa "sin clasificar", y quien consulte debe tratarlo como
     * "vale para todo": así un tipo que alguien añada a mano sigue saliendo en
     * todas partes en vez de desaparecer en silencio.
     */
    public function up(): void
    {
        Schema::table('types', function (Blueprint $table): void {
            $table->string('meta', 16)->nullable()->after('position');
        });

        $this->clasificarPorLosDatos();
        $this->clasificarPorElNombre();
    }

    public function down(): void
    {
        Schema::table('types', function (Blueprint $table): void {
            $table->dropColumn('meta');
        });
    }

    /**
     * Lo que se puede afirmar: un tipo usado por torrents de una categoría de
     * vídeo es de vídeo. Es la fuente más fiable porque son datos reales.
     *
     * Un tipo usado por categorías de dos clases distintas se deja a NULL: no
     * se puede decidir por él, y NULL ya significa "vale para todo".
     */
    private function clasificarPorLosDatos(): void
    {
        $banderas = [
            'video'     => ['movie_meta', 'tv_meta'],
            'book'      => ['book_meta'],
            'audiobook' => ['audiobook_meta'],
            'game'      => ['game_meta'],
        ];

        /** @var array<int, list<string>> $vistos */
        $vistos = [];

        foreach ($banderas as $meta => $columnas) {
            $ids = DB::table('torrents')
                ->distinct()
                ->whereNotNull('type_id')
                ->whereIn('category_id', function ($q) use ($columnas): void {
                    $q->select('id')->from('categories');

                    foreach ($columnas as $i => $columna) {
                        $i === 0 ? $q->where($columna, '=', true) : $q->orWhere($columna, '=', true);
                    }
                })
                ->pluck('type_id');

            foreach ($ids as $id) {
                $vistos[(int) $id][] = $meta;
            }
        }

        foreach ($vistos as $id => $metas) {
            $metas = array_unique($metas);

            if (\count($metas) === 1) {
                DB::table('types')->where('id', '=', $id)->update(['meta' => reset($metas)]);
            }
        }
    }

    /**
     * Los tipos que todavía no tiene nadie subidos no dejan rastro en los
     * datos, así que se clasifican por su nombre. Es un mapa cerrado y sólo se
     * aplica a los que sigan a NULL: nunca pisa lo deducido arriba.
     */
    private function clasificarPorElNombre(): void
    {
        $mapa = [
            'video'     => ['full disc', 'remux', 'encode', 'web-dl', 'webrip', 'hdtv', 'bluray', 'dvd'],
            'book'      => ['epub', 'pdf', 'mobi', 'azw3', 'cbz/cbr', 'cbz', 'cbr'],
            'audiobook' => ['m4b', 'mp3 (audiolibro)'],
            'game'      => ['scummvm', 'rom', 'pc'],
        ];

        foreach ($mapa as $meta => $nombres) {
            DB::table('types')
                ->whereNull('meta')
                ->whereIn(DB::raw('LOWER(name)'), $nombres)
                ->update(['meta' => $meta]);
        }
    }
};
