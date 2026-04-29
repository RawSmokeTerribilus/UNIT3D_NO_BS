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
        Schema::create('playlist_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->smallInteger('position');
            $table->string('name');
        });

        DB::table('playlist_categories')->insert([
            'id'       => 1,
            'position' => 100,
            'name'     => 'Other',
        ]);

        Schema::table('playlists', function (Blueprint $table): void {
            // Add the default 1 to migrate all existing playlists to the newly created
            // "Other" category, and then remove the default so that sysops can delete
            // the "Other" category if they want (and it contains no playlists).
            $table->unsignedInteger('playlist_category_id')->after('id')->default(1);
            $table->unsignedInteger('playlist_category_id')->change();

            $table->foreign('playlist_category_id')->references('id')->on('playlist_categories')->cascadeOnUpdate();
        });
    }
};
