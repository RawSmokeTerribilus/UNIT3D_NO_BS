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
 * Seguimiento de los baneos por H&R abandonado: el baneo, la invitacion de un
 * solo uso al grupo de Telegram «Baneados de NOBS», si el socio entro y
 * cuando, y la poda posterior.
 *
 * `telegram_user_id` es copia del telegram_chat_id que tenia al banearlo: el
 * baneo nativo lo borra, y sin el no se sabria quien entro al grupo si no lo
 * hizo por su enlace.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hitrun_bans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('ban_id')->nullable();
            $table->unsignedInteger('staff_id')->nullable();
            $table->timestamp('banned_at');
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->string('invite_link')->nullable();
            $table->string('invite_name', 32)->nullable();
            $table->boolean('telegram_enviado')->default(false);
            $table->boolean('correo_enviado')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('prune_after');
            $table->timestamp('pruned_at')->nullable();
            $table->unsignedInteger('pruned_by')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('invite_link');
            $table->index(['pruned_at', 'prune_after']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('staff_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('pruned_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
