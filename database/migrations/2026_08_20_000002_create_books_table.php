<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // E-book metadata, one row per edition.
        //
        // The primary key is the ISBN-13 rather than a provider id. Measured
        // against the live APIs on 2026-08-20: OpenLibrary answers 404 for
        // Spanish ISBNs and returns wrong books for Spanish titles, so its
        // work key does not exist for most of this catalogue. Google Books
        // does carry those editions, but keying on its volume id would pin
        // the schema to one provider. The ISBN-13 belongs to the edition, not
        // to whoever reported it, which is also the right granularity here:
        // a torrent is one edition, not an abstract work.
        //
        // ISBN-10-only volumes are converted on ingest (978 prefix, check
        // digit recomputed). A volume with no ISBN at all is simply not
        // identified and takes the manual path, same as a scanlated comic.
        Schema::create('books', function (Blueprint $table): void {
            $table->string('isbn13', 13)->primary();
            $table->string('isbn10', 10)->nullable();
            $table->string('olid', 20)->nullable()->index();       // OpenLibrary work key, enrichment only
            $table->string('google_volume_id', 32)->nullable()->index();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->json('authors')->nullable();
            $table->json('subjects')->nullable();
            $table->json('languages')->nullable();
            $table->unsignedSmallInteger('first_publish_year')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('publisher')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_url', 500)->nullable();          // the one in use
            $table->json('cover_urls')->nullable();                // ordered pool for meta:rotate-covers
            $table->json('provenance')->nullable();                // which provider supplied what
            $table->timestamps();

            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
