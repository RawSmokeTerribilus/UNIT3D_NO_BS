<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los laterales animados van encendidos de serie.
 *
 * Decision de producto: la experiencia completa es la de serie, y quien no la
 * quiera la apaga en su perfil. Antes el defecto era 'off' en dos sitios a la
 * vez —esta columna y el withDefault() de User::settings()— asi que nadie los
 * veia salvo que fuese a buscarlos.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE `user_settings` ALTER COLUMN `lateral_fx` SET DEFAULT 'rising'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `user_settings` ALTER COLUMN `lateral_fx` SET DEFAULT 'off'");
    }
};
