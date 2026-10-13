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

use App\Mail\HitRunReminder;
use App\Models\Conversation;
use App\Models\PrivateMessage;
use App\Models\StaffContact;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use stdClass;

/**
 * Mensajes personalizados a los socios con hit and run, con registro.
 *
 * Repite lo de la jornada del 22/23-09: a cada socio, su lista concreta de
 * torrents, enlaces y horas que faltan. Cada envio deja una fila en
 * staff_contacts con lo que tenia delante en ese momento.
 *
 * Cuentas, segun el estandar del 22-09:
 *   - aviso ACTIVO = warnings.active = 1 y sin borrar. `users.hitandruns` es
 *     un contador historico y aqui no se usa.
 *   - EN CAMINO = lo que AutoWarning convertira en aviso si no se siembra: el
 *     mismo filtro sin las condiciones de tiempo (preaviso y gracia).
 *   - history siempre con deleted_at IS NULL.
 *
 * Quien NO entra, y la lista lo dice con el motivo:
 *   - cuentas baneadas, aniquiladas, desactivadas o sin validar, y el staff;
 *   - corte de descarga hecho a mano por el staff (staff_download_blocks);
 *   - quien tiene un contacto con la ventana abierta (plazo_hasta): no se le
 *     re-contacta y no entra en un proceso nuevo mientras siga en otro;
 *   - quien ya acumula CONTACTOS_PARA_DECIDIR contactos de H&R (campanas que
 *     empiezan por «hitrun»; un reconocimiento o una comprobacion de correo no
 *     cuentan): a ese ya no se le escribe, se decide (boton de baneo).
 */
final class HitRunContacts
{
    final public const string FILTRO_TODOS = 'todos';

    final public const string FILTRO_LIMITE = 'limite';

    final public const int CONTACTOS_PARA_DECIDIR = 2;

    private const array GRUPOS_FUERA = ['banned', 'pruned', 'disabled', 'validating'];

    /**
     * @return array{incluidos: Collection<int, array<string, mixed>>, excluidos: Collection<int, array<string, mixed>>}
     */
    public static function recipients(string $filtro): array
    {
        $max = (int) config('hitrun.max_warnings');
        $activos = self::activeCounts();
        $camino = self::onTheWayCounts();

        $ids = $activos->keys()->merge($camino->keys())->unique();

        $ids = $ids->filter(function (int $id) use ($filtro, $max, $activos, $camino): bool {
            $a = $activos[$id] ?? 0;

            return $filtro === self::FILTRO_LIMITE
                ? $a >= $max || $a + ($camino[$id] ?? 0) >= $max
                : $a > 0;
        });

        $usuarios = DB::table('users as u')
            ->join('groups as g', 'g.id', '=', 'u.group_id')
            ->whereIn('u.id', $ids->all())
            ->whereNull('u.deleted_at')
            ->select([
                'u.id', 'u.username', 'u.email', 'u.can_download', 'u.last_action',
                'u.telegram_chat_id', 'g.name as grupo', 'g.slug',
                'g.is_modo', 'g.is_admin', 'g.is_owner',
            ])
            ->get()
            ->keyBy('id');

        $bloqueos = DB::table('staff_download_blocks')->whereIn('user_id', $ids->all())->pluck('user_id')->flip();

        $abiertos = StaffContact::query()
            ->abiertos()
            ->whereIn('user_id', $ids->all())
            ->orderByDesc('plazo_hasta')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $contactos = StaffContact::query()
            ->whereIn('user_id', $ids->all())
            ->where('canal', '!=', 'ninguno')
            ->where('campana', 'like', 'hitrun%')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) AS n'))
            ->pluck('n', 'user_id');

        $incluidos = collect();
        $excluidos = collect();

        foreach ($usuarios as $u) {
            $fila = [
                'id'           => $u->id,
                'username'     => $u->username,
                'grupo'        => $u->grupo,
                'activos'      => $activos[$u->id] ?? 0,
                'en_camino'    => $camino[$u->id] ?? 0,
                'can_download' => (bool) $u->can_download,
                'tiene_email'  => !empty($u->email),
                'telegram'     => !empty($u->telegram_chat_id),
                'last_action'  => $u->last_action,
                'contactos'    => (int) ($contactos[$u->id] ?? 0),
            ];

            $motivo = match (true) {
                \in_array($u->slug, self::GRUPOS_FUERA, true) => 'cuenta '.$u->grupo,
                $u->is_modo || $u->is_admin || $u->is_owner   => 'staff',
                isset($bloqueos[$u->id])                      => 'corte de descarga manual del staff',
                isset($abiertos[$u->id])                      => \sprintf(
                    'en proceso: %s, hasta el %s',
                    $abiertos[$u->id]->campana,
                    $abiertos[$u->id]->plazo_hasta->timezone('Europe/Madrid')->format('d/m/Y'),
                ),
                $fila['contactos'] >= self::CONTACTOS_PARA_DECIDIR => \sprintf('ya contactado %d veces: toca decidir, no escribir', $fila['contactos']),
                default                                            => null,
            };

            if ($motivo === null) {
                $incluidos->push($fila);
            } else {
                $excluidos->push($fila + ['motivo' => $motivo]);
            }
        }

        $orden = fn (Collection $c) => $c->sortBy([['activos', 'desc'], ['en_camino', 'desc'], ['username', 'asc']])->values();

        return ['incluidos' => $orden($incluidos), 'excluidos' => $orden($excluidos)];
    }

    /**
     * Torrents de un socio: los avisos activos y los que van camino de serlo.
     *
     * @return array{activos: Collection<int, stdClass>, en_camino: Collection<int, stdClass>, horas_pendientes: float}
     */
    public static function detail(int $userId): array
    {
        $req = (int) config('hitrun.seedtime');

        $activos = DB::table('warnings as w')
            ->join('torrents as t', 't.id', '=', 'w.torrent_id')
            ->leftJoin('history as h', fn ($j) => $j
                ->on('h.torrent_id', '=', 'w.torrent_id')
                ->on('h.user_id', '=', 'w.user_id')
                ->whereNull('h.deleted_at'))
            ->where('w.user_id', '=', $userId)
            ->where('w.active', '=', true)
            ->whereNull('w.deleted_at')
            ->whereNull('t.deleted_at')
            ->select(self::torrentColumns())
            ->get();

        $camino = self::onTheWayQuery()
            ->where('h.user_id', '=', $userId)
            ->select(self::torrentColumns())
            ->get();

        $horas = $activos->merge($camino)->sum(fn ($t) => max(0, $req - (int) $t->seedtime)) / 3600;

        $orden = fn (Collection $c) => $c->sortBy(fn ($t) => max(0, $req - (int) $t->seedtime))->values();

        return ['activos' => $orden($activos), 'en_camino' => $orden($camino), 'horas_pendientes' => round($horas, 1)];
    }

    /**
     * Envia el mensaje a los ids dados que SIGAN en la lista con ese filtro, y
     * deja una fila por socio en staff_contacts.
     *
     * @param  list<int>                                    $ids
     * @return array{enviados: int, saltados: list<string>}
     */
    public static function send(array $ids, string $filtro, string $canal, int $plazoDias, User $staff): array
    {
        $vigentes = self::recipients($filtro)['incluidos']->keyBy('id');
        $plantilla = $filtro === self::FILTRO_LIMITE ? 'hitrun-limite' : 'hitrun-lista';
        $campana = $plantilla.'-'.now()->format('Y-m-d');

        $enviados = 0;
        $saltados = [];

        foreach (array_unique($ids) as $id) {
            $fila = $vigentes[$id] ?? null;

            if ($fila === null) {
                $saltados[] = '#'.$id;

                continue;
            }

            $user = User::find($id);

            if ($user === null) {
                $saltados[] = '#'.$id;

                continue;
            }

            $detalle = self::detail($user->id);
            $texto = self::message($user, $staff, $filtro, $detalle);
            $conversationId = null;
            $canales = [];

            if ($canal === 'pm' || $canal === 'pm+email') {
                $conversationId = DB::transaction(function () use ($texto, $user, $staff): int {
                    $conversation = Conversation::create(['subject' => $texto['asunto']]);

                    PrivateMessage::create([
                        'conversation_id' => $conversation->id,
                        'sender_id'       => $staff->id,
                        'message'         => $texto['bbcode'],
                    ]);

                    $conversation->users()->sync([$staff->id => ['read' => true], $user->id]);

                    return $conversation->id;
                });

                $canales[] = 'pm';
            }

            if (($canal === 'email' || $canal === 'pm+email') && !empty($user->email)) {
                Mail::to($user->email)->queue(new HitRunReminder($texto['asunto'], $texto['texto']));

                $canales[] = 'email';
            }

            StaffContact::create([
                'user_id'          => $user->id,
                'contacted_at'     => now(),
                'campana'          => $campana,
                'canal'            => $canales === [] ? 'ninguno' : implode('+', $canales),
                'plantilla'        => $plantilla,
                'avisos_activos'   => $detalle['activos']->count(),
                'avisos_en_camino' => $detalle['en_camino']->count(),
                'horas_pendientes' => $detalle['horas_pendientes'],
                'conversation_id'  => $conversationId,
                'plazo_hasta'      => now()->addDays($plazoDias),
                'staff_id'         => $staff->id,
                'origen'           => 'boton',
            ]);

            $enviados++;
        }

        return ['enviados' => $enviados, 'saltados' => $saltados];
    }

    /**
     * El mensaje de un socio, en BBCode (privado) y en texto plano (correo).
     *
     * @param  array{activos: Collection<int, stdClass>, en_camino: Collection<int, stdClass>, horas_pendientes: float} $detalle
     * @return array{asunto: string, bbcode: string, texto: string}
     */
    public static function message(User $user, User $staff, string $filtro, array $detalle): array
    {
        $max = (int) config('hitrun.max_warnings');
        $horasReq = intdiv((int) config('hitrun.seedtime'), 3600);
        $nActivos = $detalle['activos']->count();
        $nCamino = $detalle['en_camino']->count();

        $b = 'Hola, '.$user->username.'.'."\n\n";

        // «Cortada» solo si el corte es por los avisos: con menos del maximo y
        // sin descarga, la causa es otra (ratio, staff) y no se le promete nada.
        if ($filtro === self::FILTRO_LIMITE && $nActivos >= $max && !$user->can_download) {
            $b .= \sprintf(
                'Te escribimos desde el staff porque tienes [b]%d avisos de hit and run[/b] y con %d se corta la descarga: ahora mismo la tienes cortada.',
                $nActivos,
                $max,
            );
            $b .= config('hitrun.redownload')
                ? ' Lo que sí puedes hacer es volver a bajar [b]los torrents con aviso[/b], y solo esos, para sembrarlos: en cuanto bajes de '.$max.' avisos, recuperas la descarga entera sin que nadie tenga que tocar nada.'
                : ' En cuanto bajes de '.$max.' avisos, recuperas la descarga sin que nadie tenga que tocar nada.';
        } elseif ($filtro === self::FILTRO_LIMITE && $nActivos >= $max) {
            $b .= \sprintf(
                'Te escribimos desde el staff porque tienes [b]%d avisos de hit and run[/b] y el límite es %d: con el próximo aviso la descarga se corta sola.',
                $nActivos,
                $max,
            );
        } elseif ($filtro === self::FILTRO_LIMITE) {
            $b .= \sprintf(
                'Te escribimos desde el staff porque vas camino del corte de descarga: tienes [b]%d aviso(s) de hit and run[/b] y [b]%d torrent(s) más en camino[/b] de serlo, y con %d se corta la descarga. Todavía estás a tiempo de que no pase.',
                $nActivos,
                $nCamino,
                $max,
            );
        } else {
            $b .= \sprintf(
                'Te escribimos desde el staff con tu lista concreta: tienes [b]%d aviso(s) de hit and run[/b]. Cada uno se apaga solo en cuanto ese torrent llega a %d h de siembra en el tracker.',
                $nActivos,
                $horasReq,
            );
        }

        $b .= "\n\n";

        if ($nActivos > 0) {
            $b .= '[b]Con aviso[/b]'."\n".self::bbList($detalle['activos'], $horasReq)."\n";
        }

        if ($nCamino > 0) {
            $b .= '[b]Todavía sin aviso, pero en camino[/b] (si no se siembran, el aviso llega solo)'."\n".self::bbList($detalle['en_camino'], $horasReq)."\n";
        }

        $b .= \sprintf('En total te faltan [b]%s h de siembra[/b]. ', self::num($detalle['horas_pendientes']))
            .'Son horas de siembra, no de calendario, y corren todas a la vez: si vuelves a cargar los torrents, '
            .'avanzan en paralelo. Lo que ya llevas sembrado no se pierde.'."\n\n"
            .'[b]Cómo comprobarlo:[/b] en tu perfil → Torrents, marca la casilla «Show more precision». '
            .'Cuando el Seedtime de un torrent sale en verde, ya está cumplido. Fíate del tracker, no del contador '
            .'de tu cliente, que siempre va un poco por delante: pon la parada de qBittorrent en 105-110 h o quítala.';

        $b .= self::wikiLine();

        $b .= "\n\n".'Las herramientas las ponemos nosotros; los pasos los das tú. Si algo no te cuadra o no '
            .'puedes recuperar algún fichero, contesta a este mensaje y lo vemos.'."\n\n"
            .'— '.$staff->username.', staff de NOBS';

        $asunto = $filtro === self::FILTRO_LIMITE
            ? 'Tu descarga y tus hit and run: lo que falta, torrent a torrent'
            : 'Tus hit and run: lo que falta, torrent a torrent';

        $texto = self::bbToText($b)."\n\n".'(Este mismo mensaje lo tienes en tu buzón del tracker, con los enlaces.)';

        return ['asunto' => $asunto, 'bbcode' => $b, 'texto' => $texto];
    }

    /**
     * @return Collection<int, int> user_id => avisos activos
     */
    private static function activeCounts(): Collection
    {
        return DB::table('warnings')
            ->where('active', '=', true)
            ->whereNull('deleted_at')
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) AS n'))
            ->pluck('n', 'user_id')
            ->map(fn ($n) => (int) $n);
    }

    public static function onTheWayCountFor(int $userId): int
    {
        return self::onTheWayQuery()->where('h.user_id', '=', $userId)->count();
    }

    /**
     * @return Collection<int, int> user_id => torrents en camino
     */
    private static function onTheWayCounts(): Collection
    {
        return self::onTheWayQuery()
            ->groupBy('h.user_id')
            ->select('h.user_id', DB::raw('COUNT(*) AS n'))
            ->pluck('n', 'h.user_id')
            ->map(fn ($n) => (int) $n);
    }

    /**
     * El filtro de AutoWarning sin las condiciones de tiempo: lo que acabara
     * en aviso si no se siembra.
     */
    private static function onTheWayQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('history as h')
            ->join('torrents as t', 't.id', '=', 'h.torrent_id')
            ->join('users as u', 'u.id', '=', 'h.user_id')
            ->join('groups as g', 'g.id', '=', 'u.group_id')
            ->whereNull('h.deleted_at')
            ->whereNull('t.deleted_at')
            ->where('h.actual_downloaded', '>', 0)
            ->where('h.hitrun', '=', false)
            ->where('h.immune', '=', false)
            ->where('h.active', '=', false)
            ->where('h.seedtime', '<', (int) config('hitrun.seedtime'))
            ->where('g.is_immune', '=', false)
            ->where('u.is_donor', '=', false)
            ->whereRaw('h.actual_downloaded > t.size * ?', [config('hitrun.buffer') / 100])
            ->whereNotExists(fn ($q) => $q->from('warnings as w')
                ->whereColumn('w.user_id', 'h.user_id')
                ->whereColumn('w.torrent_id', 'h.torrent_id'));
    }

    /**
     * @return list<mixed>
     */
    private static function torrentColumns(): array
    {
        return [
            't.id',
            't.name',
            't.size',
            DB::raw('COALESCE(h.seedtime, 0) AS seedtime'),
            'h.updated_at as ultimo_announce',
            DB::raw('COALESCE(h.active = 1 AND h.seeder = 1, 0) AS sembrando'),
        ];
    }

    /**
     * @param Collection<int, stdClass> $torrents
     */
    private static function bbList(Collection $torrents, int $horasReq): string
    {
        $req = $horasReq * 3600;
        $s = '[list]';

        foreach ($torrents as $t) {
            $hechas = (int) $t->seedtime / 3600;
            $faltan = max(0, $req - (int) $t->seedtime) / 3600;
            $estado = $t->sembrando
                ? 'lo estás sembrando ahora, no lo quites'
                : ($t->ultimo_announce !== null
                    ? 'parado desde el '.Carbon::parse($t->ultimo_announce)->timezone('Europe/Madrid')->format('d/m/Y')
                    : 'nunca lo has sembrado');

            $s .= \sprintf(
                "[*][url=%s/torrents/%d]%s[/url] — %s — %s h de %d, faltan [b]%s h[/b] — %s\n",
                rtrim((string) config('app.url'), '/'),
                $t->id,
                $t->name,
                \App\Helpers\StringHelper::formatBytes((int) $t->size, 1),
                self::num($hechas),
                $horasReq,
                self::num($faltan),
                $estado,
            );
        }

        return $s.'[/list]';
    }

    private static function bbToText(string $bb): string
    {
        $t = preg_replace('/\[url=([^\]]+)\](.*?)\[\/url\]/s', '$2 ($1)', $bb) ?? $bb;
        $t = str_replace(['[list]', '[/list]', '[*]'], ['', '', '  · '], $t);

        return preg_replace('/\[\/?b\]/', '', $t) ?? $t;
    }

    private static function num(float $n): string
    {
        return number_format($n, 1, ',', '.');
    }

    /**
     * Enlace a la wiki de los tres ajustes de qBittorrent; si no existe (staging
     * no la tiene), a la de H&R; si tampoco, nada.
     */
    private static function wikiLine(): string
    {
        $base = rtrim((string) config('app.url'), '/').'/wikis/';

        $id = DB::table('wikis')->where('name', 'like', '%ajustes de qBittorrent%')->value('id');

        if ($id !== null) {
            return ' Los tres ajustes que lo evitan: [url='.$base.$id.']esta wiki[/url].';
        }

        $id = DB::table('wikis')->where('name', '=', 'H&R')->value('id');

        return $id === null ? '' : ' Más detalle en [url='.$base.$id.']la wiki de H&R[/url].';
    }
}
