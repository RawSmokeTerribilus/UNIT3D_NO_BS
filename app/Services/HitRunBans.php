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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Baneo de quien sigue sin hacer caso tras los mensajes de H&R, y poda
 * posterior de quien no respondio.
 *
 * Decidido con el operador el 2026-10-13: se BANEA, no se prunea (el baneo se
 * deshace y prunear se lleva tambien sus publicaciones), se le deja responder
 * por el grupo de Telegram «Baneados de NOBS», y la poda es un paso aparte a
 * los DIAS_PODA dias, solo con quien no respondio y siempre confirmando.
 *
 * Candidato:
 *   - HitRunContacts::CONTACTOS_PARA_DECIDIR o mas contactos de H&R en
 *     staff_contacts, el ultimo hace DIAS_BANEO dias o mas;
 *   - ningun plazo abierto en el registro (otro proceso, o retenido);
 *   - deuda pendiente: avisos activos o en camino;
 *   - ningun aviso cumplido sembrando desde el primer contacto;
 *   - su cliente no ha anunciado nada desde el ultimo contacto (history,
 *     con deleted_at IS NULL).
 * Quien solo ha entrado a la web sale en la lista pero DESMARCADO: es
 * actividad, aunque no siembre, y lo decide el staff.
 *
 * Fuera siempre: staff, cuentas ya baneadas/aniquiladas/desactivadas y los
 * bloqueados sin ninguna fila de aviso visible (el bucle de
 * AutoDeactivateWarning: no se tocan sin permiso del operador).
 */
final class HitRunBans
{
    final public const int DIAS_BANEO = 21;

    final public const int DIAS_PODA = 14;

    /**
     * @return array{candidatos: Collection<int, array<string, mixed>>, excluidos: Collection<int, array<string, mixed>>}
     */
    public static function candidates(int $dias = self::DIAS_BANEO): array
    {
        $contactos = StaffContact::query()
            ->where('campana', 'like', 'hitrun%')
            ->where('canal', '!=', 'ninguno')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= ?', [HitRunContacts::CONTACTOS_PARA_DECIDIR])
            ->select([
                'user_id',
                DB::raw('COUNT(*) AS n'),
                DB::raw('MIN(contacted_at) AS primero'),
                DB::raw('MAX(contacted_at) AS ultimo'),
            ])
            ->get()
            ->keyBy('user_id');

        $ids = $contactos->keys()->all();

        $usuarios = DB::table('users as u')
            ->join('groups as g', 'g.id', '=', 'u.group_id')
            ->whereIn('u.id', $ids)
            ->whereNull('u.deleted_at')
            ->select([
                'u.id', 'u.username', 'u.email', 'u.can_download', 'u.last_action',
                'u.telegram_chat_id', 'g.name as grupo', 'g.slug',
                'g.is_modo', 'g.is_admin', 'g.is_owner',
            ])
            ->get();

        $abiertos = StaffContact::query()->abiertos()->whereIn('user_id', $ids)
            ->orderByDesc('plazo_hasta')->get()->unique('user_id')->keyBy('user_id');

        $activos = DB::table('warnings')->whereIn('user_id', $ids)->where('active', '=', true)->whereNull('deleted_at')
            ->groupBy('user_id')->select('user_id', DB::raw('COUNT(*) AS n'))->pluck('n', 'user_id');

        $filasAviso = DB::table('warnings')->whereIn('user_id', $ids)->whereNull('deleted_at')
            ->groupBy('user_id')->select('user_id', DB::raw('COUNT(*) AS n'))->pluck('n', 'user_id');

        $ultimoAnnounce = DB::table('history')->whereIn('user_id', $ids)->whereNull('deleted_at')
            ->groupBy('user_id')->select('user_id', DB::raw('MAX(updated_at) AS t'))->pluck('t', 'user_id');

        $limite = now()->subDays($dias);
        $candidatos = collect();
        $excluidos = collect();

        foreach ($usuarios as $u) {
            $c = $contactos[$u->id];
            $primero = Carbon::parse($c->primero);
            $ultimo = Carbon::parse($c->ultimo);
            $camino = HitRunContacts::onTheWayCountFor($u->id);

            $cumplidos = DB::table('warnings')
                ->where('user_id', '=', $u->id)
                ->where('active', '=', false)
                ->whereNull('deleted_at')
                ->where('updated_at', '>', $primero)
                ->count();

            $announce = isset($ultimoAnnounce[$u->id]) ? Carbon::parse($ultimoAnnounce[$u->id]) : null;
            $web = $u->last_action === null ? null : Carbon::parse($u->last_action);

            $fila = [
                'id'              => $u->id,
                'username'        => $u->username,
                'grupo'           => $u->grupo,
                'contactos'       => (int) $c->n,
                'primer_contacto' => $primero,
                'ultimo_contacto' => $ultimo,
                'activos'         => (int) ($activos[$u->id] ?? 0),
                'en_camino'       => $camino,
                'cumplidos'       => $cumplidos,
                'ultimo_announce' => $announce,
                'ultima_web'      => $web,
                'telegram'        => !empty($u->telegram_chat_id),
                'tiene_email'     => !empty($u->email),
                // Solo ha entrado a la web: candidato, pero desmarcado.
                'marcado' => $web === null || $web->lte($ultimo),
            ];

            $motivo = match (true) {
                \in_array($u->slug, ['banned', 'pruned', 'disabled'], true) => 'cuenta '.$u->grupo,
                $u->is_modo || $u->is_admin || $u->is_owner                 => 'staff',
                !$u->can_download && !isset($filasAviso[$u->id])            => 'bloqueado sin filas de H&R visibles (bucle de AutoDeactivateWarning): no tocar sin permiso',
                isset($abiertos[$u->id])                                    => \sprintf(
                    '%s, hasta el %s',
                    $abiertos[$u->id]->campana,
                    $abiertos[$u->id]->plazo_hasta->timezone('Europe/Madrid')->format('d/m/Y'),
                ),
                $ultimo->gt($limite)                         => 'último contacto hace menos de '.$dias.' días',
                $fila['activos'] + $camino === 0             => 'ya no debe nada',
                $cumplidos > 0                               => $cumplidos.' H&R cumplido(s) sembrando desde el primer contacto',
                $announce !== null && $announce->gt($ultimo) => 'su cliente ha anunciado desde el último contacto',
                default                                      => null,
            };

            if ($motivo === null) {
                $candidatos->push($fila);
            } else {
                $excluidos->push($fila + ['motivo' => $motivo]);
            }
        }

        return [
            'candidatos' => $candidatos->sortBy([['marcado', 'desc'], ['activos', 'desc']])->values(),
            'excluidos'  => $excluidos->sortBy('username')->values(),
        ];
    }

    /**
     * Banea a los ids dados que SIGAN siendo candidatos.
     *
     * @param  list<int>                                                                           $ids
     * @return array{baneados: list<string>, saltados: list<string>, sin_invitacion: list<string>}
     */
    public static function ban(array $ids, int $dias, User $staff): array
    {
        $vigentes = self::candidates($dias)['candidatos']->keyBy('id');
        $grupoBaneados = config('services.telegram.banned_group_id');
        $bannedGroupId = Group::where('slug', '=', 'banned')->soleValue('id');
        $r = ['baneados' => [], 'saltados' => [], 'sin_invitacion' => []];

        foreach (array_unique($ids) as $id) {
            $fila = $vigentes[$id] ?? null;
            $user = $fila === null ? null : User::find($id);

            if ($user === null || $user->is($staff)) {
                $r['saltados'][] = '#'.$id;

                continue;
            }

            $motivo = \sprintf(
                'hit and run sin resolver: %d H&R activo(s) y %d en camino; contactado %d veces entre el %s y el %s sin respuesta ni actividad.',
                $fila['activos'],
                $fila['en_camino'],
                $fila['contactos'],
                $fila['primer_contacto']->format('d/m/Y'),
                $fila['ultimo_contacto']->format('d/m/Y'),
            );

            $podaEl = now()->addDays(self::DIAS_PODA);
            $telegramId = $user->telegram_chat_id;

            // 1. Invitacion y privado del bot ANTES del baneo: el baneo nativo
            //    borra telegram_chat_id y luego ya no hay a quien escribir.
            $invitacion = null;
            $telegramEnviado = false;

            if (!empty($grupoBaneados)) {
                $invitacion = app(TelegramService::class)->createSingleUseInvite((string) $grupoBaneados, $user->username);

                if ($invitacion !== null && !empty($telegramId)) {
                    $telegramEnviado = app(TelegramService::class)->sendMessage(
                        'Hola, '.e($user->username).'. Tu cuenta de '.e((string) config('other.title')).' ha sido baneada por '.e($motivo)."\n\n"
                        .'Si quieres explicarte o arreglarlo, entra aquí (enlace personal, de un solo uso): '.$invitacion."\n\n"
                        .'Si el '.$podaEl->copy()->timezone('Europe/Madrid')->format('d/m/Y').' no sabemos nada de ti, la cuenta se dará de baja.',
                        (string) $telegramId,
                    );
                }
            }

            if ($invitacion === null) {
                $r['sin_invitacion'][] = $user->username;
            }

            // 2. Baneo con el ritual de Staff\BanController::store y los dos
            //    registros, todo o nada: un fallo a medias dejaria a alguien
            //    baneado sin rastro de por que ni plazo de poda.
            $correo = !empty($user->email);

            DB::transaction(function () use ($user, $staff, $motivo, $bannedGroupId, $fila, $invitacion, $telegramId, $telegramEnviado, $correo, $podaEl): void {
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
                    'banned_at'        => now(),
                    'telegram_user_id' => $telegramId ?: null,
                    'invite_link'      => $invitacion,
                    'invite_name'      => $invitacion === null ? null : mb_substr($user->username, 0, 32),
                    'telegram_enviado' => $telegramEnviado,
                    'correo_enviado'   => $correo,
                    'prune_after'      => $podaEl,
                ]);

                StaffContact::create([
                    'user_id'          => $user->id,
                    'contacted_at'     => now(),
                    'campana'          => 'baneo-hitrun-'.now()->format('Y-m-d'),
                    'canal'            => implode('+', array_filter([$correo ? 'email' : null, $telegramEnviado ? 'telegram' : null])) ?: 'ninguno',
                    'plantilla'        => 'baneo-hitrun',
                    'hr_activos'       => $fila['activos'],
                    'hr_en_camino'     => $fila['en_camino'],
                    'plazo_hasta'      => $podaEl,
                    'staff_id'         => $staff->id,
                    'origen'           => 'boton',
                    'nota'             => $invitacion === null ? 'sin invitación al grupo de baneados' : 'invitación: '.$invitacion,
                ]);
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
                $user->notify(new HitRunBanned($motivo, $invitacion, $podaEl));
            }

            $r['baneados'][] = $user->username;
        }

        return $r;
    }

    /**
     * Baneados por H&R a los que ya les vencio el plazo para responder.
     *
     * @return Collection<int, HitRunBan>
     */
    public static function pruneCandidates(): Collection
    {
        return HitRunBan::query()
            ->with('user.group')
            ->whereNull('pruned_at')
            ->where('prune_after', '<=', now())
            ->orderBy('prune_after')
            ->get()
            // Solo quien sigue baneado: si el staff le levanto el baneo, no se poda.
            ->filter(fn (HitRunBan $b) => $b->user !== null && $b->user->deleted_at === null && $b->user->group?->slug === 'banned')
            ->values();
    }

    /**
     * @param  list<int>                                            $banIds ids de hitrun_bans
     * @return array{podados: list<string>, saltados: list<string>}
     */
    public static function prune(array $banIds, User $staff): array
    {
        $vigentes = self::pruneCandidates()->keyBy('id');
        $r = ['podados' => [], 'saltados' => []];

        foreach (array_unique($banIds) as $id) {
            $b = $vigentes[$id] ?? null;

            if ($b === null) {
                $r['saltados'][] = '#'.$id;

                continue;
            }

            UserPrune::prune($b->user, $staff->id);
            $b->update(['pruned_at' => now(), 'pruned_by' => $staff->id]);

            $r['podados'][] = $b->user->username;
        }

        return $r;
    }

    /**
     * Actualizacion chat_member del grupo de baneados (webhook de Telegram):
     * apunta cuando entra cada uno. Casa por el enlace con el que entro y, si
     * no lo trae, por su id de Telegram.
     *
     * @param array<string, mixed> $update
     */
    public static function recordJoin(array $update): void
    {
        $member = $update['new_chat_member'] ?? null;

        if (!\is_array($member) || !\in_array($member['status'] ?? null, ['member', 'restricted'], true)) {
            return;
        }

        $enlace = $update['invite_link']['invite_link'] ?? null;
        $telegramId = $member['user']['id'] ?? null;

        if ($enlace === null && $telegramId === null) {
            return;
        }

        $ban = HitRunBan::query()
            ->whereNull('joined_at')
            ->whereNull('pruned_at')
            ->where(fn ($q) => $q
                ->when($enlace !== null, fn ($q) => $q->where('invite_link', '=', $enlace))
                ->when($telegramId !== null, fn ($q) => $q->orWhere('telegram_user_id', '=', $telegramId)))
            ->latest('banned_at')
            ->first();

        if ($ban === null) {
            Log::info('Telegram: entrada al grupo de baneados sin baneo que casar', ['tg' => $telegramId, 'enlace' => $enlace !== null]);

            return;
        }

        $ban->update(['joined_at' => now(), 'telegram_user_id' => $ban->telegram_user_id ?? $telegramId]);

        Log::info('Telegram: un baneado por H&R ha entrado al grupo de baneados', ['user_id' => $ban->user_id]);
    }
}
