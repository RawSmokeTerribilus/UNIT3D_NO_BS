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
     * Censo de la amnistia de descarga durante el freeleech.
     *
     * Hace falta una tabla y no basta con mirar `users.can_download` porque el
     * aviso de cierre tiene que llegar tambien a quien SALIO de Sanguijuela
     * durante la amnistia: ese recupera ratio, `AutoGroup` lo asciende y nunca
     * vuelve a pasar por el cambio 1 -> 0. Sin censo se queda sin explicacion.
     *
     * Una fila abierta (`revoked_at IS NULL`) por usuario amnistiado. El
     * historico se conserva: las amnistias futuras abren filas nuevas, no
     * reescriben las viejas.
     */
    public function up(): void
    {
        Schema::create('leech_amnesty_grants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();

            // freeleech_ended | hitrun | left_group
            $table->string('revoked_reason', 32)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leech_amnesty_grants');
    }
};
