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
use App\Models\User;
use App\Services\ArenaPase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
     * Página del módulo: iframe al origen propio de la Arena (config arena.origen) con un pase
     * firmado de 5 minutos; esta ruta ya ha pasado por todo el grupo auth.
     */
    public function show(Request $request, string $slug): View
    {
        if (!$this->abiertaPara($request->user())) {
            return view('arena.cerrada');
        }

        $modulo = config('arena.modulos.'.$slug);

        abort_unless(\is_array($modulo) && ($modulo['activo'] ?? false), 404);

        // Sin origen propio no hay Arena: los juegos nunca corren en el origen del tracker
        abort_unless(ArenaPase::disponible(), 503, 'La Arena no tiene configurado su origen (ARENA_ORIGEN / ARENA_PASE_SECRETO).');

        // El pase de la URL dura 5 minutos; la puerta lo cambia por su cookie
        $src = rtrim((string) config('arena.origen'), '/').'/arena-app/'.$slug.'/?pase='.ArenaPase::firmar($request->user(), ArenaPase::URL, 300);

        return view('arena.show', [
            'slug'    => $slug,
            'modulo'  => $modulo,
            'src'     => $src,
            'cerrada' => !config('arena.is_enabled'),
        ]);
    }

    /**
     * Puerta de nginx (auth_request) del origen propio de la Arena. Sin sesión (routes/arena.php):
     * cada fichero que pide el juego pasa por aquí. nginx manda la URI original (X-Arena-Uri, de
     * donde sale ?pase=) y la cookie (X-Arena-Pase-Cookie). Un pase de URL válido se cambia
     * por una cookie (X-Arena-Cookie, la pone nginx) que dura lo que el pase de la Arena.
     */
    public function pase(Request $request): Response
    {
        if (!ArenaPase::disponible()) {
            return response('', 401);
        }

        parse_str((string) parse_url((string) $request->header('X-Arena-Uri'), PHP_URL_QUERY), $query);
        $datos = ArenaPase::verificar(\is_string($query['pase'] ?? null) ? $query['pase'] : null, ArenaPase::URL);
        $deUrl = $datos !== null;
        $datos ??= ArenaPase::verificar($request->header('X-Arena-Pase-Cookie'), ArenaPase::COOKIE);

        if ($datos === null) {
            return response('', 401);
        }

        // Baneado o Arena cerrada: fuera aunque tenga pase. 30 s de caché para no ir a la BD por fichero.
        $user = cache()->remember('arena:pase:user:'.$datos['u'], 30, fn () => User::with('group')->find($datos['u']));
        $bannedGroupId = (int) cache()->rememberForever('group:banned:id', fn () => Group::where('slug', '=', 'banned')->soleValue('id'));

        if ($user === null || $user->group_id === $bannedGroupId || !$this->abiertaPara($user)) {
            return response('', 401);
        }

        $respuesta = response('', 204)->header('X-Arena-User', $user->id.':'.rawurlencode($user->username));

        if ($deUrl) {
            $segundos = (int) config('arena.pase_horas', 12) * 3600;
            $respuesta->header('X-Arena-Cookie', 'arena_pase='.ArenaPase::firmar($user, ArenaPase::COOKIE, $segundos)
                .'; Path=/; Max-Age='.$segundos.'; Secure; HttpOnly; SameSite=Lax');
        }

        return $respuesta;
    }
}
