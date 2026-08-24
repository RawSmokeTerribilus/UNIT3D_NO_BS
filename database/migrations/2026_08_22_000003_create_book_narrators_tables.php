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
        // El narrador es al audiolibro lo que el director al cine: es lo que
        // distingue dos grabaciones del mismo texto, y es por lo que la gente
        // busca. Vivia en `audiobooks.narrators` como json, o sea invisible
        // para cualquier listado.
        //
        // Tabla propia y no reutilizar `book_authors`: aquella tiene el olid
        // de OpenLibrary como clave primaria y los narradores no estan en
        // OpenLibrary. Meterlos alli obligaria a inventar olids falsos.
        Schema::create('book_narrators', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('audiobook_narrator', function (Blueprint $table): void {
            $table->id();
            $table->string('asin', 10);
            $table->unsignedInteger('book_narrator_id');
            $table->unsignedInteger('position')->default(0);

            $table->unique(['asin', 'book_narrator_id'], 'audiobook_narrator_unique');
            $table->index('asin');
            $table->index('book_narrator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audiobook_narrator');
        Schema::dropIfExists('book_narrators');
    }
};
