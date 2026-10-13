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
use App\Http\Requests\Staff\StoreUnbanRequest;
use App\Models\User;
use App\Services\BanConInvitacion;

/**
 * @see \Tests\Todo\Feature\Http\Controllers\Staff\BanControllerTest
 */
class UnbanController extends Controller
{
    /**
     * Unban A User (banned -> new_group).
     */
    public function store(StoreUnbanRequest $request): \Illuminate\Http\RedirectResponse
    {
        $user = User::findOrFail($request->integer('owned_by'));
        $staff = $request->user();

        abort_if($user->group->is_modo || $request->user()->is($user), 403);

        // NOBS: el reverso de App\Services\BanConInvitacion::ban(). Además del
        // ritual nativo, le devuelve su vínculo de Telegram, le saca del grupo de
        // baneados, el bot le manda el botón para volver al grupo y el correo va
        // en castellano.
        $hecho = BanConInvitacion::unban($user, $staff, (int) $request->group_id, (string) $request->unban_reason);

        return to_route('users.show', ['user' => $user])
            ->with('success', 'Usuario desbaneado.'
                .($hecho['privado'] ? ' El bot le ha mandado el botón para volver al grupo.' : '')
                .($hecho['telegram_restaurado'] ? ' Telegram revinculado.' : ''));
    }
}
