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

/**
 * hitrun_bans pasa a seguir TODOS los baneos con invitación al grupo de
 * baneados (App\Services\BanConInvitacion): los del botón de H&R y los del
 * botón de banear del perfil. «origen» los distingue en la lista de poda.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hitrun_bans', function (Blueprint $table): void {
            $table->string('origen', 16)->default('hitrun')->after('staff_id');
        });
    }
};
