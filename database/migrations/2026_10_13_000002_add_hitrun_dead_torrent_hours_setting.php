<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fila de `hitrun.dead_torrent_hours` en la tabla settings, para que el panel
 * de configuracion la muestre y la guarde.
 *
 * Es el plazo de los torrents muertos, que vale tres veces: horas sin semilla,
 * antiguedad minima del torrent y espera entre pedir la resiembra y borrar.
 * 0 = el mismo que el tiempo minimo de seed del H&R (hitrun.seedtime), que es
 * lo que se decidio el 2026-10-13. Se separa poniendo aqui otro numero.
 *
 * insertOrIgnore no pisa una fila que ya exista.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'        => 'hitrun.dead_torrent_hours',
            'value'      => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
