<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class SettingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            if (Schema::hasTable('settings')) {
                $settings = Setting::all();
                foreach ($settings as $setting) {
                    $value = $setting->value;
                    
                    // Cast numeric and boolean strings
                    if (is_numeric($value)) {
                        $value = strpos($value, '.') !== false ? (float) $value : (int) $value;
                    } elseif ($value === 'true' || $value === 'false') {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    } elseif ($value === '1' || $value === '0') {
                        $value = (bool) $value;
                    }

                    Config::set($setting->key, $value);
                }
            }
        } catch (Throwable $e) {
            Log::error('SettingServiceProvider: failed to load settings from DB, trying JSON snapshot.', [
                'error' => $e->getMessage(),
            ]);

            try {
                $jsonPath = storage_path('app/settings.json');

                if (file_exists($jsonPath)) {
                    $snapshot = json_decode(file_get_contents($jsonPath), true) ?? [];

                    foreach ($snapshot as $key => $value) {
                        if (is_numeric($value)) {
                            $value = str_contains((string) $value, '.') ? (float) $value : (int) $value;
                        } elseif ($value === 'true' || $value === 'false') {
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        }

                        Config::set($key, $value);
                    }
                }
            } catch (Throwable) {
                // JSON also unavailable — PHP config file values remain as-is
            }
        }
    }
}
