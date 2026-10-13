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
use App\Models\StaffContact;
use App\Models\User;
use App\Services\HitRunBans;
use App\Services\HitRunContacts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff → Comandos → Mensajes de H&R. Primero la lista y un mensaje de
 * muestra; el envio sale solo al confirmar y cada uno queda en staff_contacts.
 */
class HitRunContactController extends Controller
{
    public function index(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        $opciones = $request->validate([
            'filtro' => ['nullable', Rule::in([HitRunContacts::FILTRO_TODOS, HitRunContacts::FILTRO_LIMITE])],
        ]);

        $filtro = $opciones['filtro'] ?? HitRunContacts::FILTRO_LIMITE;
        $listas = HitRunContacts::recipients($filtro);

        $muestra = null;
        $primero = $listas['incluidos']->first();

        if ($primero !== null) {
            $muestra = HitRunContacts::message(
                User::findOrFail($primero['id']),
                $request->user(),
                $filtro,
                HitRunContacts::detail($primero['id']),
            );
            $muestra['para'] = $primero['username'];
        }

        return view('Staff.hitrun-contact.index', [
            'filtro'    => $filtro,
            'plazo'     => HitRunBans::DIAS_BANEO,
            'max'       => (int) config('hitrun.max_warnings'),
            'incluidos' => $listas['incluidos'],
            'excluidos' => $listas['excluidos'],
            'muestra'   => $muestra,
            'registro'  => StaffContact::query()
                ->with(['user:id,username', 'staff:id,username'])
                ->latest('contacted_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $v = $request->validate([
            'filtro' => ['required', Rule::in([HitRunContacts::FILTRO_TODOS, HitRunContacts::FILTRO_LIMITE])],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
        ]);

        $r = HitRunContacts::send(
            array_map('intval', $v['ids']),
            $v['filtro'],
            $request->user(),
        );

        return to_route('staff.commands.hitrun_contacts.index', ['filtro' => $v['filtro']])
            ->with('info', \sprintf(
                '✅ %d mensaje(s) enviados y anotados en el registro.%s',
                $r['enviados'],
                $r['saltados'] === [] ? '' : ' Saltados porque ya no cumplían: '.implode(', ', $r['saltados']).'.',
            ));
    }
}
