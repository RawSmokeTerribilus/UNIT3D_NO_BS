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

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Pasa el comprobador de contadores (sólo lee) y deja la salida en el log,
 * visible en /dashboard/laravel-log. Va en su propio job tras una tarea por
 * lotes: dentro del último lote compartía su límite de tiempo y en prod ya
 * tardaba ~40 s.
 */
class TrackerCountersCheck implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Por debajo del retry_after de la cola (120 s): nunca se relanza duplicado. */
    public int $timeout = 110;

    public int $tries = 1;

    public function __construct(public string $after)
    {
    }

    public function handle(): void
    {
        Artisan::call('tracker:counters');
        Log::info("{$this->after} terminado. Comprobador de contadores:\n".trim(Artisan::output()));
    }
}
