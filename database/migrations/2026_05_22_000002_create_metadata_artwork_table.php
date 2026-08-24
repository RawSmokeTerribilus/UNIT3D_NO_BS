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
        // Cover/backdrop pool harvested from every metadata provider the
        // consensus resolver already queries. One row per (title, source,
        // type); meta:rotate-covers picks from it. Additive — no core table
        // is altered.
        Schema::create('metadata_artwork', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 8);                 // MOVIE | TV — keys into tmdb_movies / tmdb_tv
            $table->unsignedInteger('tmdb_id');
            $table->string('source', 16);                  // tmdb | omdb | tvmaze | jikan | anilist
            $table->string('type', 16)->default('poster'); // poster | backdrop
            $table->string('url', 500);
            $table->timestamps();
            $table->unique(['category', 'tmdb_id', 'source', 'type'], 'metadata_artwork_unique');
            $table->index(['category', 'tmdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metadata_artwork');
    }
};
