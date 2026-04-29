<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_saves', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('game_id', 64);
            $table->string('filename', 255);
            $table->string('path', 500);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'game_id', 'filename']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_saves');
    }
};
