<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Degradado opcional para el nombre de los grupos «épicos» (Mentor, Custodio,
 * Druida, Artesano, Admin, #root): de 2 a 3 colores hex separados por comas.
 * Si es null, el nombre se pinta con `color` como siempre. `color` sigue
 * siendo obligatorio: es el respaldo en los 21 sitios que sólo saben pintar
 * un color sólido (estadísticas, tablas del staff...). Ver Group::estiloTexto().
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->string('gradient', 64)->nullable()->after('color');
        });
    }
};
