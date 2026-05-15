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

