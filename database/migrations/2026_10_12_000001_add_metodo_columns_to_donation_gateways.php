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
     * Varios métodos de pago, cada uno con sus propias reglas.
     *
     * Hasta ahora había UNA pasarela (Ko-fi) colgada de `payment_url` en cada
     * tramo. Con cripto, cheques regalo y Ko-fi a la vez, cada método necesita
     * decir cómo se paga y qué justificante pide.
     *
     * Qué método se ofrece en qué tramo va en una tabla puente y no en un
     * «importe mínimo»: el Owner enciende y apaga COMBINACIONES desde el panel
     * de configuración (Ko-fi sólo en el vitalicio, Amazon fuera de los tramos
     * pequeños, ETH apagado entero si da guerra). Una fila = combinación
     * encendida; sin fila, ese método no aparece en ese tramo. Una pasarela
     * nueva nace sin combinaciones, o sea sin ofrecerse en ningún sitio.
     *
     * `donations.gateway_id` es nullable porque las donaciones anteriores no
     * guardaban por dónde entraron (todas fueron por Ko-fi).
     */
    public function up(): void
    {
        Schema::table('donation_gateways', function (Blueprint $table): void {
            $table->string('icono')->nullable()->after('name');
            $table->text('instrucciones')->nullable()->after('address');
            $table->string('etiqueta_justificante')->nullable()->after('instrucciones');
            $table->string('patron_justificante')->nullable()->after('etiqueta_justificante');
        });

        Schema::create('donation_gateway_package', function (Blueprint $table): void {
            $table->unsignedInteger('donation_gateway_id');
            $table->unsignedInteger('donation_package_id');
            $table->primary(['donation_gateway_id', 'donation_package_id']);
            $table->index('donation_package_id');
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->unsignedInteger('gateway_id')->nullable()->index()->after('package_id');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropIndex(['gateway_id']);
            $table->dropColumn('gateway_id');
        });

        Schema::dropIfExists('donation_gateway_package');

        Schema::table('donation_gateways', function (Blueprint $table): void {
            $table->dropColumn([
                'icono',
                'instrucciones',
                'etiqueta_justificante',
                'patron_justificante',
            ]);
        });
    }
};
