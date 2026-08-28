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

namespace App\Http\Controllers;

use App\Enums\ModerationStatus;
use App\Http\Requests\StoreDonationRequest;
use App\Models\Donation;
use App\Models\DonationGateway;
use App\Models\DonationPackage;

class DonationController extends Controller
{
    /**
     * El interruptor de upstream sólo escondía el enlace.
     *
     * `donation.is_enabled` se comprobaba en DOS vistas — el menú superior y el
     * panel de staff — y en ningún sitio más. Con las donaciones apagadas la
     * ruta /donations seguía sirviéndose entera a cualquiera que la escribiera a
     * mano o la tuviera en el historial, y /donations/store seguía aceptando
     * envíos. Que la papelera esté escondida no significa que esté cerrada.
     *
     * 404 y no 403: apagado, esta parte del sitio no existe. Un 403 confirmaría
     * que existe pero está prohibida, que es información que no hace falta dar.
     */
    private function abortSiApagado(): void
    {
        abort_unless((bool) config('donation.is_enabled'), 404);
    }

    /**
     * Display Donation Page.
     */
    public function index(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        $this->abortSiApagado();

        $packages = DonationPackage::where('is_active', '=', true)->orderBy('position')->get();
        $gateways = DonationGateway::where('is_active', '=', true)->orderBy('position')->get();

        return view('donation.index', ['packages' => $packages, 'gateways' => $gateways]);
    }

    /**
     * Store A Donation.
     */
    public function store(StoreDonationRequest $request)
    {
        $this->abortSiApagado();

        Donation::create([
            'status'      => ModerationStatus::PENDING,
            'package_id'  => $request->package_id,
            'user_id'     => auth()->user()->id,
            'transaction' => $request->transaction,
        ]);

        return redirect()->route('donations.index')
            ->with('success', 'Thank you for supporting us! Please allow for up to 48 hours for staff to confirm the transaction.');
    }
}
