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
        // El espejo de tmdb_people / tmdb_credits, para libros.
        //
        // Google Books identifica al autor sólo por su nombre en texto, y con
        // eso una ficha no puede pintar la columna de chips con foto que sí
        // tiene la de una película. OpenLibrary sí publica registros de autor
        // completos -- bio, fechas, foto, ids externos -- y su cobertura de
        // AUTORES es buena aunque la de ediciones españolas sea mala, que es
        // la razón por la que no le dejamos identificar libros.
        //
        // Medido el 2026-08-21: Tanenbaum, Rothfuss y Ruiz Zafón tienen foto;
        // aproximadamente la mitad de una muestra. Los que no, caen al
        // monograma, igual que hace la página de personas.
        Schema::create('book_authors', function (Blueprint $table): void {
            $table->string('olid', 20)->primary();          // p.ej. OL236786A
            $table->string('name');
            $table->string('personal_name')->nullable();
            $table->json('alternate_names')->nullable();
            $table->text('bio')->nullable();
            $table->string('birth_date', 64)->nullable();   // OpenLibrary los da en texto libre
            $table->string('death_date', 64)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->json('remote_ids')->nullable();         // wikidata, viaf, isni
            $table->unsignedInteger('work_count')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('book_author', function (Blueprint $table): void {
            $table->id();
            $table->string('isbn13', 13);
            $table->string('author_olid', 20);
            // El orden de autoría importa: el primero es el que se muestra
            // cuando sólo cabe uno.
            $table->unsignedTinyInteger('position')->default(0);

            $table->unique(['isbn13', 'author_olid'], 'book_author_unique');
            $table->index('isbn13');
            $table->index('author_olid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_author');
        Schema::dropIfExists('book_authors');
    }
};
