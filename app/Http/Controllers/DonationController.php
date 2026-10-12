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
use App\Jobs\AvisarDonacionPendiente;
use App\Models\Donation;
use App\Models\DonationGateway;
use App\Models\DonationPackage;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

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
        $gateways = DonationGateway::with('packages:id')->where('is_active', '=', true)->orderBy('position')->get();

        // Qué métodos cubren cada tramo, según la matriz del panel de config.
        // Un método que no cubre ningún tramo encendido no se ofrece: sería
        // elegirlo y encontrarse todos los tramos en gris.
        $ofertas = $packages->mapWithKeys(fn (DonationPackage $package) => [
            $package->id => $gateways
                ->filter(fn (DonationGateway $gateway) => $gateway->packages->contains('id', $package->id))
                ->pluck('id')
                ->values()
                ->all(),
        ]);
        $gateways = $gateways
            ->filter(fn (DonationGateway $gateway) => $ofertas->contains(fn (array $ids) => \in_array($gateway->id, $ids, true)))
            ->values();

        // QR de las direcciones, en SVG en línea: la CSP no deja cargar imágenes
        // de fuera y un <svg> en el HTML no es una imagen. Es la misma librería
        // que pinta el QR del 2FA.
        $writer = new Writer(new ImageRenderer(new RendererStyle(180, 1), new SvgImageBackEnd()));
        $qrs = $gateways
            ->reject(fn (DonationGateway $gateway) => $gateway->esEnlace())
            ->mapWithKeys(fn (DonationGateway $gateway) => [
                $gateway->id => preg_replace('/^<\?xml[^>]*>\s*/', '', $writer->writeString($gateway->address)),
            ]);

        // Lo mismo que la barra del menú: lo aprobado este mes contra el
        // objetivo. Sirve para frenar a quien iba a donar «por si acaso».
        $recaudadoMes = (float) Donation::query()
            ->join('donation_packages', 'donations.package_id', '=', 'donation_packages.id')
            ->where('donations.created_at', '>=', now()->startOfMonth())
            ->where('donations.status', ModerationStatus::APPROVED)
            ->sum('donation_packages.cost');
        $objetivo = (float) config('donation.monthly_goal');

        return view('donation.index', [
            'packages'           => $packages,
            'gateways'           => $gateways,
            'qrs'                => $qrs,
            'ofertas'            => $ofertas,
            'porcentajeObjetivo' => $objetivo > 0 ? (int) min(100, floor($recaudadoMes / $objetivo * 100)) : 0,
        ]);
    }

    /**
     * Store A Donation.
     */
    public function store(StoreDonationRequest $request)
    {
        $this->abortSiApagado();

        $donation = Donation::create([
            'status'      => ModerationStatus::PENDING,
            'package_id'  => $request->package_id,
            'gateway_id'  => $request->gateway_id,
            'user_id'     => auth()->user()->id,
            'transaction' => $request->justificante,
        ]);

        if (config('donation.aviso_telegram')) {
            AvisarDonacionPendiente::dispatch($donation);
        }

        return redirect()->route('donations.index')
            ->with('success', '¡Gracias! Lo revisamos a mano: puede tardar hasta 48 horas en aplicarse.');
    }
}
