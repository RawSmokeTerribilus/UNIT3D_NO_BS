<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de "el staff le ha cortado la descarga a mano".
 *
 * `users.can_download = 0` significa cosas distintas segun quien lo puso: el
 * hit and run, la amnistia de Sanguijuela, un baneo... o una decision humana.
 * El announce deja volver a bajar los torrents con aviso a quien esta bloqueado
 * por H&R (store::hitrun_redownload), pero un bloqueo del staff tiene su motivo
 * y no se lo salta nada. Esta tabla es lo que los distingue.
 *
 * La escribe Staff\UserController::permissions al pasar can_download de 1 a 0
 * y la borra al devolverlo. Tabla aparte y no columna en users: aditiva, sin
 * tocar la tabla mas cargada.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_download_blocks', function (Blueprint $table): void {
            $table->unsignedInteger('user_id')->primary();
            $table->unsignedInteger('blocked_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('blocked_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
