<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * El historial de decisiones de moderación de cada torrent.
     *
     * Pedido por un miembro del staff, y con razón: al rechazar o aplazar un
     * torrent el motivo es OBLIGATORIO, pero acababa únicamente dentro de un
     * mensaje privado entre el moderador que decidió y el uploader. Ningún
     * otro moderador podía verlo, así que no había forma de saber por qué se
     * rechazó algo ni de mantener un criterio común.
     *
     * En `torrents` sólo quedaban `moderated_at` y `moderated_by`: quién y
     * cuándo, nunca por qué. Y el registro de auditoría tampoco servía: guarda
     * el cambio de columna (`status` 0 → 2) porque el motivo no toca el modelo.
     *
     * Por qué una tabla y no una columna en `torrents`:
     *
     *   - Un torrent se modera VARIAS veces --se aplaza, el uploader lo
     *     corrige, se aprueba o se rechaza-- y una columna sólo guarda la
     *     última. La secuencia es justo lo que un moderador necesita leer.
     *   - `conversation_id` deja el hilo a un clic: la conversación con el
     *     uploader es donde sigue el ida y vuelta, y aquí no se duplica.
     */
    public function up(): void
    {
        Schema::create('torrent_moderations', function (Blueprint $table): void {
            $table->id();

            $table->unsignedInteger('torrent_id')->index();

            // Nullable a propósito: si algún día se borra la cuenta de un
            // moderador, la decisión sigue siendo un hecho y no debe irse con
            // él. Lo mismo con la conversación.
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->unsignedInteger('conversation_id')->nullable();

            $table->smallInteger('status');

            // Nullable porque una aprobación no lleva motivo: el formulario
            // sólo lo exige al rechazar o aplazar.
            $table->text('message')->nullable();

            $table->timestamps();

            $table->index(['torrent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torrent_moderations');
    }
};
