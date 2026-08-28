<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra derivada de UNIT3D Community Edition (HDInnovations), de la que hereda
 * la licencia GNU AGPL v3.0.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Cada tier tiene su PROPIO boton de pago.
     *
     * Antes la vista componia el enlace pegandole `?amount=X&currency_code=EUR`
     * a la unica direccion de `donation_gateways`. Eso vale para una wallet o
     * un enlace generico, pero no para botones de PayPal con importe fijo: el
     * importe ya va dentro del boton, y el `hosted_button_id` es distinto en
     * cada uno.
     *
     * Va en `donation_packages` y no en `donation_gateways` porque la relacion
     * real es con el TIER, no con la pasarela: cinco filas «PayPal» en la tabla
     * de pasarelas obligarian a casarlas por importe, que no es una relacion.
     *
     * Nullable a proposito: si esta vacio, la vista cae al comportamiento de
     * siempre (pasarela generica + parametros), asi que ninguna instalacion se
     * rompe por no rellenarlo.
     */
    public function up(): void
    {
        Schema::table('donation_packages', function (Blueprint $table): void {
            $table->string('payment_url')->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('donation_packages', function (Blueprint $table): void {
            $table->dropColumn('payment_url');
        });
    }
};
