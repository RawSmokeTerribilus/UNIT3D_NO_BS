<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Output ledger of the metadata consensus resolver — one row per
        // torrent. Records the cross-checked id-set and how trusted it is, so
        // a torrent counts as "well-configured" when it carries a high-
        // confidence resolution. Additive: no users/torrents table is altered.
        Schema::create('metadata_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('torrent_id')->unique(); // no FK — a stale row is harmless and re-runs overwrite
            $table->string('category', 8);                   // MOVIE | TV
            $table->unsignedInteger('tmdb_id')->nullable()->index();
            $table->unsignedInteger('tvdb_id')->nullable();
            $table->string('imdb_id')->nullable()->index();  // ttNNNNNNN form
            $table->unsignedInteger('mal_id')->nullable();
            $table->string('resolved_title')->nullable();
            $table->unsignedSmallInteger('resolved_year')->nullable();
            $table->string('confidence', 8)->index();        // high | low | none
            $table->unsignedTinyInteger('votes')->default(0);
            $table->unsignedTinyInteger('mal_votes')->default(0);
            $table->json('detail')->nullable();              // per-provider candidate breakdown (incl. tvmaze/anilist ids)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metadata_resolutions');
    }
};
