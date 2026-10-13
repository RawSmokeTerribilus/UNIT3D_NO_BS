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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de contactos del staff con socios: a quién se escribió, cuándo, por
 * qué canal y con qué datos delante.
 *
 * Hasta ahora vivía en markdown (hitrun-registro-de-contactos y
 * users-reviews/ del vault) y ninguna herramienta lo podía consultar. Lo
 * escriben el botón de mensajes de H&R (campañas nuevas) y
 * staff-contacts:import (lo anterior), y lo lee el botón de baneo.
 *
 * `plazo_hasta` es la ventana del contacto: mientras no venza, el socio no
 * recibe otro mensaje ni entra en listas nuevas (no re-contactar + una
 * persona, un proceso). Una retención sin mensaje es una fila con canal
 * «ninguno».
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->timestamp('contacted_at');
            $table->string('campana', 64);
            // pm | email | telegram | ninguno, o combinaciones con «+»
            $table->string('canal', 32);
            $table->string('plantilla', 64)->nullable();
            $table->unsignedSmallInteger('hr_activos')->nullable();
            $table->unsignedSmallInteger('hr_en_camino')->nullable();
            $table->decimal('horas_pendientes', 8, 1)->nullable();
            $table->unsignedInteger('conversation_id')->nullable();
            $table->timestamp('plazo_hasta')->nullable();
            $table->unsignedInteger('staff_id')->nullable();
            // boton | importado
            $table->string('origen', 16);
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'contacted_at']);
            $table->index(['campana', 'contacted_at']);
            $table->index('plazo_hasta');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('staff_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
