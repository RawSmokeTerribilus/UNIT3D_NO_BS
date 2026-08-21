<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Google Books ya devuelve todo esto en la misma peticion que ya
        // hacemos; lo estabamos descartando y luego la ficha salia vacia
        // comparada con la de una pelicula. El objetivo es que construir la
        // pagina sea leer filas, no volver a preguntar a nadie.
        Schema::table('books', function (Blueprint $table): void {
            // El equivalente al "70%" que muestra la ficha de pelicula.
            $table->decimal('average_rating', 3, 2)->nullable()->after('page_count');
            $table->unsignedInteger('ratings_count')->nullable()->after('average_rating');

            // Los generos NO van aqui: van en book_genres + pivote, como
            // hace igdb_genres para juegos. Una columna json no se puede
            // indexar ni unir, asi que no permitiria navegar "todos los
            // libros de este genero", que es justo para lo que sirven.
            $table->string('maturity_rating', 24)->nullable()->after('ratings_count');
            $table->string('print_type', 16)->nullable()->after('maturity_rating');

            // Enlaces del proveedor, para la fila de logos.
            $table->string('preview_link', 500)->nullable()->after('print_type');
            $table->string('info_link', 500)->nullable()->after('preview_link');

            // Saga. Google Books no la da; OpenLibrary a veces, y Audnexus
            // siempre para audiolibros, asi que la columna se llena por el
            // lado que la tenga.
            $table->string('series')->nullable()->after('info_link');
            $table->string('series_position', 16)->nullable()->after('series');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropColumn([
                'average_rating', 'ratings_count', 'maturity_rating',
                'print_type', 'preview_link', 'info_link', 'series', 'series_position',
            ]);
        });
    }
};
