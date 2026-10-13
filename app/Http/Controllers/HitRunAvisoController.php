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

use App\Http\Middleware\HitRunAvisoPendiente;
use App\Models\StaffContact;
use Illuminate\Http\Request;

/**
 * La pantalla de aviso de H&R y su «lo he leído». Ver HitRunAvisoPendiente.
 */
class HitRunAvisoController extends Controller
{
    public function show(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $id = HitRunAvisoPendiente::pendiente($request->user()->id);

        if ($id === null) {
            return redirect()->intended('/');
        }

        return view('hitrun-aviso', ['aviso' => StaffContact::findOrFail($id)]);
    }

    public function ack(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $id = HitRunAvisoPendiente::pendiente($user->id);

        if ($id === null) {
            return redirect('/');
        }

        $aviso = StaffContact::findOrFail($id);
        $aviso->update(['visto_at' => now()]);

        HitRunAvisoPendiente::olvidar($user->id);

        return $aviso->conversation_id === null
            ? to_route('users.conversations.index', ['user' => $user])
            : to_route('users.conversations.show', ['user' => $user, 'conversation' => $aviso->conversation_id]);
    }
}
