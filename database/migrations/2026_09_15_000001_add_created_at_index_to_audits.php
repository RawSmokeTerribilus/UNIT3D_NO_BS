<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El listado de /dashboard/audits ordena siempre por created_at, y sin índice
 * MySQL hace un filesort arrastrando la fila entera, con el JSON de `record`
 * dentro. Un audit de Torrent guarda mediainfo y description, viejos y nuevos:
 * una sola fila de 361 KB no cupo en el sort_buffer_size (256 KB) y la página
 * dio 500 (SQLSTATE HY001, 1038 Out of sort memory) desde el 2026-09-10.
 *
 * Con el índice, `order by created_at desc limit 25` recorre el índice hacia
 * atrás y no ordena nada, pese a lo que pese cada fila.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }
};
