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

/**
 * MODIFICADO PARA NOBS
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
 * Se distribuye bajo la misma licencia, GNU AGPL v3.0.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreBanRequest;
use App\Models\Ban;
use App\Models\User;
use App\Services\BanConInvitacion;
use Exception;

/**
 * @see \Tests\Todo\Feature\Http\Controllers\Staff\BanControllerTest
 */
class BanController extends Controller
{
    /**
     * Display All Bans.
     */
    public function index(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('Staff.ban.index', [
            'bans' => Ban::latest()->with('user.group', 'staff.group')->paginate(25),
        ]);
    }

    /**
     * Ban A User (current_group -> banned).
     *
     * @throws Exception
     */
    public function store(StoreBanRequest $request): \Illuminate\Http\RedirectResponse
    {
        $user = User::findOrFail($request->string('owned_by'));
        $staff = $request->user();

        abort_if($user->group->is_modo || $staff->is($user), 403);

        // NOBS: el mismo baneo que el del botón de H&R (App\Services\BanConInvitacion):
        // invitación al grupo de baneados por privado del bot y en el correo,
        // expulsión del grupo principal y registro con plazo de poda. Sustituye
        // al ritual nativo, que hacía lo mismo sin invitación y con el correo
        // UserBan en inglés.
        $hecho = BanConInvitacion::ban($user, $staff, (string) $request->validated('ban_reason'), 'manual');

        return to_route('users.show', ['user' => $user])
            ->with('success', $hecho['invitacion'] === null
                ? 'Usuario baneado. Sin invitación al grupo de baneados (no configurado o falló).'
                : 'Usuario baneado e invitado al grupo de baneados'.($hecho['telegram'] ? ' (privado del bot enviado).' : ' (por correo; sin Telegram vinculado).'));
    }
}
