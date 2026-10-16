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
        // Las mismas dos reglas que en libros (2026_08_22_000001): lo traducido
        // se marca y se guarda el original, para enseñarlo y para no volver a
        // traducir lo mismo en cada refresco.
        //
        // TMDB: sinopsis es-ES vacía o en inglés → es-MX si la hay (escrita por
        // personas, no se marca) → la inglesa traducida. MAL sólo da inglés.
        Schema::table('tmdb_movies', function (Blueprint $table): void {
            $table->text('overview_original')->nullable()->after('overview');
            // Idioma del original. NULL = la sinopsis no es traducción automática.
            $table->string('overview_source_language', 8)->nullable()->after('overview_original');
        });

        Schema::table('tmdb_tv', function (Blueprint $table): void {
            $table->text('overview_original')->nullable()->after('overview');
            $table->string('overview_source_language', 8)->nullable()->after('overview_original');
        });

        Schema::table('mal_anime', function (Blueprint $table): void {
            $table->text('synopsis_original')->nullable()->after('synopsis');
            $table->string('synopsis_source_language', 8)->nullable()->after('synopsis_original');
        });
    }

    public function down(): void
    {
        foreach (['tmdb_movies', 'tmdb_tv'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->dropColumn(['overview_original', 'overview_source_language']);
            });
        }

        Schema::table('mal_anime', function (Blueprint $table): void {
            $table->dropColumn(['synopsis_original', 'synopsis_source_language']);
        });
    }
};
