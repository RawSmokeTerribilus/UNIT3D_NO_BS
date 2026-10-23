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
use App\Services\ArenaControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Staff → Arena: salas en vivo de cada módulo y acciones sobre ellas.
 *
 * Permisos (rutas): ver, reiniciar una sala y mandar un aviso = staff (modo);
 * crear y borrar salas y apagarlo todo = admin. El interruptor general está en
 * /dashboard/config (arena.is_enabled).
 */
class ArenaController extends Controller
{
    private function modulo(string $slug): ArenaControl
    {
        abort_unless(\is_array(config('arena.modulos.'.$slug)), 404);

        return new ArenaControl($slug);
    }

    private function actor(Request $request): string
    {
        return $request->user()->id.':'.$request->user()->username;
    }

    /**
     * Deja rastro en el log de Laravel (el agente guarda el suyo en acciones.jsonl).
     *
     * @param array{ok: bool, error: ?string} $resultado
     */
    private function volver(Request $request, string $slug, string $accion, array $resultado, string $hecho): RedirectResponse
    {
        Log::info('Arena: acción de staff', ['actor' => $this->actor($request), 'modulo' => $slug, 'accion' => $accion, 'ok' => $resultado['ok']]);

        return to_route('staff.arena.index')
            ->with($resultado['ok'] ? 'success' : 'warning', $resultado['ok'] ? $hecho : 'Arena: '.$resultado['error']);
    }

    public function index(Request $request): View
    {
        $modulos = [];

        foreach (config('arena.modulos', []) as $slug => $modulo) {
            $control = new ArenaControl($slug);
            $estado = $control->llamar('GET', '/estado');
            $esAdmin = (bool) $request->user()->group->is_admin;
            $presets = $esAdmin && $estado['ok'] ? $control->llamar('GET', '/presets') : null;
            $catalogo = $esAdmin && $estado['ok'] ? $control->llamar('GET', '/catalogo') : null;

            $modulos[$slug] = [
                'nombre'   => $modulo['nombre'],
                'estado'   => $estado,
                'presets'  => $presets['data']['presets'] ?? [],
                'catalogo' => $catalogo['ok'] ?? false ? $catalogo['data'] : null,
            ];
        }

        $logSala = null;

        if ($request->filled('log') && $request->filled('modulo')) {
            $sala = (string) $request->query('log');
            abort_unless((bool) preg_match('/^sala[0-9]{1,2}$/', $sala), 404);
            $logSala = [
                'modulo' => (string) $request->query('modulo'),
                'sala'   => $sala,
                'datos'  => $this->modulo((string) $request->query('modulo'))->llamar('GET', '/salas/'.$sala.'/log', ['lineas' => 300]),
            ];
        }

        return view('Staff.arena.index', [
            'modulos'  => $modulos,
            'abierta'  => (bool) config('arena.is_enabled'),
            'esAdmin'  => (bool) $request->user()->group->is_admin,
            'logSala'  => $logSala,
        ]);
    }

    public function reiniciar(Request $request, string $slug, string $sala): RedirectResponse
    {
        $r = $this->modulo($slug)->llamar('POST', '/salas/'.$sala.'/reiniciar', [], $this->actor($request));

        return $this->volver($request, $slug, 'reiniciar '.$sala, $r, 'Sala '.$sala.' reiniciada. Quien estuviera dentro ha salido.');
    }

    public function aviso(Request $request, string $slug, string $sala): RedirectResponse
    {
        $datos = $request->validate(['mensaje' => ['required', 'string', 'max:200']]);
        $r = $this->modulo($slug)->llamar('POST', '/salas/'.$sala.'/aviso', ['mensaje' => '[NOBS] '.$datos['mensaje']], $this->actor($request));

        return $this->volver($request, $slug, 'aviso '.$sala, $r, 'Aviso enviado a '.$sala.'.');
    }

    /**
     * Crear sala: desde un preset (con nombre propio opcional) o a medida con el plan
     * que monta el formulario (JSON); el agente valida el plan contra el catálogo del juego.
     */
    public function crear(Request $request, string $slug): RedirectResponse
    {
        $datos = $request->validate([
            'modo'         => ['required', 'in:preset,medida'],
            'preset'       => ['required_if:modo,preset', 'nullable', 'string', 'regex:/^[a-z0-9][a-z0-9-]{0,40}$/'],
            'nombre'       => ['nullable', 'string', 'max:60'],
            'plan'         => ['required_if:modo,medida', 'nullable', 'json', 'max:20000'],
            'guardar_como' => ['nullable', 'string', 'regex:/^[a-z0-9][a-z0-9-]{0,40}$/'],
        ]);

        if ($datos['modo'] === 'preset') {
            $cuerpo = ['preset' => $datos['preset'], 'nombre' => $datos['nombre'] ?? null];
            $que = 'el preset '.$datos['preset'];
        } else {
            $cuerpo = ['plan' => json_decode((string) $datos['plan'], true), 'guardar_como' => $datos['guardar_como'] ?? null];
            $que = 'una configuración a medida'.(empty($datos['guardar_como']) ? '' : ' (guardada como preset '.$datos['guardar_como'].')');
        }

        $r = $this->modulo($slug)->llamar('POST', '/salas', $cuerpo, $this->actor($request));

        return $this->volver($request, $slug, 'crear', $r, 'Sala creada con '.$que.'.');
    }

    public function renombrar(Request $request, string $slug, string $sala): RedirectResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:60']]);
        $r = $this->modulo($slug)->llamar('POST', '/salas/'.$sala.'/nombre', ['nombre' => $datos['nombre']], $this->actor($request));
        $cuando = ($r['data']['cuando'] ?? '') === 'aplicado' ? 'ya' : 'al acabar la partida en curso';

        return $this->volver($request, $slug, 'renombrar '.$sala, $r, 'Sala '.$sala.' renombrada; el nombre nuevo se ve '.$cuando.'.');
    }

    public function borrarPreset(Request $request, string $slug, string $preset): RedirectResponse
    {
        $r = $this->modulo($slug)->llamar('DELETE', '/presets/'.$preset, [], $this->actor($request));

        return $this->volver($request, $slug, 'borrar preset '.$preset, $r, 'Preset '.$preset.' borrado.');
    }

    public function borrar(Request $request, string $slug, string $sala): RedirectResponse
    {
        $r = $this->modulo($slug)->llamar('DELETE', '/salas/'.$sala, [], $this->actor($request));

        return $this->volver($request, $slug, 'borrar '.$sala, $r, 'Sala '.$sala.' cerrada.');
    }

    public function apagarTodo(Request $request, string $slug): RedirectResponse
    {
        $r = $this->modulo($slug)->llamar('POST', '/apagar-todo', [], $this->actor($request));

        return $this->volver($request, $slug, 'apagar-todo', $r, 'Todas las salas de '.$slug.' cerradas.');
    }
}
