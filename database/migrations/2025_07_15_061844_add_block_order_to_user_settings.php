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
            $table->renameColumn('news_visible', 'news_block_visible');
            $table->renameColumn('chat_visible', 'chat_block_visible');
            $table->renameColumn('featured_visible', 'featured_block_visible');
            $table->renameColumn('random_media_visible', 'random_media_block_visible');
            $table->renameColumn('poll_visible', 'poll_block_visible');
            $table->renameColumn('top_torrents_visible', 'top_torrents_block_visible');
            $table->renameColumn('top_users_visible', 'top_users_block_visible');
            $table->renameColumn('latest_topics_visible', 'latest_topics_block_visible');
            $table->renameColumn('latest_posts_visible', 'latest_posts_block_visible');
            $table->renameColumn('latest_comments_visible', 'latest_comments_block_visible');
            $table->renameColumn('online_visible', 'online_block_visible');

            $table->unsignedTinyInteger('news_block_position')->default(0)->after('news_block_visible');
            $table->unsignedTinyInteger('chat_block_position')->default(1)->after('chat_block_visible');
            $table->unsignedTinyInteger('featured_block_position')->default(2)->after('featured_block_visible');
            $table->unsignedTinyInteger('random_media_block_position')->default(3)->after('random_media_block_visible');
            $table->unsignedTinyInteger('poll_block_position')->default(4)->after('poll_block_visible');
            $table->unsignedTinyInteger('top_torrents_block_position')->default(5)->after('top_torrents_block_visible');
            $table->unsignedTinyInteger('top_users_block_position')->default(6)->after('top_users_block_visible');
            $table->unsignedTinyInteger('latest_topics_block_position')->default(7)->after('latest_topics_block_visible');
            $table->unsignedTinyInteger('latest_posts_block_position')->default(8)->after('latest_posts_block_visible');
            $table->unsignedTinyInteger('latest_comments_block_position')->default(9)->after('latest_comments_block_visible');
            $table->unsignedTinyInteger('online_block_position')->default(10)->after('online_block_visible');
        });
    }
};
