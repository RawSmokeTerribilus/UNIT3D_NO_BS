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
        '/dashboard/commands*',
        '/dashboard/commands/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Always allow staff command panel in maintenance mode
        if ($this->isStaffCommandRequest($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    /**
     * Check if this is a staff command panel request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    private function isStaffCommandRequest(Request $request): bool
    {
        $path = $request->getPathInfo();
        return str_starts_with($path, '/dashboard/commands');
    }
}

