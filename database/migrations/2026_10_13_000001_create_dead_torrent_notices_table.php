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
 * Reloj de los torrents muertos: cuando el staff pide la resiembra se abre una
 * fila «pendiente»; auto:dead-torrents la cierra como «salvado» si vuelve a
 * haber semilla, o «borrado» si pasa el plazo sin ella.
 *
 * `torrent_id` sin clave foranea a proposito: la fila tiene que sobrevivir al
 * torrent, porque es el registro de lo que se borro y por que. Por eso tambien
 * se guarda el nombre.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dead_torrent_notices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('torrent_id');
            $table->string('nombre');
            $table->unsignedInteger('avisado_por')->nullable();
            $table->timestamp('avisado_at');
            // Fecha prometida en el privado. Se fija al avisar: cambiar el plazo
            // en el panel no mueve la de quien ya fue avisado.
            $table->timestamp('borrar_at');
            // Ultimo announce de semilla conocido al avisar (null = nunca la tuvo).
            $table->timestamp('ultima_semilla_at')->nullable();
            $table->unsignedInteger('destinatarios')->default(0);
            // pendiente | salvado | borrado | desaparecido | cancelado
            $table->string('estado', 16)->default('pendiente');
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'borrar_at']);
            $table->index('torrent_id');
            $table->foreign('avisado_por')->references('id')->on('users')->nullOnDelete();
        });
    }
};
