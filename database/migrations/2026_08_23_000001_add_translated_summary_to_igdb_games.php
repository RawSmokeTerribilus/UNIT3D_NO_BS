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
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // IGDB indexa su catálogo SÓLO en inglés: no hay parámetro de idioma
        // que pedirle, así que la sinopsis de un juego llega siempre en
        // inglés aunque la copia sea española. Medido el 2026-08-22 con el
        // romset de ScummVM en castellano.
        //
        // Mismas dos reglas que en `books`, y por eso las columnas se llaman
        // igual salvo el prefijo:
        //
        //   1. Se marca lo traducido, porque colar una traducción automática
        //      como si fuera el texto del editor engaña al que lo lee.
        //   2. Se guarda el original, para poder retraducir si cambia el
        //      motor sin volver a pedirle nada a IGDB.
        Schema::table('igdb_games', function (Blueprint $table): void {
            $table->text('summary_original')->nullable()->after('summary');
            // Idioma del original. NULL = el resumen es nativo, sin traducir.
            $table->string('summary_source_language', 8)->nullable()->after('summary_original');
        });
    }

    public function down(): void
    {
        Schema::table('igdb_games', function (Blueprint $table): void {
            $table->dropColumn(['summary_original', 'summary_source_language']);
        });
    }
};
