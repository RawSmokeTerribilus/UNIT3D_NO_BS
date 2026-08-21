<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Audiobook metadata, keyed by Audible ASIN because that is the only
        // identifier Audnexus accepts and the only one that distinguishes one
        // recording of a book from another. Note the ASIN is not always a
        // B0-style code: Audible reuses the ISBN-10 for part of its catalogue,
        // so the column is a plain 10-char string.
        //
        // Narrator and runtime live here and nowhere else. They are what makes
        // two audiobook editions of the same title different releases, so a
        // torrent page without them is missing the point of the category.
        Schema::create('audiobooks', function (Blueprint $table): void {
            $table->string('asin', 10)->primary();
            $table->string('region', 2)->default('es');            // Audible marketplace the record came from
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->json('authors')->nullable();
            $table->json('narrators')->nullable();
            $table->string('series')->nullable();
            $table->string('series_position', 16)->nullable();
            $table->unsignedInteger('runtime_length_min')->nullable();
            $table->date('release_date')->nullable();
            $table->string('publisher')->nullable();
            $table->string('language', 32)->nullable();
            $table->json('genres')->nullable();
            $table->string('isbn13', 13)->nullable()->index();     // when Audnexus reports one, links back to books
            $table->text('description')->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->json('cover_urls')->nullable();
            $table->json('provenance')->nullable();
            $table->timestamps();

            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audiobooks');
    }
};
