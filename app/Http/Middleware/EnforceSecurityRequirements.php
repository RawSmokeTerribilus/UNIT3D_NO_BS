<?php

declare(strict_types=1);

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
        $hasTelegram = !empty($user->telegram_chat_id);

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

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error'   => 'Security Restriction',
                'message' => 'Pendiente activar 2FA o vincular Telegram en la web del tracker.',
            ], 403);
        }

        if (!$hasTwoFactor) {
            return to_route('users.two_factor_auth.edit', ['user' => $user])
                ->withErrors('Seguridad critica: activa el 2FA para acceder al tracker.');
        }

        return to_route('users.notification_settings.edit', ['user' => $user])
            ->withErrors('Seguridad critica: vincula tu cuenta de Telegram para acceder al tracker.');
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
