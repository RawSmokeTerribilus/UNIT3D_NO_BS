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
            $table->unsignedInteger('imdb')->nullable()->change();
            $table->unsignedInteger('tvdb')->nullable()->change();
            $table->unsignedInteger('mal')->nullable()->change();
        });

        DB::table('torrents')->where('tmdb_movie_id', '=', 0)->update(['tmdb_movie_id' => null]);
        DB::table('torrents')->where('tmdb_tv_id', '=', 0)->update(['tmdb_tv_id' => null]);
        DB::table('torrents')->where('imdb', '=', 0)->update(['imdb' => null]);
        DB::table('torrents')->where('tvdb', '=', 0)->update(['tvdb' => null]);
        DB::table('torrents')->where('mal', '=', 0)->update(['mal' => null]);
        DB::table('torrents')->where('igdb', '=', 0)->update(['igdb' => null]);

        DB::table('requests')->where('tmdb_movie_id', '=', 0)->update(['tmdb_movie_id' => null]);
        DB::table('requests')->where('tmdb_tv_id', '=', 0)->update(['tmdb_tv_id' => null]);
        DB::table('requests')->where('imdb', '=', 0)->update(['imdb' => null]);
        DB::table('requests')->where('tvdb', '=', 0)->update(['tvdb' => null]);
        DB::table('requests')->where('mal', '=', 0)->update(['mal' => null]);
        DB::table('requests')->where('igdb', '=', 0)->update(['igdb' => null]);
    }
};
