<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // El rango de donante es una INSIGNIA, nunca un cambio de grupo.
        //
        // Mover a un donante de grupo tiene tres trampas, las tres verificadas
        // en este árbol:
        //
        //   1. `AutoGroup` sólo recorre usuarios cuyo grupo tenga autogroup=1
        //      (whereIntegerInRaw sobre los grupos autogroup). Un grupo
        //      "Donante" dejaría al usuario congelado ahí de por vida: ni
        //      asciende ni vuelve.
        //   2. `AutoRemoveExpiredDonors` sólo apaga is_donor. No devuelve el
        //      grupo previo, así que el rango de pago sería perpetuo.
        //   3. Cambiar de grupo quita is_uploader / is_trusted / can_upload:
        //      un Uploader que donase saldría degradado.
        //
        // Con la insignia el group_id no se toca y el rango caduca solo.
        Schema::table('donation_packages', function (Blueprint $table): void {
            // Rótulo del rango, el que se lee al pasar el ratón.
            $table->string('badge_title', 40)->nullable()->after('donor_value');
            // Clase de Font Awesome, mismo formato que `groups.icon`.
            $table->string('badge_icon', 60)->nullable()->after('badge_title');
            // Color CSS del icono. Vale hex o nombre.
            $table->string('badge_color', 20)->nullable()->after('badge_icon');
        });

        // Los mismos tres campos, copiados a la fila del usuario al aprobar la
        // donación y borrados al expirar.
        //
        // Está desnormalizado a propósito: `user-tag.blade.php` se pinta una vez
        // por cada nick de cada listado — cientos por página — y resolver ahí la
        // donación activa sería un N+1 en la vista más caliente del sitio.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('donor_badge_title', 40)->nullable()->after('is_lifetime');
            $table->string('donor_badge_icon', 60)->nullable()->after('donor_badge_title');
            $table->string('donor_badge_color', 20)->nullable()->after('donor_badge_icon');
        });
    }

    public function down(): void
    {
        Schema::table('donation_packages', function (Blueprint $table): void {
            $table->dropColumn(['badge_title', 'badge_icon', 'badge_color']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['donor_badge_title', 'donor_badge_icon', 'donor_badge_color']);
        });
    }
};
