<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Exception;

class EmailBlacklistUpdater
{
    public static function update(): bool|int
    {
        $url = config('email-blacklist.source');

        if ($url === null) {
            return false;
        }

        // Define parameters for the cache
        $key = config('email-blacklist.cache-key');
        $duration = Carbon::now()->addMonth();

        $localPath = storage_path('app/email-blacklist.json');

        try {
            $response = Http::timeout(10)->get($url);
            if ($response->successful()) {
                $domains = $response->json();
                // Guardar copia de seguridad local (Búnker)
                file_put_contents($localPath, json_encode($domains));
            } else {
                throw new Exception('Source unavailable');
            }
        } catch (Exception $e) {
            // Si falla la red, cargar la copia local
            if (file_exists($localPath)) {
                $domains = json_decode(file_get_contents($localPath), true);
            } else {
                $domains = [];
            }
        }

        $count = is_countable($domains) ? \count($domains) : 0;

        // Store blacklisted domains in Cache (for fast lookup)
        cache()->put($key, $domains, $duration);

        return $count;
    }
}
