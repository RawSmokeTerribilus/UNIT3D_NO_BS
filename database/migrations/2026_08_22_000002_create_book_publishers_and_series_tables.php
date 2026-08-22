<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Editorial y saga eran texto libre en `books` y `audiobooks`, que es
        // exactamente como TMDB NO guarda companias ni colecciones. Con cinco
        // filas de datos reales ya se veia el problema: los dos audiolibros de
        // la misma saga traen
        //
        //     "Cronica del asesino de reyes [Kingkiller Chronicles]"
        //     "Cronica del asesino de reyes"
        //
        // o sea que nunca se agrupan. Y las editoriales llegan con el formato
        // que le da la gana a cada proveedor ("PLAZA & JANES" en mayusculas
        // desde Google Books, "Penguin Random House Grupo Editorial" desde
        // Audnexus).
        //
        // Las columnas de texto NO se tocan: siguen siendo lo que escriben los
        // scrapers y la fuente para rellenar estas tablas. Lo que se anade es
        // la clave normalizada, que es lo unico navegable, filtrable y
        // contable. Mismo patron que book_genres.
        Schema::create('book_publishers', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('book_series', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->unsignedInteger('book_publisher_id')->nullable()->after('publisher')->index();
            $table->unsignedInteger('book_series_id')->nullable()->after('series_position')->index();
        });

        Schema::table('audiobooks', function (Blueprint $table): void {
            $table->unsignedInteger('book_publisher_id')->nullable()->after('publisher')->index();
            $table->unsignedInteger('book_series_id')->nullable()->after('series_position')->index();
        });
    }

    public function down(): void
    {
        Schema::table('audiobooks', function (Blueprint $table): void {
            $table->dropColumn(['book_publisher_id', 'book_series_id']);
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->dropColumn(['book_publisher_id', 'book_series_id']);
        });

        Schema::dropIfExists('book_series');
        Schema::dropIfExists('book_publishers');
    }
};
