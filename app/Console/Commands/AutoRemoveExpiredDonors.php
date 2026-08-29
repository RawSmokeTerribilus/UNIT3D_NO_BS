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
use Illuminate\Support\Facades\Storage;
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
        // `with('group')` porque el bucle pregunta `esStaff()` por usuario: sin
        // esto es una consulta por donante vencido.
        $expiredDonors = User::with('group')
            ->where('is_donor', '=', true)
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
            //
            // Al staff NO se le quitan: su derecho a elegirlos viene del cargo,
            // no de la donacion, igual que con el icono propio de mas abajo. La
            // insignia si se va en todos los casos, porque esa dice «he donado»
            // y eso ha dejado de ser cierto; el icono y el efecto son solo
            // personalizacion.
            if (!$user->group->esStaff()) {
                $user->donor_rank_icon = null;
                $user->donor_rank_color = null;
                $user->donor_effect = null;
            }

            // El icono propio (imagen subida por el usuario) tambien caduca.
            // Antes no hacia falta limpiarlo porque solo lo tenian lifetime y
            // staff, que no vencen nunca; al abrirlo a todo donante, sin esto
            // una donacion de un mes dejaba un adorno permanente.
            //
            // El staff lo conserva MIENTRAS sea staff: su derecho no viene de
            // la donacion sino del cargo, y `esStaff()` es la definicion unica
            // de eso — la misma que abre el campo en el formulario.
            //
            // Se borra tambien el fichero, no solo la fila: es lo que ya hace
            // `User\UserController` al reemplazar un icono, y si no el disco
            // acumula huerfanos que nadie vuelve a mirar.
            if ($user->icon !== null && !$user->group->esStaff()) {
                Storage::disk('user-icons')->delete($user->icon);
                $user->icon = null;
            }

            $user->save();

            // Dos cachés: `user:{passkey}` la lee el announce, `cachedUser.{id}`
            // la web. Olvidar solo la primera dejaba al donante vencido luciendo
            // sus perks otros 30 s.
            cache()->forget('user:'.$user->passkey);
            cache()->forget('cachedUser.'.$user->id);
            Unit3dAnnounce::addUser($user);
        }

        $this->info('Updated '.$expiredDonors->count().' users.');
    }
}
