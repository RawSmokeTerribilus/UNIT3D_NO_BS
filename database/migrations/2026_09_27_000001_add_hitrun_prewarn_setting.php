<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fila de `hitrun.prewarn` en la tabla settings, para que el panel de
 * configuracion (Staff\ConfigManager) la muestre y la guarde.
 *
 * Hasta ahora ese plazo solo vivia en config/hitrun.php (2 dias) y nadie lo
 * veia: se usa dos veces, como dias sin actividad hasta el preaviso
 * (AutoPreWarning) y como dias desde el preaviso hasta el H&R (AutoWarning).
 *
 * El panel solo actualiza filas que ya existen, asi que hay que crearla.
 * Toma el valor que este en vigor, sin cambiar nada: insertOrIgnore no pisa
 * una fila que ya exista (por SettingSeeder o por una ejecucion anterior).
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'        => 'hitrun.prewarn',
            'value'      => (string) config('hitrun.prewarn', 2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
