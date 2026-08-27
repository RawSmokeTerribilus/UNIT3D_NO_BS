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

use App\Models\User;
use App\Notifications\DonationExpired;
use App\Services\Unit3dAnnounce;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Throwable;

class AutoRemoveExpiredDonors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:remove_expired_donors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically remove expired donors.';

    /**
     * Execute the console command.
     *
     * @throws Exception|Throwable If there is an error during the execution of the command.
     */
    final public function handle(): void
    {
        $expiredDonors = User::where('is_donor', '=', true)
            ->where('is_lifetime', '=', false)
            ->whereHas('donations')
            ->whereDoesntHave('donations', function ($query): void {
                $query->where('ends_at', '>', Carbon::now());
            })->get();

        Notification::send($expiredDonors, new DonationExpired());

        foreach ($expiredDonors as $user) {
            $user->is_donor = false;
            // La insignia de rango caduca con la donación. Se limpia aquí y no
            // en otro sitio porque este es el único punto que apaga is_donor por
            // vencimiento; el group_id no se toca, que nunca llegó a moverse.
            $user->donor_badge_title = null;
            $user->donor_badge_icon = null;
            $user->donor_badge_color = null;
            // El icono y el efecto tambien caducan. Faltaban aqui: como el
            // render mira si el campo esta puesto y no `is_donor`, sin esto un
            // donante vencido seguiria luciendolos indefinidamente.
            $user->donor_rank_icon = null;
            $user->donor_rank_color = null;
            $user->donor_effect = null;
            $user->save();

            cache()->forget('user:'.$user->passkey);
            Unit3dAnnounce::addUser($user);
        }

        $this->info('Updated '.$expiredDonors->count().' users.');
    }
}
