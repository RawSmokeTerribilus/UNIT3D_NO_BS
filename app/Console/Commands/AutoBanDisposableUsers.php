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
 * @credit     PyR8zdl
 */

namespace App\Console\Commands;

use App\Models\Ban;
use App\Models\DisposableEmailDomain;
use App\Models\Group;
use App\Models\User;
use App\Notifications\UserBan;
use App\Rules\EmailBlacklist;
use App\Services\Unit3dAnnounce;
use Illuminate\Console\Command;
use Exception;
use Throwable;

class AutoBanDisposableUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:ban_disposable_users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ban user if they are using a disposable email';

    /**
     * Execute the console command.
     *
     * @throws Exception|Throwable If there is an error during the execution of the command.
     */
    final public function handle(): void
    {
        try {
            \Log::info('AutoBanDisposableUsers: Starting');
            
            // Check if blacklist is enabled
            if (!config('email-blacklist.enabled')) {
                $this->comment('Email blacklist is disabled. Skipping!');
                \Log::info('AutoBanDisposableUsers: Email blacklist disabled, skipping');
                return;
            }

            // Check if database has any domains
            $domainCount = DisposableEmailDomain::count();
            if ($domainCount === 0) {
                $this->comment('No disposable domains in database. Run: php artisan email-blacklist:sync');
                \Log::info('AutoBanDisposableUsers: No domains in DB, skipping');
                return;
            }

            $bannedGroupId = Group::where('slug', '=', 'banned')->soleValue('id');
            \Log::info('AutoBanDisposableUsers: Got banned group ID ' . $bannedGroupId);
            \Log::info('AutoBanDisposableUsers: Loaded ' . $domainCount . ' blacklisted domains from DB');

            $bannedCount = 0;
            $usersProcessed = 0;
            
            // Get all non-banned users
            $users = User::where('group_id', '!=', $bannedGroupId)->get();
            \Log::info('AutoBanDisposableUsers: Processing ' . count($users) . ' users');

            foreach ($users as $user) {
                $usersProcessed++;
                
                try {
                    // Extract domain from email (fast)
                    $domain = strtolower(substr($user->email, strpos($user->email, '@') + 1));
                    
                    // Check if domain is disposable using DB model
                    if (DisposableEmailDomain::isDisposable($domain)) {
                        // Ban the user
                        $user->update([
                            'group_id'     => $bannedGroupId,
                            'can_download' => 0,
                        ]);

                        Ban::create([
                            'owned_by'     => $user->id,
                            'created_by'   => User::SYSTEM_USER_ID,
                            'ban_reason'   => 'Detected disposable email, ' . $domain . ' not allowed.',
                            'unban_reason' => '',
                        ]);

                        $bannedCount++;
                        \Log::info('AutoBanDisposableUsers: Banned ' . $user->username . ' (' . $domain . ')');
                    }

                    // Clear cache
                    cache()->forget('user:'.$user->passkey);
                    
                } catch (\Throwable $e) {
                    \Log::error('AutoBanDisposableUsers: Error #' . $usersProcessed, ['msg' => substr($e->getMessage(), 0, 100)]);
                }
            }

            
            \Log::info('AutoBanDisposableUsers: Complete - ' . $bannedCount . ' banned, ' . $usersProcessed . ' processed');
            $this->comment('✅ Automated user banning complete');
            $this->comment('   Processed: ' . $usersProcessed);
            $this->comment('   Banned: ' . $bannedCount);
            
        } catch (\Throwable $e) {
            \Log::error('AutoBanDisposableUsers: Fatal error', ['error' => $e->getMessage()]);
            $this->error('Fatal error: ' . $e->getMessage());
        }
    }
}
