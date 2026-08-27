<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra derivada de UNIT3D Community Edition (HDInnovations), de la que hereda
 * la licencia GNU AGPL v3.0.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Perks del donante que hasta ahora sólo existían a nivel de GRUPO.
     *
     * El icono de rango, el color y el efecto se leían de `groups`, así que un
     * donante sólo podía lucirlos moviéndolo de grupo — y eso tiene tres
     * trampas ya documentadas (AutoGroup congela, la caducidad no devuelve el
     * grupo, y cambiar de grupo quita is_uploader/is_trusted/can_upload).
     *
     * Con estas columnas el perk viaja con la donación y el `group_id` no se
     * toca. Se desnormaliza a `users` por lo mismo que la insignia: `user-tag`
     * se pinta cientos de veces por página y resolver la donación activa en
     * cada pintada sería un N+1 en la vista más caliente del sitio.
     *
     * `effect` guarda el atajo `background` COMPLETO, no sólo la url: hay
     * efectos que son texturas y otros que son marcos, y cada uno necesita su
     * propio tamaño y repetición.
     */
    public function up(): void
    {
        Schema::table('donation_packages', function (Blueprint $table): void {
            $table->unsignedInteger('fl_token_value')->nullable()->after('bonus_value');
            $table->string('rank_icon', 60)->nullable()->after('badge_color');
            $table->string('rank_color', 20)->nullable()->after('rank_icon');
            $table->string('effect', 255)->nullable()->after('rank_color');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('donor_rank_icon', 60)->nullable()->after('donor_badge_color');
            $table->string('donor_rank_color', 20)->nullable()->after('donor_rank_icon');
            $table->string('donor_effect', 255)->nullable()->after('donor_rank_color');
        });
    }

    public function down(): void
    {
        Schema::table('donation_packages', function (Blueprint $table): void {
            $table->dropColumn(['fl_token_value', 'rank_icon', 'rank_color', 'effect']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['donor_rank_icon', 'donor_rank_color', 'donor_effect']);
        });
    }
};
