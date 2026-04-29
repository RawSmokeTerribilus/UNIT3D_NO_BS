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
        DB::table('torrents')->whereNull('balance')->update([
            'balance' => 0,
        ]);

        DB::table('torrents')->whereNull('balance_offset')->update([
            'balance_offset' => 0,
        ]);

        Schema::table('torrents', function (Blueprint $table): void {
            $table->bigInteger('balance')->default(0)->change();
            $table->bigInteger('balance_offset')->default(0)->change();
            $table->timestamp('balance_reset_at')->after('balance_offset')->nullable();
        });
    }
};
