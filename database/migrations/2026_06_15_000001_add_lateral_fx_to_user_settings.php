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
        Schema::table('user_settings', function (Blueprint $table): void {
            // Lateral FX: animated canvas in the empty side columns beside <main>.
            // off | rain | circuit | racks | rising
            $table->string('lateral_fx', 10)->after('fx_vignette')->default('off');
            // Neon hue for the effect (HSL degrees, user-tunable 180–340).
            $table->unsignedSmallInteger('lateral_fx_hue')->after('lateral_fx')->default(322);
        });
    }
};
