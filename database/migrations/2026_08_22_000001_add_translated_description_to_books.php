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
        // Muchas sinopsis llegan sólo en inglés, incluso para ediciones
        // españolas. Se traducen con LibreTranslate local, con dos reglas
        // acordadas con el operador:
        //
        //   1. Se marca lo traducido. Colar una traducción automática como si
        //      fuera la sinopsis del editor es el mismo pecado que pegar la de
        //      otro libro.
        //   2. Se guarda el original. Si mañana cambia el motor, se retraduce
        //      sin volver a pedir nada al proveedor.
        Schema::table('books', function (Blueprint $table): void {
            $table->text('description_original')->nullable()->after('description');
            // Idioma del texto original. NULL = la sinopsis es nativa y no se
            // ha traducido nada.
            $table->string('description_source_language', 8)->nullable()->after('description_original');
        });

        Schema::table('audiobooks', function (Blueprint $table): void {
            $table->text('description_original')->nullable()->after('description');
            $table->string('description_source_language', 8)->nullable()->after('description_original');
        });
    }

    public function down(): void
    {
        foreach (['books', 'audiobooks'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->dropColumn(['description_original', 'description_source_language']);
            });
        }
    }
};
