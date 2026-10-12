<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fila de `donation.aviso_telegram` en la tabla settings, para que el panel de
 * configuracion (Staff\ConfigManager) la muestre y la guarde.
 *
 * Con el interruptor encendido, cada donacion nueva manda un privado por
 * Telegram a los Owners que tengan la cuenta vinculada
 * (App\Jobs\AvisarDonacionPendiente).
 *
 * El panel solo actualiza filas que ya existen, asi que hay que crearla.
 * insertOrIgnore no pisa una fila que ya exista.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'        => 'donation.aviso_telegram',
            'value'      => 'true',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
