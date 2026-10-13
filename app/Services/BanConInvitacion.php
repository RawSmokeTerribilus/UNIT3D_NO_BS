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

use App\Models\Ban;
use App\Models\Group;
use App\Models\HitRunBan;
use App\Models\StaffContact;
use App\Models\User;
use App\Notifications\HitRunBanned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * EL baneo de NOBS, venga de donde venga: el botón de banear del perfil
 * (Staff\BanController) y el botón de H&R abandonado (HitRunBans). Decidido
 * el 2026-10-13: si hay grupo de baneados, todo baneo invita a él.
 *
 *   1. Invitación de un solo uso al grupo «Baneados de NOBS» y privado del bot
 *      con botón, ANTES del baneo: el baneo borra telegram_chat_id y después
 *      ya no hay a quién escribir.
 *   2. Baneo con el ritual nativo (grupo Castigados, sin descarga, fila en
 *      bans) más hitrun_bans y staff_contacts, todo en una transacción.
 *   3. Announce, expulsión del grupo principal de Telegram y correo en
 *      castellano con la invitación (HitRunBanned, en vez del UserBan nativo).
 *
 * A los HitRunBans::DIAS_PODA días aparece en la lista de poda; quien entró al
 * grupo de baneados sale desmarcado.
 */
final class BanConInvitacion
{
    /**
     * @param  'hitrun'|'manual'                                                         $origen
     * @param  array{activos: int, en_camino: int}|null                                  $hr     H&R en el momento, si el baneo es por H&R
     * @return array{invitacion: string|null, telegram: bool, correo: bool, ban_id: int}
     */
    public static function ban(User $user, User $staff, string $motivo, string $origen, ?array $hr = null): array
    {
        $grupoBaneados = config('services.telegram.banned_group_id');
        $bannedGroupId = Group::where('slug', '=', 'banned')->soleValue('id');
        $podaEl = now()->addDays(HitRunBans::DIAS_PODA);
        $telegramId = $user->telegram_chat_id;
        $motivo = trim($motivo);

        // 1. Invitación y privado del bot, antes del baneo.
        $invitacion = null;
        $telegramEnviado = false;

        if (!empty($grupoBaneados)) {
            $invitacion = app(TelegramService::class)->createSingleUseInvite((string) $grupoBaneados, $user->username);

            if ($invitacion !== null && !empty($telegramId)) {
                // Con botón, como el «📡 UNIRSE AL GRUPO» de la vinculación: un
                // toque y entra (un bot no puede añadir a nadie él solo).
                $telegramEnviado = app(TelegramService::class)->sendMessageWithButton(
                    'Hola, '.e($user->username).'. Tu cuenta de '.e((string) config('other.title')).' ha sido baneada.'."\n\n"
                    .'<b>Motivo:</b> '.e(ucfirst($motivo))."\n\n"
                    .'Si quieres explicarte o arreglarlo, entra al grupo de baneados con el botón de abajo. Es personal y de un solo uso: no lo compartas.'."\n\n"
                    .'Si el '.$podaEl->copy()->timezone('Europe/Madrid')->format('d/m/Y').' no sabemos nada de ti, la cuenta se dará de baja.',
                    (string) $telegramId,
                    "\u{1F4E1} ENTRAR AL GRUPO DE BANEADOS",
                    $invitacion,
                );
            }
        }

        // 2. Baneo y registros, todo o nada.
        $correo = !empty($user->email);

        $ban = DB::transaction(function () use ($user, $staff, $motivo, $origen, $hr, $bannedGroupId, $invitacion, $telegramId, $telegramEnviado, $correo, $podaEl): Ban {
            $user->update([
                'group_id'     => $bannedGroupId,
                'can_download' => 0,
            ] + ($telegramId ? [
                'telegram_chat_id'         => null,
                'telegram_group_joined_at' => null,
                'telegram_token'           => null,
            ] : []));

            $ban = Ban::create([
                'owned_by'   => $user->id,
                'created_by' => $staff->id,
                'ban_reason' => ucfirst($motivo),
            ]);

            HitRunBan::create([
                'user_id'          => $user->id,
                'ban_id'           => $ban->id,
                'staff_id'         => $staff->id,
                'origen'           => $origen,
                'banned_at'        => now(),
                'telegram_user_id' => $telegramId ?: null,
                'invite_link'      => $invitacion,
                'invite_name'      => $invitacion === null ? null : mb_substr($user->username, 0, 32),
                'telegram_enviado' => $telegramEnviado,
                'correo_enviado'   => $correo,
                'prune_after'      => $podaEl,
            ]);

            StaffContact::create([
                'user_id'      => $user->id,
                'contacted_at' => now(),
                'campana'      => ($origen === 'hitrun' ? 'baneo-hitrun-' : 'baneo-manual-').now()->format('Y-m-d'),
                'canal'        => implode('+', array_filter([$correo ? 'email' : null, $telegramEnviado ? 'telegram' : null])) ?: 'ninguno',
                'plantilla'    => $origen === 'hitrun' ? 'baneo-hitrun' : 'baneo-manual',
                'hr_activos'   => $hr['activos'] ?? null,
                'hr_en_camino' => $hr['en_camino'] ?? null,
                'plazo_hasta'  => $podaEl,
                'staff_id'     => $staff->id,
                'origen'       => 'boton',
                'nota'         => ($invitacion === null ? 'sin invitación al grupo de baneados' : 'invitación: '.$invitacion).' · motivo: '.mb_substr($motivo, 0, 400),
            ]);

            return $ban;
        });

        // 3. Lo que sale fuera, ya con el baneo guardado.
        cache()->forget('user:'.$user->passkey);

        Unit3dAnnounce::addUser($user);

        if ($telegramId) {
            try {
                app(TelegramService::class)->kickUser((string) $telegramId);
            } catch (Throwable $e) {
                Log::warning('Telegram kick failed for user '.$user->username, ['error' => $e->getMessage()]);
            }
        }

        if ($correo) {
            $user->notify(new HitRunBanned($motivo, $invitacion, $podaEl, $origen === 'hitrun'));
        }

        return ['invitacion' => $invitacion, 'telegram' => $telegramEnviado, 'correo' => $correo, 'ban_id' => $ban->id];
    }
}
