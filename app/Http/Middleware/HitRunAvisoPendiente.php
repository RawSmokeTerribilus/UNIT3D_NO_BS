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

use App\Models\StaffContact;
use App\Services\HitRunBans;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Pantalla de aviso al entrar, como la del 2FA: quien tiene un aviso de H&R con
 * fecha de eliminación (plantillas HitRunBans::PLANTILLAS_CON_FECHA), con el
 * plazo abierto y sin confirmar, va a la pantalla de aviso en cualquier página
 * hasta que pulse «lo he leído». Eso apunta staff_contacts.visto_at y le lleva
 * al privado.
 *
 * Solo desvía peticiones GET de navegador. Formularios, JSON y Livewire pasan,
 * igual que el logout, el buzón de mensajes (tiene que poder leer el privado) y
 * la propia pantalla. La consulta se cachea 5 minutos por socio.
 */
final class HitRunAvisoPendiente
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user === null
            || !$request->isMethod('GET')
            || $request->expectsJson()
            || $request->routeIs(['logout', 'hitrun_aviso.*', 'users.conversations.*'])) {
            return $next($request);
        }

        if (self::pendiente($user->id) === null) {
            return $next($request);
        }

        return to_route('hitrun_aviso.show');
    }

    /**
     * Id del contacto con aviso pendiente de ver, o null.
     */
    public static function pendiente(int $userId): ?int
    {
        // (int) fuera: Redis devuelve los números guardados como texto.
        $id = (int) Cache::remember(self::clave($userId), 300, fn (): int => (int) StaffContact::query()
            ->where('user_id', '=', $userId)
            ->whereIn('plantilla', HitRunBans::PLANTILLAS_CON_FECHA)
            ->where('plazo_hasta', '>', now())
            ->whereNull('visto_at')
            ->latest('contacted_at')
            ->value('id'));

        return $id > 0 ? $id : null;
    }

    public static function olvidar(int $userId): void
    {
        Cache::forget(self::clave($userId));
    }

    private static function clave(int $userId): string
    {
        return 'hitrun-aviso:'.$userId;
    }
}
