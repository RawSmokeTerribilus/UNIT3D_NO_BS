<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('locale')->default('es')->change();
        });

        // Update existing users who still have the old 'en' default
        DB::table('user_settings')->where('locale', 'en')->update(['locale' => 'es']);
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('locale')->default('en')->change();
        });
    }
};
