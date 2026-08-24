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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('show_poster')->default(true)->change();
        });

        DB::table('user_settings')->where('show_poster', false)->update(['show_poster' => true]);
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('show_poster')->default(false)->change();
        });
    }
};
