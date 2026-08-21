<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Two more meta flags alongside movie/tv/game/music/no, so the e-book
        // and audiobook categories can carry their own metadata pipeline
        // instead of falling through to no-meta. Mutual exclusivity is a
        // convention enforced in Staff\CategoryController, not a constraint.
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('book_meta')->default(false)->after('movie_meta');
            $table->boolean('audiobook_meta')->default(false)->after('book_meta');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['book_meta', 'audiobook_meta']);
        });
    }
};
