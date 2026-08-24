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
        // Same two id columns the torrents table got, so a member can request
        // a specific edition of a book instead of the request falling through
        // to no-meta with nothing but a free-text title.
        Schema::table('requests', function (Blueprint $table): void {
            $table->string('isbn13', 13)->nullable()->after('igdb');
            $table->string('asin', 10)->nullable()->after('isbn13');

            $table->index('isbn13');
            $table->index('asin');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropIndex(['isbn13']);
            $table->dropIndex(['asin']);
            $table->dropColumn(['isbn13', 'asin']);
        });
    }
};
