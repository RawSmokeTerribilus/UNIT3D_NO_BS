<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Http\Livewire\Staff;

use App\Models\DonationGateway;
use App\Models\DonationPackage;
use Livewire\Component;

/**
 * Métodos de pago × tramos de donación: qué se ofrece y dónde.
 *
 * Tres interruptores que se combinan: el método entero (`is_active` de la
 * pasarela), el tramo entero (`is_active` del paquete) y cada combinación
 * (una fila en `donation_gateway_package`). Una combinación sólo se ofrece si
 * los tres están encendidos, así que apagar ETH porque da guerra no obliga a
 * desmarcar sus cinco casillas, y al volver a encenderlo vuelven tal cual.
 *
 * Cada clic se guarda al momento: es un panel de emergencia («Amazon está
 * rechazando códigos, fuera») y no tiene sentido un botón de guardar aparte.
 *
 * Sólo Owners, como el resto de lo que toca dinero (alta de pasarelas y
 * aprobación de donaciones). La comprobación va en CADA acción y no sólo al
 * pintar: un método público de Livewire se puede llamar a mano.
 */
class DonationMethodMatrix extends Component
{
    private function soloOwner(): void
    {
        abort_unless(auth()->user()->group->is_owner, 403);
    }

    public function toggleMetodo(int $gatewayId): void
    {
        $this->soloOwner();

        $gateway = DonationGateway::findOrFail($gatewayId);
        $gateway->update(['is_active' => !$gateway->is_active]);
    }

    public function toggleTramo(int $packageId): void
    {
        $this->soloOwner();

        $package = DonationPackage::findOrFail($packageId);
        $package->update(['is_active' => !$package->is_active]);
    }

    public function toggleCombinacion(int $gatewayId, int $packageId): void
    {
        $this->soloOwner();

        $gateway = DonationGateway::findOrFail($gatewayId);
        $package = DonationPackage::findOrFail($packageId);

        $gateway->packages()->toggle([$package->id]);
    }

    final public function render(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        $this->soloOwner();

        $gateways = DonationGateway::with('packages:id')->orderBy('position')->get();
        $packages = DonationPackage::orderBy('position')->get();

        // Tramos encendidos que ahora mismo nadie puede pagar: salen en la
        // página de donar como «no disponible», y eso hay que verlo aquí.
        $huerfanos = $packages
            ->filter(fn (DonationPackage $package) => $package->is_active)
            ->reject(fn (DonationPackage $package) => $gateways->contains(
                fn (DonationGateway $gateway) => $gateway->is_active && $gateway->packages->contains('id', $package->id),
            ));

        return view('livewire.staff.donation-method-matrix', [
            'gateways'  => $gateways,
            'packages'  => $packages,
            'huerfanos' => $huerfanos,
        ]);
    }
}
