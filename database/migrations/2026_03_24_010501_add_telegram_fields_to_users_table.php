<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Añadimos el ID de chat de Telegram (es un número largo)
            $table->bigInteger('telegram_chat_id')->nullable()->unique()->after('id');
            // Token temporal para vincular la cuenta (Ej: TRK-PULICION)
            $table->string('telegram_token', 64)->nullable()->unique()->after('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['telegram_chat_id', 'telegram_token']);
        });
    }
};
