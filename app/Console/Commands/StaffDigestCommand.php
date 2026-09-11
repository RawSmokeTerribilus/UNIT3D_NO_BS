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

namespace App\Console\Commands;

use App\Services\PromoAnnouncer;
use App\Services\StaffDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Resumen operativo al grupo de staff.
 *
 * Dos modos:
 *
 *   auto:staff-digest            resumen diario, se manda SIEMPRE aunque este
 *                                todo a cero — un digest que no llega tiene que
 *                                significar «el cron se ha muerto», no «no habia
 *                                nada». Sin latido no hay forma de distinguirlo.
 *
 *   auto:staff-digest --watch    vigilancia entre digests. Solo habla cuando una
 *                                metrica CRUZA su umbral, y no repite hasta que
 *                                baje: si no, un numero alto durante tres dias
 *                                serian setenta mensajes y nadie volveria a leer
 *                                el grupo.
 */
class StaffDigestCommand extends Command
{
    protected $signature = 'auto:staff-digest {--watch : Solo avisar si una métrica acaba de cruzar su umbral}';

    protected $description = 'Manda el resumen operativo al grupo de staff (o vigila umbrales con --watch)';

    private const string CACHE_KEY = 'staff-digest:calientes';

    final public function handle(): int
    {
        return $this->option('watch') ? $this->watch() : $this->digest();
    }

    private function digest(): int
    {
        $texto = StaffDigest::render();

        // El diario redefine la linea base: lo que ya salio en el resumen no
        // vuelve a avisarse como novedad cinco minutos despues.
        Cache::put(self::CACHE_KEY, StaffDigest::hot(), now()->addDays(2));

        if (!PromoAnnouncer::staff($texto)) {
            $this->error('No he podido mandar el resumen al grupo de staff.');

            return self::FAILURE;
        }

        $this->info('Resumen enviado.');

        return self::SUCCESS;
    }

    private function watch(): int
    {
        $ahora    = StaffDigest::hot();
        $anterior = Cache::get(self::CACHE_KEY, []);
        $nuevas   = array_diff($ahora, $anterior);

        // Se guarda el estado completo aunque no haya nada nuevo: asi una
        // metrica que baja vuelve a poder avisar cuando suba otra vez.
        Cache::put(self::CACHE_KEY, $ahora, now()->addDays(2));

        if ($nuevas === []) {
            $this->info('Sin cruces de umbral nuevos.');

            return self::SUCCESS;
        }

        $metricas = StaffDigest::metrics();
        $texto    = "⚠️ Aviso fuera de hora — ".now()->format('d/m/Y H:i')." UTC\n\n";

        foreach ($nuevas as $clave) {
            $texto .= sprintf(
                "· %s: %d (umbral %d)\n",
                trim($metricas[$clave]['etiqueta']),
                $metricas[$clave]['valor'],
                StaffDigest::THRESHOLDS[$clave],
            );
        }

        $texto .= "\nNo se repite este aviso hasta que la métrica baje del umbral y vuelva a subir.";

        PromoAnnouncer::staff($texto);
        $this->warn(\count($nuevas).' métrica(s) han cruzado su umbral.');

        return self::SUCCESS;
    }
}
