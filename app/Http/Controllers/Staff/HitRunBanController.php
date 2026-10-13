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
use App\Models\HitRunBan;
use App\Services\HitRunBans;
use Illuminate\Http\Request;

/**
 * Staff → Comandos → Baneo por H&R abandonado. Dos listas, cada una con su
 * confirmacion: a quien banear, y a quien prunear porque no respondio.
 */
class HitRunBanController extends Controller
{
    public function index(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        $v = $request->validate(['dias' => ['nullable', 'integer', 'min:1', 'max:365']]);
        $dias = (int) ($v['dias'] ?? HitRunBans::DIAS_BANEO);
        $listas = HitRunBans::candidates($dias);

        return view('Staff.hitrun-ban.index', [
            'dias'       => $dias,
            'diasPoda'   => HitRunBans::DIAS_PODA,
            'grupo'      => !empty(config('services.telegram.banned_group_id')),
            'candidatos' => $listas['candidatos'],
            'excluidos'  => $listas['excluidos'],
            'podables'   => HitRunBans::pruneCandidates(),
            'baneos'     => HitRunBan::query()->with(['user:id,username', 'staff:id,username'])->latest('banned_at')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $v = $request->validate([
            'dias'  => ['required', 'integer', 'min:1', 'max:365'],
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $r = HitRunBans::ban(array_map('intval', $v['ids']), (int) $v['dias'], $request->user());

        return to_route('staff.commands.hitrun_bans.index', ['dias' => $v['dias']])
            ->with('info', trim(\sprintf(
                "✅ Baneados %d: %s.\n%s%s",
                \count($r['baneados']),
                implode(', ', $r['baneados']) ?: '—',
                $r['sin_invitacion'] === [] ? '' : '⚠ Sin invitación al grupo de baneados (respuesta por correo): '.implode(', ', $r['sin_invitacion']).".\n",
                $r['saltados'] === [] ? '' : 'Saltados porque ya no cumplían: '.implode(', ', $r['saltados']).'.',
            )));
    }

    public function prune(Request $request): \Illuminate\Http\RedirectResponse
    {
        $v = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $r = HitRunBans::prune(array_map('intval', $v['ids']), $request->user());

        return to_route('staff.commands.hitrun_bans.index')
            ->with('info', trim(\sprintf(
                "🗑 Aniquilados %d: %s.\n%s",
                \count($r['podados']),
                implode(', ', $r['podados']) ?: '—',
                $r['saltados'] === [] ? '' : 'Saltados (ya no estaban baneados o no les tocaba): '.implode(', ', $r['saltados']).'.',
            )));
    }
}
