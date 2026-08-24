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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as BaseMiddleware;
use Illuminate\Http\Request;

class PreventRequestsDuringMaintenanceExceptStaff extends BaseMiddleware
{
    /**
     * The URIs that should be reachable during maintenance mode.
     *
     * @var array<int, string>
     */
    protected $except = [
        'login',
        'dashboard',
        'dashboard/*',
        'dashboard/commands/emergency-disable-maintenance',
    ];
}

