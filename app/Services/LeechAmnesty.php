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

use App\Models\Group;
use App\Models\LeechAmnestyGrant;
use App\Models\User;
use App\Notifications\LeechAmnestyGranted;
use App\Notifications\LeechAmnestyRevoked;

/**
 * Amnistia de descarga para Sanguijuela mientras dura el freeleech global.
 *
 * Un Sanguijuela choca contra TRES candados, no uno, y hay que levantar los
 * tres o no descarga nada:
 *
 *   1. `users.can_download = 0`      -> TorrentDownloadController (el .torrent)
 *                                       y announce.rs:426 (el announce Rust).
 *   2. `groups.download_slots = 0`   -> announce.rs:453. Ahi el 0 NO es un tope,
 *                                       es un bloqueo: el peer se vuelve invisible.
 *   3. `users.ratio < other.ratio`   -> TorrentDownloadController, antes incluso
 *                                       de mirar can_download.
 *
 * Y un cuarto actor los vuelve a bajar: `AutoGroup` pone can_download a 0 cada
 * noche a todo el que cae en el grupo. Por eso desbloquear a mano no aguanta y
 * esto tiene que ser un comando periodico, no una accion puntual.
 *
 * Quien tiene la descarga revocada por H&R (>= hitrun.max_warnings avisos
 * activos) se queda fuera: la amnistia perdona el ratio, no el hit and run.
 */
final class LeechAmnesty
{
    /**
     * Slug del grupo. Nunca por id numerico: en esta instalacion los ids van
     * +2 respecto al enum de upstream y ya ha mordido antes.
     */
    public const string GROUP_SLUG = 'leech';

    public static function isEnabled(): bool
    {
        return (bool) config('other.freeleech_leech_amnesty');
    }

    /**
     * La amnistia solo existe DENTRO de un freeleech global. El interruptor
     * propio puede quedarse encendido entre promociones sin efecto ninguno.
     */
    public static function isActive(): bool
    {
        return (bool) config('other.freeleech') && self::isEnabled();
    }

    public static function slots(): int
    {
        return max(0, (int) config('other.freeleech_leech_slots'));
    }

    /**
     * El candado del ratio se levanta SOLO para el grupo amnistiado, no para
     * todo el sitio. El de can_download sigue intacto, y es el que mantiene
     * fuera a los bloqueados por H&R.
     */
    public static function bypassesRatioFor(User $user): bool
    {
        return self::isActive() && $user->group?->slug === self::GROUP_SLUG;
    }

    /**
     * Aplica o revierte la amnistia. Idempotente: una segunda pasada seguida
     * no cambia nada ni manda ningun mensaje.
     *
     * @return array{active: bool, slots: int, slots_changed: bool, granted: int, revoked: int, skipped_hitrun: int, announce_failures: int}
     */
    public static function sync(): array
    {
        $result = [
            'active'            => self::isActive(),
            'slots'             => 0,
            'slots_changed'     => false,
            'granted'           => 0,
            'revoked'           => 0,
            'skipped_hitrun'    => 0,
            'announce_failures' => 0,
        ];

        $group = Group::where('slug', '=', self::GROUP_SLUG)->first();

        if ($group === null) {
            return $result;
        }

        $active        = $result['active'];
        $desiredSlots  = $active ? self::slots() : 0;
        $maxWarnings   = (int) config('hitrun.max_warnings');
        $result['slots'] = $desiredSlots;

        // --- Candado 2: los slots del grupo -------------------------------
        if ((int) $group->download_slots !== $desiredSlots) {
            $group->update(['download_slots' => $desiredSlots]);

            if (!Unit3dAnnounce::addGroup($group)) {
                $result['announce_failures']++;
            }

            $result['slots_changed'] = true;
        }

        // --- Candado 1: el permiso de cada usuario del grupo ---------------
        $eligibleIds = [];

        User::query()
            ->where('group_id', '=', $group->id)
            ->withCount(['warnings' => fn ($query) => $query->where('active', '=', true)])
            ->chunkById(100, function ($users) use ($active, $maxWarnings, &$eligibleIds, &$result): void {
                foreach ($users as $user) {
                    $blockedByHitRun = $user->warnings_count >= $maxWarnings;
                    $shouldDownload  = $active && !$blockedByHitRun;

                    if ($active && $blockedByHitRun) {
                        $result['skipped_hitrun']++;
                    }

                    if ($shouldDownload) {
                        $eligibleIds[] = $user->id;
                    }

                    if ((bool) $user->can_download !== $shouldDownload) {
                        $user->update(['can_download' => $shouldDownload ? 1 : 0]);

                        self::forgetCaches($user);

                        if (!Unit3dAnnounce::addUser($user)) {
                            $result['announce_failures']++;
                        }
                    }

                    if ($shouldDownload) {
                        if (self::openGrant($user)) {
                            $result['granted']++;
                        }
                    } elseif (self::closeGrant($user, $blockedByHitRun ? 'hitrun' : 'freeleech_ended')) {
                        $result['revoked']++;
                    }
                }
            });

        // --- Censo: cerrar a quien ya no esta en el grupo -------------------
        //
        // Quien recupero ratio y ascendio nunca vuelve a pasar por el bucle de
        // arriba, asi que sin esto se quedaria con la fila abierta para siempre
        // y sin el mensaje de cierre. Ojo: a estos NO se les toca can_download.
        // Ya no son Sanguijuela y su permiso lo gobierna AutoGroup.
        $stale = LeechAmnestyGrant::query()
            ->whereNull('revoked_at')
            ->when($eligibleIds !== [], fn ($query) => $query->whereIntegerNotInRaw('user_id', $eligibleIds))
            ->with('user')
            ->get();

        foreach ($stale as $grant) {
            $grant->update([
                'revoked_at'     => now(),
                'revoked_reason' => 'left_group',
            ]);

            $grant->user?->notify(new LeechAmnestyRevoked('left_group'));

            $result['revoked']++;
        }

        return $result;
    }

    /**
     * Abre la fila del censo si no habia ninguna. Devuelve true solo cuando
     * la concesion es nueva, que es lo que dispara el aviso.
     */
    private static function openGrant(User $user): bool
    {
        $exists = LeechAmnestyGrant::query()
            ->where('user_id', '=', $user->id)
            ->whereNull('revoked_at')
            ->exists();

        if ($exists) {
            return false;
        }

        LeechAmnestyGrant::create([
            'user_id'    => $user->id,
            'granted_at' => now(),
        ]);

        $user->notify(new LeechAmnestyGranted());

        return true;
    }

    private static function closeGrant(User $user, string $reason): bool
    {
        $grant = LeechAmnestyGrant::query()
            ->where('user_id', '=', $user->id)
            ->whereNull('revoked_at')
            ->first();

        if ($grant === null) {
            return false;
        }

        $grant->update([
            'revoked_at'     => now(),
            'revoked_reason' => $reason,
        ]);

        $user->notify(new LeechAmnestyRevoked($reason));

        return true;
    }

    /**
     * Son DOS cachés y olvidar solo una ya costo cuatro intentos en el trabajo
     * de donaciones: `user:{passkey}` es la del announce, pero lo que pinta la
     * web es `cachedUser.{id}` (helper CacheUser, 30 s).
     */
    private static function forgetCaches(User $user): void
    {
        cache()->forget('user:'.$user->passkey);
        cache()->forget('cachedUser.'.$user->id);
    }
}
