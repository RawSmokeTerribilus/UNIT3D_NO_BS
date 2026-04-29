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
 * @author     Obi-Wana
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
        Schema::table('reports', function (Blueprint $table): void {
            // Drop existing indexes and foreign keys
            $table->dropForeign('reports_staff_id_foreign');
            $table->dropForeign('reports_reported_user_foreign');
            $table->dropIndex('reports_solved_snoozed_until_index');

            // Rename columns
            $table->renameColumn('reported_user', 'reported_user_id');
            $table->renameColumn('torrent_id', 'reported_torrent_id');
            $table->renameColumn('request_id', 'reported_request_id');
            $table->renameColumn('staff_id', 'solved_by');

            // Reorder
            $table->string('type', 255)->collation('utf8mb4_unicode_ci')->change()->after('id');
            $table->string('title', 255)->collation('utf8mb4_unicode_ci')->change()->after('type');
            $table->unsignedInteger('reporter_id')->change()->after('title');
            $table->unsignedInteger('reported_user_id')->nullable()->change()->after('reporter_id');
            $table->unsignedInteger('reported_torrent_id')->nullable()->change()->after('reported_user_id');
            $table->unsignedInteger('reported_request_id')->nullable()->change()->after('reported_torrent_id');
            $table->text('message')->collation('utf8mb4_unicode_ci')->change()->after('reported_request_id');
            $table->text('verdict')->collation('utf8mb4_unicode_ci')->change()->after('message');
            $table->timestamp('snoozed_until')->nullable()->change()->after('verdict');
            $table->timestamp('created_at')->nullable()->change()->after('snoozed_until');
            $table->timestamp('updated_at')->nullable()->change()->after('created_at');

            // Create
            $table->unsignedInteger('assigned_to')->nullable()->after('message');
            $table->timestamp('solved_at')->nullable()->after('solved_by');

            // Create/update indexes and foreign keys
            $table->index('reported_user_id');
            $table->index('reported_torrent_id');
            $table->index('reported_request_id');
            $table->index(['solved_by', 'assigned_to', 'snoozed_until']);
            $table->foreign('reported_user_id')->references('id')->on('users')->onUpdate('cascade');
            $table->foreign('solved_by')->references('id')->on('users')->onUpdate('cascade');
        });

        // Update existing reports with new columns
        $reports = DB::table('reports')
            ->where('solved', 1)
            ->update([
                'solved_at' => DB::raw('updated_at'),
            ]);

        Schema::table('reports', function (Blueprint $table): void {
            // Remove
            $table->dropColumn('solved');
        });
    }
};
