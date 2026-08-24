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
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            // Lateral FX: animated canvas in the empty side columns beside <main>.
            // off | rain | circuit | racks | rising
            $table->string('lateral_fx', 10)->after('fx_vignette')->default('off');
            // Neon hue for the effect (HSL degrees, user-tunable 180–340).
            $table->unsignedSmallInteger('lateral_fx_hue')->after('lateral_fx')->default(322);
        });
    }
};
