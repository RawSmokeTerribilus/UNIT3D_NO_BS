<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     Roardom <roardom@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('torrents', function (Blueprint $table): void {
            $table->unsignedInteger('movie_id')->nullable()->after('user_id')->index();
            $table->unsignedInteger('tv_id')->nullable()->after('movie_id')->index();

            $table->dropIndex(['category_id', 'status', 'deleted_at', 'tmdb', 'size']);
            $table->index(['category_id', 'status', 'deleted_at', 'movie_id', 'size']);
            $table->index(['category_id', 'status', 'deleted_at', 'tv_id', 'size']);
        });

        DB::table('torrents')
            ->whereIn('category_id', DB::table('categories')->select('id')->where('movie_meta', '=', true))
            ->update([
                'movie_id' => DB::raw('tmdb'),
            ]);

        DB::table('torrents')
            ->whereIn('category_id', DB::table('categories')->select('id')->where('tv_meta', '=', true))
            ->update([
                'tv_id' => DB::raw('tmdb'),
            ]);

        Schema::table('torrents', function (Blueprint $table): void {
            $table->dropColumn('tmdb');
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->unsignedInteger('movie_id')->nullable()->after('user_id')->index();
            $table->unsignedInteger('tv_id')->nullable()->after('movie_id')->index();
        });

        DB::table('requests')
            ->whereIn('category_id', DB::table('categories')->select('id')->where('movie_meta', '=', true))
            ->update([
                'movie_id' => DB::raw('tmdb'),
            ]);

        DB::table('requests')
            ->whereIn('category_id', DB::table('categories')->select('id')->where('tv_meta', '=', true))
            ->update([
                'tv_id' => DB::raw('tmdb'),
            ]);

        Schema::table('requests', function (Blueprint $table): void {
            $table->dropColumn('tmdb');
        });
    }
};
