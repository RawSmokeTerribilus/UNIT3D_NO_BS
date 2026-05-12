<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('show_poster')->default(true)->change();
        });

        DB::table('user_settings')->where('show_poster', false)->update(['show_poster' => true]);
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('show_poster')->default(false)->change();
        });
    }
};
