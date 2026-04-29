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
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table): void {
            $table->rename('tmdb_movies');
        });

        Schema::table('tv', function (Blueprint $table): void {
            $table->rename('tmdb_tv');
        });

        Schema::table('torrents', function (Blueprint $table): void {
            $table->renameColumn('movie_id', 'tmdb_movie_id');
            $table->renameColumn('tv_id', 'tmdb_tv_id');
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->renameColumn('movie_id', 'tmdb_movie_id');
            $table->renameColumn('tv_id', 'tmdb_tv_id');
        });

        Schema::table('wishes', function (Blueprint $table): void {
            $table->renameColumn('movie_id', 'tmdb_movie_id');
            $table->renameColumn('tv_id', 'tmdb_tv_id');
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->rename('tmdb_collections');
        });

        Schema::table('collection_movie', function (Blueprint $table): void {
            $table->renameColumn('movie_id', 'tmdb_movie_id');
            $table->renameColumn('collection_id', 'tmdb_collection_id');
            $table->rename('tmdb_collection_tmdb_movie');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->rename('tmdb_companies');
        });

        Schema::table('company_movie', function (Blueprint $table): void {
            $table->renameColumn('movie_id', 'tmdb_movie_id');
            $table->renameColumn('company_id', 'tmdb_company_id');
            $table->rename('tmdb_company_tmdb_movie');
        });

        Schema::table('company_tv', function (Blueprint $table): void {
            $table->renameColumn('tv_id', 'tmdb_tv_id');
            $table->renameColumn('company_id', 'tmdb_company_id');
            $table->rename('tmdb_company_tmdb_tv');
        });

        Schema::table('genres', function (Blueprint $table): void {
            $table->rename('tmdb_genres');
        });

        Schema::table('genre_movie', function (Blueprint $table): void {
            $table->renameColumn('movie_id', 'tmdb_movie_id');
            $table->renameColumn('genre_id', 'tmdb_genre_id');
            $table->rename('tmdb_genre_tmdb_movie');
        });

        Schema::table('genre_tv', function (Blueprint $table): void {
            $table->renameColumn('tv_id', 'tmdb_tv_id');
            $table->renameColumn('genre_id', 'tmdb_genre_id');
            $table->rename('tmdb_genre_tmdb_tv');
        });

        Schema::table('networks', function (Blueprint $table): void {
            $table->rename('tmdb_networks');
        });

        Schema::table('network_tv', function (Blueprint $table): void {
            $table->renameColumn('tv_id', 'tmdb_tv_id');
            $table->renameColumn('network_id', 'tmdb_network_id');
            $table->rename('tmdb_network_tmdb_tv');
        });

        Schema::table('recommendations', function (Blueprint $table): void {
            $table->renameColumn('movie_id', 'tmdb_movie_id');
            $table->renameColumn('tv_id', 'tmdb_tv_id');
            $table->renameColumn('recommendation_movie_id', 'recommended_tmdb_movie_id');
            $table->renameColumn('recommendation_tv_id', 'recommended_tmdb_tv_id');
            $table->rename('tmdb_recommendations');
        });

        Schema::table('credits', function (Blueprint $table): void {
            $table->renameColumn('movie_id', 'tmdb_movie_id');
            $table->renameColumn('tv_id', 'tmdb_tv_id');
            $table->renameColumn('person_id', 'tmdb_person_id');
            $table->rename('tmdb_credits');
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->rename('tmdb_people');
        });

        Schema::table('seasons', function (Blueprint $table): void {
            $table->renameColumn('tv_id', 'tmdb_tv_id');
            $table->rename('tmdb_seasons');
        });

        Schema::table('episodes', function (Blueprint $table): void {
            $table->renameColumn('tv_id', 'tmdb_tv_id');
            $table->renameColumn('season_id', 'tmdb_season_id');
            $table->rename('tmdb_episodes');
        });
    }
};
