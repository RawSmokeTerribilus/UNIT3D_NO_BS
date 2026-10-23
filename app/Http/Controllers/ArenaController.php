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

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ArenaController extends Controller
{
    /**
     * Abierta para este usuario: interruptor arena.is_enabled (settings), o owner/admin
     * (pueden entrar con la Arena cerrada para probar).
     */
    private function abiertaPara(?\App\Models\User $user): bool
    {
        return (bool) config('arena.is_enabled')
            || ($user !== null && ($user->group->is_owner || $user->group->is_admin));
    }

    /**
     * Catálogo de módulos activos.
     */
    public function index(Request $request): View
    {
        if (!$this->abiertaPara($request->user())) {
            return view('arena.cerrada');
        }

        return view('arena.index', [
            'modulos'  => array_filter(config('arena.modulos', []), fn (array $m): bool => $m['activo'] ?? false),
            'cerrada'  => !config('arena.is_enabled'),
        ]);
    }

    /**
     * Página del módulo: iframe a /arena-app/<slug>/. Deja en sesión el pase que
     * luego comprueba /__arena/whoami; esta ruta ya ha pasado por todo el grupo auth.
     */
    public function show(Request $request, string $slug): View
    {
        if (!$this->abiertaPara($request->user())) {
            return view('arena.cerrada');
        }

        $modulo = config('arena.modulos.'.$slug);

        abort_unless(\is_array($modulo) && ($modulo['activo'] ?? false), 404);

        $request->session()->put('arena_pase_hasta', now()->addHours((int) config('arena.pase_horas', 12))->getTimestamp());

        return view('arena.show', [
            'slug'    => $slug,
            'modulo'  => $modulo,
            'cerrada' => !config('arena.is_enabled'),
        ]);
    }

    /**
     * Puerta de nginx (auth_request) para /arena-app/: 204 + X-Arena-User si hay
     * sesión, pase vigente y no está baneado; 401 en cualquier otro caso. Nunca
     * redirige: auth_request convierte un 3xx en 500.
     */
    public function whoami(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null || (int) $request->session()->get('arena_pase_hasta', 0) < now()->getTimestamp()) {
            return response('', 401);
        }

        // Redis devuelve los enteros como texto
        $bannedGroupId = (int) cache()->rememberForever('group:banned:id', fn () => Group::where('slug', '=', 'banned')->soleValue('id'));

        // Baneado, o Arena cerrada (salvo owner/admin): fuera, aunque tenga pase
        if ($user->group_id === $bannedGroupId || !$this->abiertaPara($user)) {
            return response('', 401);
        }

        return response('', 204)->header('X-Arena-User', $user->id.':'.rawurlencode($user->username));
    }
}
