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
        // El espejo de igdb_genres, y por la misma razon: un genero en tabla
        // se puede navegar, filtrar y contar; en una columna json no.
        //
        // Se llena con `categories` de Google Books, que devuelve pocas y
        // limpias ("Computer networks"). Las `subjects` de OpenLibrary se
        // quedan en su columna json: son decenas y ruidosas, valen para
        // mostrar pero no para navegar.
        Schema::create('book_genres', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('book_genre', function (Blueprint $table): void {
            $table->id();
            $table->string('isbn13', 13);
            $table->unsignedInteger('book_genre_id');

            $table->unique(['isbn13', 'book_genre_id'], 'book_genre_unique');
            $table->index('isbn13');
            $table->index('book_genre_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_genre');
        Schema::dropIfExists('book_genres');
    }
};
