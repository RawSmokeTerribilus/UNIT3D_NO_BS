<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Huella del contenido de un torrent, para poder decir "esto ya esta subido".
 *
 * El info_hash cambia con el piece length, la fecha de creacion y la entropia
 * que se inyecta al regenerar el .torrent, asi que no identifica contenido: en
 * produccion hay pares byte a byte identicos con info_hash distintos. La huella
 * se calcula sobre la lista de ficheros (rutas relativas + longitudes), que es
 * lo unico que no cambia.
 *
 * Indice normal, no UNIQUE: un torrent borrado tiene que poder volver a subirse,
 * y ese matiz vive en la validacion, igual que ya pasa con `name` e `info_hash`.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->char('content_hash', 40)->nullable()->after('info_hash')->index();
        });
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropIndex(['content_hash']);
            $table->dropColumn('content_hash');
        });
    }
};
