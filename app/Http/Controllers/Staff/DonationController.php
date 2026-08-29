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

namespace App\Http\Controllers\Staff;

use App\Enums\ModerationStatus;
use App\Helpers\StringHelper;
use App\Models\BonExchange;
use App\Models\Conversation;
use App\Services\Unit3dAnnounce;
use App\Models\Donation;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Models\PrivateMessage;
use App\Http\Controllers\Controller;

class DonationController extends Controller
{
    /**
     * Get All Donations.
     */
    public function index(Request $request): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        abort_unless($request->user()->group->is_owner, 403);

        $donations = Donation::with(['package' => function ($query): void {
            $query->withTrashed();
        }])->latest()->paginate(25);

        $dailyDonations = Donation::selectRaw('DATE(donations.created_at) as date, SUM(donation_packages.cost) as total')
            ->join('donation_packages', 'donations.package_id', '=', 'donation_packages.id')
            ->where('donations.status', '=', ModerationStatus::APPROVED)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $monthlyDonations = Donation::selectRaw('EXTRACT(YEAR FROM donations.created_at) as year, EXTRACT(MONTH FROM donations.created_at) as month, SUM(donation_packages.cost) as total')
            ->join('donation_packages', 'donations.package_id', '=', 'donation_packages.id')
            ->where('donations.status', '=', ModerationStatus::APPROVED)
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return view('Staff.donation.index', [
            'donations'        => $donations,
            'dailyDonations'   => $dailyDonations,
            'monthlyDonations' => $monthlyDonations,
            // El mismo número que rellena la barra del «Sostén» en la barra superior,
            // para que el panel y el botón no puedan contar cosas distintas.
            'monthlyGoal'      => (int) config('donation.monthly_goal'),
        ]);
    }

    /**
     * Update A Donation.
     */
    public function update(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()->group->is_owner, 403);

        $now = now();

        $donation = Donation::with(['user', 'package'])->findOrFail($id);
        $donation->status = ModerationStatus::APPROVED;
        $donation->starts_at = $now;

        if ($donation->package->donor_value > 0) {
            // La renovacion ENCADENA: parte de la caducidad que ya tuviera, no
            // de hoy. Antes siempre arrancaba en `$now`, asi que renovar antes
            // de tiempo regalaba los dias que quedaban — justo el castigo
            // contrario al que merece quien renueva pronto. Con el tiempo como
            // uno de los dos unicos diferenciadores entre tiers, esto importa.
            //
            // `donor_value` esta en DIAS (ojo: en `groups` los `min_age` y
            // `min_avg_seedtime` del mismo esquema van en segundos).
            $vigente = $donation->user->is_donor
                ? $donation->user
                    ->donations()
                    ->where('status', '=', ModerationStatus::APPROVED)
                    ->max('ends_at')
                : null;

            $desde = ($vigente !== null && Carbon::parse($vigente)->isFuture())
                ? Carbon::parse($vigente)
                : $now->copy();

            $donation->ends_at = $desde->addDays($donation->package->donor_value);
        } else {
            $donation->ends_at = null;
        }

        $donation->user->invites += $donation->package->invite_value ?? 0;
        $donation->user->uploaded += $donation->package->upload_value ?? 0;
        $donation->user->is_donor = true;
        $donation->user->is_lifetime = $donation->package->donor_value === null;
        $donation->user->seedbonus += $donation->package->bonus_value ?? 0;

        // Los cupones NO se abonaban. El campo existia en el paquete y el
        // mensaje de bienvenida ya los prometia, pero nadie los sumaba: el
        // donante recibia un correo diciendo que tenia N cupones y un saldo
        // de cero. `fl_tokens` es un contador simple y AutoRemoveExpiredDonors
        // no lo toca a proposito, asi que sobreviven a la caducidad.
        $donation->user->fl_tokens += $donation->package->fl_token_value ?? 0;

        // La insignia es FIJA para todo donante y vive en la config, no en el
        // paquete: es el unico de los tres perks graficos que no elige el
        // donante. Antes se copiaba de `donation_packages`, lo que obligaba a
        // repetir el mismo valor en las cinco filas y a tocar la base de datos
        // para cambiar un icono.
        //
        // Se copia al usuario en vez de resolverse por relacion porque
        // `user-tag` se pinta una vez por nick y por listado, y ahi un join
        // extra es un N+1 en la vista mas caliente del sitio.
        $donation->user->donor_badge_title = config('perks-donante.insignia.rotulo');
        $donation->user->donor_badge_icon = config('perks-donante.insignia.fichero');
        $donation->user->donor_badge_color = null;
        $donation->user->save();

        $conversation = Conversation::create(['subject' => 'Tu donación del '.$donation->created_at.' ha sido aprobada por '.$request->user()->username]);
        $conversation->users()->sync([$request->user()->id => ['read' => true], $donation->user_id]);

        PrivateMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $request->user()->id,
            'message'         => $this->mensajeDeBienvenida($donation),
        ]);

        $donation->save();

        // Dos cachés distintas, y olvidar sólo una fue el bug que costó cuatro
        // intentos: `user:{passkey}` es la del announce, pero lo que pinta la
        // web es `cachedUser.{id}` (helper CacheUser, 30 s). Sin la segunda, el
        // donante no ve su insignia hasta medio minuto después de aprobarla.
        cache()->forget('user:'.$donation->user->passkey);
        cache()->forget('cachedUser.'.$donation->user->id);
        Unit3dAnnounce::addUser($donation->user);

        return redirect()->route('staff.donations.index')
            ->with('success', 'Donación aprobada.');
    }

    /**
     * Destroy A Donation.
     */
    public function destroy(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()->group->is_owner, 403);

        $donation = Donation::findOrFail($id);
        $donation->status = ModerationStatus::REJECTED;

        $conversation = Conversation::create(['subject' => 'Tu donación del '.$donation->created_at.' ha sido rechazada por '.$request->user()->username]);
        $conversation->users()->sync([$request->user()->id => ['read' => true], $donation->user_id]);

        PrivateMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $request->user()->id,
            'message'         => 'Tu donación no ha podido aprobarse de momento. Responde a este mensaje privado y lo miramos.',
        ]);

        $donation->save();

        return redirect()->route('staff.donations.index')
            ->with('success', 'Donación rechazada.');
    }

    /**
     * Texto de bienvenida que recibe el donante al aprobarse su donación.
     *
     * Va aparte y no inline porque es el único sitio donde se le puede explicar
     * de verdad cómo funcionan los perks. En concreto los cupones de freeleech:
     * no se activan en los ajustes, se gastan en la ficha de un torrent, y
     * mientras la donación esté viva el botón ni siquiera aparece — que es
     * exactamente la confusión que costó una hora averiguar.
     */
    private function mensajeDeBienvenida(Donation $donation): string
    {
        $paquete   = $donation->package;
        $deporvida = $paquete->donor_value === null;

        $abonado = [];

        if ($paquete->bonus_value) {
            $abonado[] = '[*]'.number_format($paquete->bonus_value, 0, ',', '.').' puntos BON';
        }

        if ($paquete->fl_token_value ?? null) {
            $abonado[] = '[*]'.$paquete->fl_token_value.' '
                .($paquete->fl_token_value === 1 ? 'cupón' : 'cupones').' de freeleech';
        }

        if ($paquete->upload_value) {
            $abonado[] = '[*]'.StringHelper::formatBytes($paquete->upload_value).' de subida';
        }

        if ($paquete->invite_value) {
            $abonado[] = '[*]'.$paquete->invite_value.' invitaciones';
        }

        // El multiplicador de subida NO vive aquí: son los overrides del announce
        // Rust (DONOR_UPLOAD_FACTOR_OVERRIDE=200 y LIFETIME_..._OVERRIDE=255 en el
        // compose). Se escriben a mano porque Laravel no ve ese env, así que si
        // algún día se tocan hay que tocar también esta línea.
        $subida = $deporvida
            ? '2,55: lo que subas se te acredita multiplicado por dos y medio'
            : '2: lo que subas se te acredita al doble';

        // El precio de las 24H barra libre sale de la tienda, no escrito a mano:
        // es un objeto que se puede reajustar desde el panel y dejaría el mensaje
        // mintiendo. Si la fila no existe se cae la frase del precio, no el mensaje.
        $costeBarraLibre = BonExchange::query()
            ->where('personal_freeleech', '=', true)
            ->value('cost');

        $barraLibre = $costeBarraLibre === null
            ? 'se compra en la tienda'
            : 'se compra en la tienda por '.number_format((float) $costeBarraLibre, 0, ',', '.').' BON';

        $texto = '[b]Gracias por sostener '.config('app.name').'.[/b]'."\n\n"
            .'Tu donación queda aprobada. '
            .($deporvida
                ? 'Es [b]para siempre[/b]: no caduca.'
                : 'Es válida hasta el [b]'.$donation->ends_at->format('d/m/Y').'[/b].')
            ."\n\n";

        if ($abonado !== []) {
            $texto .= '[b]Lo que se te ha abonado, de una vez:[/b]'."\n"
                .'[list]'."\n".implode("\n", $abonado)."\n".'[/list]'."\n";
        }

        $texto .= '[b]Lo que tienes activo mientras dure:[/b]'."\n"
            .'[list]'."\n"
            .'[*]Freeleech: lo que bajes no te cuenta. Ni un byte.'."\n"
            .'[*]Subida multiplicada por '.$subida.'.'."\n"
            .'[*]Ranuras de descarga ilimitadas: se acabó el tope de descargas simultáneas de tu grupo.'."\n"
            .'[*]Inmunidad a los avisos automáticos por inactividad.'."\n"
            .'[*]Las gafas de donante y las sparkles en tu nombre, ya puestas.'."\n"
            .'[/list]'."\n"
            // Los recuentos salen de la config y no a mano por lo mismo que en la
            // página de tramos: si mañana entra un icono al catálogo, la frase se
            // actualiza sola en vez de quedarse mintiendo.
            .'[b]Y si quieres cambiarle la cara:[/b]'."\n"
            .'Las gafas y las sparkles te las hemos puesto nosotros, van solas. Lo que '
            .'seguramente no sepas es que [b]puedes cambiarlas[/b]: en [b]tu perfil → Editar[/b] '
            .'tienes los '.\count(config('perks-donante.iconos')).' iconos de rango y los '
            .\count(config('perks-donante.efectos')).' efectos del catálogo entero. No van por '
            .'tramo ni te los asignamos nosotros: eliges tú, de todo lo que hay. Si te gusta '
            .'como está, no toques nada.'."\n\n"
            .'[b]Sobre los cupones de freeleech[/b] — esto es lo que más se pregunta, y no es lo '
            .'mismo que el «24H barra libre» de la tienda. Que no te líen:'."\n"
            .'[list]'."\n"
            .'[*][b]24H barra libre[/b] — '.$barraLibre.'. Te deja [b]todo el sitio[/b] gratis '
            .'durante un día, y se acaba solo a las 24 horas.'."\n"
            .'[*][b]Cupón de freeleech[/b] — es lo que acabas de recibir. Lo gastas en [b]un '
            .'torrent concreto[/b] y ese torrent te queda gratis [b]para siempre[/b]. No caduca: '
            .'ni con la donación, ni después.'."\n"
            .'[/list]'."\n"
            .'Un cupón se gasta desde la ficha del torrent, con el botón «Usa cupón freeleech». '
            .'No hay nada que activar en los ajustes, por mucho que se busque. Sólo cabe un cupón '
            .'por torrent.'."\n"
            .'Mientras seas donante [b]no verás ese botón[/b], y es a propósito: ya bajas sin que '
            .'te cuente, así que gastarlo sería tirarlo. Tus cupones te esperan para el día que se '
            .'acabe la donación.'."\n\n"
            .'Y que quede dicho: aquí no se vende ratio. El dinero va a mantener los cacharros '
            .'encendidos, y los premios son el chiste. Gracias de verdad.';

        return $texto;
    }

}
