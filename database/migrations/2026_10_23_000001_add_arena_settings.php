<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Filas de la Arena en la tabla settings, para que el panel de configuracion
 * (Staff\ConfigManager) las muestre y las guarde.
 *
 * `arena.is_enabled` nace en false: los contenedores de la Arena suben siempre con
 * el tracker, pero nadie entra hasta que un owner/admin lo enciende. Apagado, /arena
 * se cierra y /__arena/whoami da 401, asi que tambien corta a quien ya tenia pase.
 *
 * El panel solo actualiza filas que ya existen, asi que hay que crearlas.
 * insertOrIgnore no pisa una fila que ya exista.
 */
return new class () extends Migration {
    public function up(): void
    {
        foreach (['arena.is_enabled' => 'false', 'arena.pase_horas' => '12'] as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'key'        => $key,
                'value'      => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
