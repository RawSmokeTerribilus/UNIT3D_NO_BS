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

namespace App\Console\Commands;

use App\Enums\UserGroup;
use App\Models\Group;
use App\Models\User;
use App\Services\LeechAmnesty;
use App\Services\Unit3dAnnounce;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class AutoGroup extends Command
{
    /**
     * NOBS: el grupo Fundador es una COHORTE CERRADA, no un escalon de
     * antiguedad. Solo lo ve quien se dio de alta antes de esta fecha: la
     * fundacion por invitacion y el registro abierto de mayo (los beta testers
     * del nuke de la BD). `min_age` no sirve para esto porque la antiguedad
     * crece y acabaria metiendo a todo el mundo. Se filtra por slug, que es
     * identidad y no cambia al renombrar el grupo.
     */
    private const string FOUNDER_SLUG = 'fundador';

    private const string FOUNDATION_END = '2026-05-18 00:00:00';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:group {user_ids?*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically change a users group class if requirements met';

    /**
     * Execute the console command.
     *
     * @throws Exception|Throwable If there is an error during the execution of the command.
     */
    final public function handle(): void
    {
        $now = now();
        $timestamp = $now->timestamp;

        $groups = Group::query()
            ->where('autogroup', '=', 1)
            ->orderByDesc('position')
            ->get();

        $userIds = array_map('intval', (array) $this->argument('user_ids'));

        $userQuery = User::query()
            ->withSum('seedingTorrents as seedsize', 'size')
            ->withCount([
                'torrents as uploads',
                'warnings' => fn ($query) => $query->where('active', '=', true),
            ])
            ->withAvg('history as avg_seedtime', 'seedtime');

        if ($userIds !== []) {
            $userQuery->whereIntegerInRaw('id', $userIds);
        } else {
            $userQuery->whereIntegerInRaw('group_id', $groups->pluck('id'));
        }

        $foundationEnd = Carbon::parse(self::FOUNDATION_END)->timestamp;

        $userQuery->chunkById(100, function ($users) use ($groups, $timestamp, $foundationEnd): void {
            foreach ($users as $user) {
                foreach ($groups as $group) {
                    if ($group->slug === self::FOUNDER_SLUG && $user->created_at->timestamp >= $foundationEnd) {
                        continue;
                    }

                    if (
                        ($group->min_uploaded === null || $user->uploaded >= $group->min_uploaded)
                        && ($group->min_ratio === null || $user->ratio >= $group->min_ratio)
                        && ($group->min_age === null || $timestamp - $user->created_at->timestamp >= $group->min_age)
                        && ($group->min_avg_seedtime === null || $user->avg_seedtime >= $group->min_avg_seedtime)
                        && ($group->min_seedsize === null || $user->seedsize >= $group->min_seedsize)
                        && ($group->min_uploads === null || $user->uploads >= $group->min_uploads)
                    ) {
                        $user->group_id = $group->id;

                        // Leech ratio dropped below sites minimum
                        if ($user->group_id === UserGroup::LEECH->value) {
                            // Keep these as 0/1 instead of false/true
                            // because it reduces 6% custom casting overhead
                            //
                            // NOBS: durante la amnistia del freeleech el grupo
                            // conserva la descarga salvo que se la haya quitado
                            // el H&R. Sin esta rama, este comando nocturno
                            // deshacia la amnistia entera cada madrugada.
                            $user->can_download = LeechAmnesty::isActive()
                                && $user->warnings_count < config('hitrun.max_warnings')
                                    ? 1
                                    : 0;
                        } elseif ($user->warnings_count < config('hitrun.max_warnings')) {
                            $user->can_download = 1;
                        }

                        $user->save();

                        if ($user->wasChanged()) {
                            cache()->forget('user:'.$user->passkey);

                            Unit3dAnnounce::addUser($user);
                        }

                        break;
                    }
                }
            }
        });

        $elapsed = (int) $now->diffInSeconds(now(), true);
        $this->comment('Automated user group command complete ('.$elapsed.' s)');
    }
}
