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
        Schema::create('mal_anime', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary(); // MAL ID — no auto-increment
            $table->string('title');
            $table->string('title_english')->nullable();
            $table->string('title_japanese')->nullable();
            $table->text('synopsis')->nullable();
            $table->float('mean')->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->unsignedInteger('num_episodes')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('media_type', 50)->nullable(); // tv, movie, ova, ona, special, music
            $table->string('status', 50)->nullable();     // finished_airing, currently_airing, not_yet_aired
            $table->string('nsfw', 20)->nullable();       // white, gray, black
            $table->string('poster')->nullable();
            $table->json('genres')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mal_anime');
    }
};
