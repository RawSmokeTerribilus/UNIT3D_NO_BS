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
        // Two nullable id columns beside the six that already live here
        // (tmdb_movie_id, tmdb_tv_id, imdb, tvdb, mal, igdb).
        //
        // The standing rule is not to ALTER torrents for bulk columns. This is
        // a deliberate, narrow exception: two columns, not a bulk import, and
        // a side table would break five established patterns at once — the
        // mass-assign in TorrentController::store(), the per-id
        // Rule::when($category->X_meta) blocks in StoreTorrentRequest, the
        // upload blade, the torrents.similar route and the API controller.
        Schema::table('torrents', function (Blueprint $table): void {
            $table->string('isbn13', 13)->nullable()->after('igdb');
            $table->string('asin', 10)->nullable()->after('isbn13');

            $table->index('isbn13');
            $table->index('asin');
        });
    }

    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropIndex(['isbn13']);
            $table->dropIndex(['asin']);
            $table->dropColumn(['isbn13', 'asin']);
        });
    }
};
