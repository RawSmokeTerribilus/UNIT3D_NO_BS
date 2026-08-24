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
            // Lateral FX user knobs (multipliers fed to the canvas engine).
            // Density: element count multiplier (sparse 0.6 / normal 1 / dense 1.4).
            $table->decimal('lateral_fx_density', 3, 2)->after('lateral_fx_hue')->default(1.00);
            // Speed: flow speed multiplier (calm 0.6 / normal 1 / fast 1.6).
            $table->decimal('lateral_fx_speed', 3, 2)->after('lateral_fx_density')->default(1.00);
        });
    }
};
