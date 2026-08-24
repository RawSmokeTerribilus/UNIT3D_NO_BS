<?php

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
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Handle público de Telegram (@username). Cacheado de forma dinámica
            // desde el webhook y las consultas en vivo del staff. No es único:
            // los handles cambian y pueden reutilizarse; nullable porque no todos
            // los usuarios tienen handle público.
            $table->string('telegram_username', 64)->nullable()->after('telegram_token');
            $table->index('telegram_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['telegram_username']);
            $table->dropColumn('telegram_username');
        });
    }
};
