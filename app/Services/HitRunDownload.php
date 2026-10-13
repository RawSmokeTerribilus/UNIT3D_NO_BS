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

namespace App\Services;

use App\Enums\UserGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Devolver la descarga a quien ya no tiene avisos suficientes para estar
 * bloqueado.
 *
 * AutoDeactivateWarning hace lo mismo cada hora, pero recorre la tabla warnings
 * y Warning usa SoftDeletes: a quien se le borran TODOS los avisos desaparece de
 * esa consulta y se queda sin descargas para siempre (usuario 52, 2026-08-16).
 * Borrar un torrent borra sus avisos, asi que el borrado de torrents muertos
 * tambien tiene que pasar por aqui.
 */
final class HitRunDownload
{
    /**
     * @return bool true si se le ha devuelto la descarga
     */
    public static function restoreIfEarned(User $user): bool
    {
        $user = $user->fresh();

        if ($user === null || $user->can_download) {
            return false;
        }

        // Estos grupos tienen la descarga cortada por otra razon (ratio bajo,
        // sancion o cuenta sin validar). Los avisos no son lo que les bloquea
        // y no nos toca desbloquearlos. Al Sanguijuela lo gobierna su propia
        // amnistia: ver App\Services\LeechAmnesty.
        $blockedByOtherRules = [
            UserGroup::LEECH->value,
            UserGroup::BANNED->value,
            UserGroup::DISABLED->value,
            UserGroup::PRUNED->value,
            UserGroup::VALIDATING->value,
        ];

        if (\in_array($user->group_id, $blockedByOtherRules, true)) {
            return false;
        }

        // Corte a mano del staff: tiene su motivo y no lo levanta un aviso.
        if (DB::table('staff_download_blocks')->where('user_id', '=', $user->id)->exists()) {
            return false;
        }

        // La relacion ya excluye los borrados por el scope de SoftDeletes.
        if ($user->warnings()->where('active', '=', true)->count() >= config('hitrun.max_warnings')) {
            return false;
        }

        $user->update(['can_download' => 1]);

        cache()->forget('user:'.$user->passkey);
        cache()->forget('cachedUser.'.$user->id);

        Unit3dAnnounce::addUser($user);

        return true;
    }
}
