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
        // Los pivotes que ya existen (`book_author`, `book_genre`) van por
        // isbn13, que es la clave de un EBOOK. Un audiolibro se identifica por
        // asin y su isbn13 —cuando lo trae— es el de la edicion de audio, no
        // el del ebook:
        //
        //     El nombre del viento, ebook:      9788401352799
        //     El nombre del viento, audiolibro: 9788401017025
        //
        // Mismo libro, dos claves, ninguna relacion. Asi que reutilizar los
        // pivotes de isbn13 no une nada; hacen falta los suyos por asin.
        //
        // Los CATALOGOS si se comparten a proposito: un autor es el mismo lo
        // lea quien lo lea, y un genero es el mismo se imprima o se narre.
        // Duplicarlos daria dos hubs con la mitad del catalogo cada uno.
        Schema::create('audiobook_author', function (Blueprint $table): void {
            $table->id();
            $table->string('asin', 10);
            $table->string('author_olid', 20);
            $table->unsignedInteger('position')->default(0);

            $table->unique(['asin', 'author_olid'], 'audiobook_author_unique');
            $table->index('asin');
            $table->index('author_olid');
        });

        // Audnexus devuelve generos en castellano y utiles ("Fantasia",
        // "Epopeyas") mientras que Google Books devuelve "Fiction" y
        // "Computers". El proveedor con mejores generos era justo el que los
        // tenia en json. Este pivote los mete en el mismo catalogo.
        Schema::create('audiobook_genre', function (Blueprint $table): void {
            $table->id();
            $table->string('asin', 10);
            $table->unsignedInteger('book_genre_id');

            $table->unique(['asin', 'book_genre_id'], 'audiobook_genre_unique');
            $table->index('asin');
            $table->index('book_genre_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audiobook_genre');
        Schema::dropIfExists('audiobook_author');
    }
};
