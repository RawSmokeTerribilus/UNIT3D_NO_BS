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

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\QueueMonitor;
use Illuminate\Http\Request;

/**
 * Página de colas: una pestaña por cola, cada una con su consola (Livewire).
 */
class QueueController extends Controller
{
    public function index(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        $totals = QueueMonitor::totals();
        $queue = (string) $request->query('queue', 'default');

        if (!\array_key_exists($queue, $totals)) {
            $queue = 'default';
        }

        return view('Staff.queue.index', [
            'queue'  => $queue,
            'totals' => $totals,
        ]);
    }
}
