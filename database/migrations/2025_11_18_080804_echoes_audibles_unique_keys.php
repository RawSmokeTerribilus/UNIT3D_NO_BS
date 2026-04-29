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
        // Remove existing echo duplicates
        DB::table('user_echoes as e1')
            ->join('user_echoes as e2', function ($join): void {
                $join->on('e1.id', '<', 'e2.id')
                    ->whereColumn('e1.user_id', '=', 'e2.user_id')
                    ->whereColumn('e1.room_id', '=', 'e2.room_id');
            })
            ->delete();

        DB::table('user_echoes as e1')
            ->join('user_echoes as e2', function ($join): void {
                $join->on('e1.id', '<', 'e2.id')
                    ->whereColumn('e1.user_id', '=', 'e2.user_id')
                    ->whereColumn('e1.target_id', '=', 'e2.target_id');
            })
            ->delete();

        DB::table('user_echoes as e1')
            ->join('user_echoes as e2', function ($join): void {
                $join->on('e1.id', '<', 'e2.id')
                    ->whereColumn('e1.user_id', '=', 'e2.user_id')
                    ->whereColumn('e1.bot_id', '=', 'e2.bot_id');
            })
            ->delete();

        Schema::table('user_echoes', function (Blueprint $table): void {
            $table->unique(['user_id', 'room_id']);
            $table->unique(['user_id', 'target_id']);
            $table->unique(['user_id', 'bot_id']);
        });

        // Remove existing audible duplicates
        DB::table('user_audibles as a1')
            ->join('user_audibles as a2', function ($join): void {
                $join->on('a1.id', '<', 'a2.id')
                    ->whereColumn('a1.user_id', '=', 'a2.user_id')
                    ->whereColumn('a1.room_id', '=', 'a2.room_id');
            })
            ->delete();

        DB::table('user_audibles as a1')
            ->join('user_audibles as a2', function ($join): void {
                $join->on('a1.id', '<', 'a2.id')
                    ->whereColumn('a1.user_id', '=', 'a2.user_id')
                    ->whereColumn('a1.target_id', '=', 'a2.target_id');
            })
            ->delete();

        DB::table('user_audibles as a1')
            ->join('user_audibles as a2', function ($join): void {
                $join->on('a1.id', '<', 'a2.id')
                    ->whereColumn('a1.user_id', '=', 'a2.user_id')
                    ->whereColumn('a1.bot_id', '=', 'a2.bot_id');
            })
            ->delete();

        Schema::table('user_audibles', function (Blueprint $table): void {
            $table->unique(['user_id', 'room_id']);
            $table->unique(['user_id', 'target_id']);
            $table->unique(['user_id', 'bot_id']);
        });
    }
};
