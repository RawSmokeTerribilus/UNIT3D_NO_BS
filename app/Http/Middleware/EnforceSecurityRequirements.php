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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EnforceSecurityRequirements
{
    private const string DEPLOYMENT_DATE = '2026-05-07';
    private const string AMNESTY_CUTOFF = '2026-06-01';
    private const array EXEMPT_USERNAMES = [
        'CUNYAT',
        'garfield1969',
        'nahik99374',
        'testcunyat',
        'OcbSmoke',
        'Marpava',
        // Invitada el 2026-08-21. Ya tiene 2FA; el bloqueo era el requisito
        // de Telegram, y su cuenta de TG ya esta en el grupo de NOBS, no en
        // el de staging.
        'NoSoyAni',
        // Cuenta de pruebas del operador, con rol de admin para testear.
        // No tiene 2FA ni Telegram y el bloqueo le impedia llegar a las
        // paginas que precisamente hay que probar. No existe ningun 'test'
        // en produccion; si algun dia alguien se registra con ese nombre,
        // quedaria exento sin querer: revisar entonces.
        'test',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->group->is_owner || $this->isExemptUsername($user->username)) {
            return $next($request);
        }

        if ($request->routeIs([
            'logout',
            'users.notification_settings.*',
            'users.two_factor_auth.*',
            'users.telegram.*',
        ])) {
            return $next($request);
        }

        $hasTwoFactor = !empty($user->two_factor_secret);
        $hasTelegram = $user->telegram_group_joined_at !== null;

        if ($hasTwoFactor && $hasTelegram) {
            return $next($request);
        }

        $deploymentDate = Carbon::parse(self::DEPLOYMENT_DATE);
        $amnestyCutoff = Carbon::parse(self::AMNESTY_CUTOFF);
        $isNewUser = $user->created_at?->greaterThanOrEqualTo($deploymentDate) ?? true;
        $amnestyExpired = now()->greaterThanOrEqualTo($amnestyCutoff);

        if (!$isNewUser && !$amnestyExpired) {
            if (!$request->is('api/*') && $request->hasSession() && !$request->session()->has('security_requirements_amnesty_notice_shown')) {
                $request->session()->put('security_requirements_amnesty_notice_shown', true);
                $request->session()->flash(
                    'info',
                    "Periodo de gracia activo hasta {$amnestyCutoff->toDateString()}: activa 2FA y vincula Telegram antes de esa fecha para evitar el bloqueo."
                );
            }

            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')
            || $request->routeIs('rss.show.rsskey', 'torrent.download.rsskey')) {
            return response()->json([
                'error'   => 'Security Restriction',
                'message' => 'Pendiente activar 2FA o completar la verificación de Telegram en la web del tracker.',
            ], 403);
        }

        if (!$hasTwoFactor) {
            return to_route('users.two_factor_auth.edit', ['user' => $user])
                ->withErrors('Seguridad critica: activa el 2FA para acceder al tracker.');
        }

        return to_route('users.notification_settings.edit', ['user' => $user])
            ->withErrors('Seguridad critica: vincula tu Telegram y confirma tu entrada al grupo para acceder al tracker.');
    }

    private function isExemptUsername(string $username): bool
    {
        foreach (self::EXEMPT_USERNAMES as $exemptUsername) {
            if (strcasecmp($username, $exemptUsername) === 0) {
                return true;
            }
        }

        return false;
    }
}
