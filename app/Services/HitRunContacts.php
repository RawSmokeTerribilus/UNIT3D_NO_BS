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

        // Un aviso con fecha de eliminacion ya dado y vencido: ese plazo era el
        // unico. No se le escribe otra vez; lo decide el boton de baneo.
        $avisadosConFecha = StaffContact::query()
            ->whereIn('user_id', $ids->all())
            ->whereIn('plantilla', HitRunBans::PLANTILLAS_CON_FECHA)
            ->groupBy('user_id')
            ->select('user_id', DB::raw('MAX(contacted_at) AS t'))
            ->pluck('t', 'user_id');

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
                isset($avisadosConFecha[$u->id]) => \sprintf(
                    'avisado el %s con fecha de eliminación: toca decidir, no escribir',
                    Carbon::parse($avisadosConFecha[$u->id])->timezone('Europe/Madrid')->format('d/m/Y'),
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
    public static function send(array $ids, string $filtro, User $staff): array
    {
        $vigentes = self::recipients($filtro)['incluidos']->keyBy('id');

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

            self::sendTo($user, $staff, $filtro);

            $enviados++;
        }

        return ['enviados' => $enviados, 'saltados' => $saltados];
    }

    /**
     * Último aviso a candidatos del botón de baneo (histórico sin fecha
     * prometida): el mismo mensaje, encabezado con «Último aviso», y desde ahí
     * con su plazo de DIAS_BANEO días. Solo a quien SIGA siendo candidato.
     *
     * @param  list<int>                                    $ids
     * @return array{enviados: int, saltados: list<string>}
     */
    public static function sendUltimoAviso(array $ids, User $staff): array
    {
        $vigentes = HitRunBans::candidates()['candidatos']->keyBy('id');
        $r = ['enviados' => 0, 'saltados' => []];

        foreach (array_unique($ids) as $id) {
            $user = isset($vigentes[$id]) ? User::find($id) : null;

            if ($user === null) {
                $r['saltados'][] = '#'.$id;

                continue;
            }

            self::sendTo($user, $staff, self::FILTRO_LIMITE, true);
            $r['enviados']++;
        }

        return $r;
    }

    /**
     * Un mensaje a un socio por todas las vías, y su fila en staff_contacts.
     */
    private static function sendTo(User $user, User $staff, string $filtro, bool $ultimo = false): void
    {
        $detalle = self::detail($user->id);
        $texto = self::message($user, $staff, $filtro, $detalle, $ultimo);
        $plantilla = $texto['plantilla'];
        $campana = $plantilla.'-'.now()->format('Y-m-d');
        $conversationId = null;
        $canales = [];

        // Por tierra, mar y aire: privado siempre, correo si lo tiene y
        // Telegram si lo tiene vinculado. Un solo contacto con fecha vale
        // como aviso solo si le ha llegado por todas las vias posibles.
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

        if (!empty($user->email)) {
            Mail::to($user->email)->queue(new HitRunReminder($texto['asunto'], $texto['texto']));

            $canales[] = 'email';
        }

        if (!empty($user->telegram_chat_id) && app(TelegramService::class)->sendMessage(
            self::telegramText($user, $texto, $conversationId),
            (string) $user->telegram_chat_id,
        )) {
            $canales[] = 'telegram';
        }

        StaffContact::create([
            'user_id'          => $user->id,
            'contacted_at'     => now(),
            'campana'          => $campana,
            'canal'            => $canales === [] ? 'ninguno' : implode('+', $canales),
            'plantilla'        => $plantilla,
            'hr_activos'       => $detalle['activos']->count(),
            'hr_en_camino'     => $detalle['en_camino']->count(),
            'horas_pendientes' => $detalle['horas_pendientes'],
            'conversation_id'  => $conversationId,
            'plazo_hasta'      => now()->addDays(HitRunBans::DIAS_BANEO),
            'staff_id'         => $staff->id,
            'origen'           => 'boton',
            'nota'             => $ultimo ? 'último aviso, desde el botón de baneo' : null,
        ]);

        // La pantalla de aviso cachea 5 min «no tiene nada pendiente»: que el
        // aviso nuevo le salga en la siguiente pagina, no a los 5 minutos.
        \App\Http\Middleware\HitRunAvisoPendiente::olvidar($user->id);
    }

    /**
     * El mensaje de un socio, en BBCode (privado) y en texto plano (correo).
     *
     * La plantilla depende del ESTADO del socio, no del filtro de la lista:
     *   A hitrun-cortada  descarga cortada por H&R (activos >= maximo);
     *   B hitrun-limite   activos + los que le llegan en 48 h >= maximo;
     *   C hitrun-lista    el resto.
     * A y B avisan de que la cuenta se eliminara si no hace nada: la fecha es
     * la de hoy + HitRunBans::DIAS_BANEO, que es cuando pasa a ser candidato
     * del boton de baneo. Las listas llevan solo titulo, enlace y horas que
     * faltan: el detalle lo ve en la web.
     *
     * @param  array{activos: Collection<int, stdClass>, en_camino: Collection<int, stdClass>, horas_pendientes: float} $detalle
     * @return array{asunto: string, bbcode: string, texto: string, plantilla: string}
     */
    public static function message(User $user, User $staff, string $filtro, array $detalle, bool $ultimo = false): array
    {
        $max = (int) config('hitrun.max_warnings');
        $horasReq = intdiv((int) config('hitrun.seedtime'), 3600);
        $prewarn = (int) config('hitrun.prewarn');
        $n = $detalle['activos']->count();
        $m = $detalle['en_camino']->count();

        $plantilla = match (true) {
            $n >= $max && !$user->can_download => 'hitrun-cortada',
            $n + $m >= $max                    => 'hitrun-limite',
            default                            => 'hitrun-lista',
        };

        $activos = $n.' hit and run '.($n === 1 ? 'activo' : 'activos');
        $llegan = $m > 0 ? ' y [b]'.$m.' '.($m === 1 ? 'más que te llegará' : 'más que te llegarán').'[/b] en los próximos dos días' : '';

        $b = 'Hola, '.$user->username.'.'."\n\n";

        $intro = match ($plantilla) {
            'hitrun-cortada' => \sprintf('Tienes [b]%s[/b], y con %d se corta la descarga: ahora mismo no puedes descargar. De todo se sale.', $activos, $max),
            'hitrun-limite'  => \sprintf('Tienes [b]%s[/b]%s. Con %d se corta la descarga: todavía puedes evitarlo.', $activos, $llegan, $max),
            default          => \sprintf('Tienes [b]%s[/b]%s. Con %d se corta la descarga.', $activos, $llegan, $max),
        };

        if ($ultimo) {
            $intro = '[b]ÚLTIMO AVISO.[/b] '.$intro;
        }

        $b .= $intro.' '.($plantilla === 'hitrun-limite' ? 'Estos son los torrents:' : 'Estos son los torrents que te faltan:');
        $baja = null;

        if ($n > 0) {
            $b .= "\n".self::bbList($detalle['activos'], $horasReq)."\n";
        }

        if ($m > 0) {
            $b .= ($n > 0 ? 'Y estos te llegarán' : 'Estos te llegarán').' como hit and run en los próximos dos días si no vuelves a sembrarlos:'
                ."\n".self::bbList($detalle['en_camino'], $horasReq)."\n";
        }

        $b .= "\n".'[b]Cómo se arregla[/b]'."\n".'[list]'
            .'[*][b]Cada torrent[/b] tiene que sembrarse [b]'.$horasReq.' h ('.self::dias($horasReq).')[/b] por su cuenta. No se suman entre torrents: uno con '.($horasReq - 1).' h sigue siendo un hit and run.'."\n"
            .'[*]Las horas las cuenta [b]el tracker[/b], no tu cliente. En tu perfil → Torrents, marca «Show more precision»: si el Seedtime sale en verde, está cumplido.'."\n";

        if ($plantilla === 'hitrun-cortada' && config('hitrun.redownload')) {
            $b .= '[*]Aunque tengas la descarga cortada, [b]puedes volver a bajar los torrents de esta lista[/b], y solo esos. En cuanto bajes de '.$max.' hit and run, recuperas la descarga sola.'."\n";
        }

        $b .= $prewarn > 0
            ? '[*][b]Antes de borrar un torrent de tu cliente, páusalo '.$prewarn.' '.($prewarn === 1 ? 'día' : 'días').'.[/b] Si no te llega un preaviso, está cumplido y ya puedes borrarlo. Si te llega, es que aún le faltan horas.'."\n"
            : '[*][b]Antes de borrar un torrent de tu cliente,[/b] comprueba en tu perfil que su Seedtime sale en verde.'."\n";

        $wiki = trim(self::wikiLine());
        $b .= '[/list]'.($wiki === '' ? '' : "\n".$wiki)."\n\n";

        if ($plantilla !== 'hitrun-lista') {
            $baja = '[b]Una cuenta con la que no se hace nada por arreglarla no tiene sentido conservarla.[/b] Si el '
                .now()->addDays(HitRunBans::DIAS_BANEO)->timezone('Europe/Madrid')->format('d/m/Y')
                .' no has empezado a recuperarla, la cuenta se eliminará.';
            $b .= $baja."\n\n";
        }

        $b .= 'Si algo no te cuadra o has perdido algún fichero, contesta a este mensaje y lo vemos.'."\n\n"
            .'— '.$staff->username.', staff de NOBS';

        $asunto = match ($plantilla) {
            'hitrun-cortada' => 'Tu descarga está cortada por hit and run',
            'hitrun-limite'  => 'Tu descarga está a punto de cortarse por hit and run',
            default          => 'Tus hit and run: lo que te falta',
        };

        $texto = self::bbToText($b)."\n\n".'(Este mismo mensaje lo tienes en tu buzón del tracker, con los enlaces.)';

        return ['asunto' => $asunto, 'bbcode' => $b, 'texto' => $texto, 'plantilla' => $plantilla, 'intro' => $intro, 'baja' => $baja];
    }

    /**
     * Version corta para el privado del bot de Telegram: el arranque, la fecha
     * si la hay y el enlace al mensaje completo (la lista no cabe en un
     * privado de Telegram y alli no se ve bien). parse_mode HTML.
     *
     * @param array{asunto: string, intro: string, baja: string|null} $texto
     */
    private static function telegramText(User $user, array $texto, ?int $conversationId): string
    {
        $html = fn (string $bb): string => preg_replace('/\[b\](.*?)\[\/b\]/s', '<b>$1</b>', e($bb)) ?? e($bb);

        $enlace = $conversationId === null
            ? rtrim((string) config('app.url'), '/')
            : rtrim((string) config('app.url'), '/').'/users/'.rawurlencode($user->username).'/conversations/'.$conversationId;

        return '<b>'.e($texto['asunto']).'</b>'."\n\n"
            .$html($texto['intro'])."\n\n"
            .($texto['baja'] === null ? '' : $html($texto['baja'])."\n\n")
            .'La lista de torrents y cómo arreglarlo, en tu buzón del tracker: '.$enlace;
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
     * Lo que AutoWarning convertira en H&R en las proximas 48 h: su mismo
     * filtro, mas el reloj. AutoWarning corre a medianoche (UTC); se mira la
     * ultima medianoche dentro de las 48 h. En esa pasada hace falta llevar mas
     * de `grace` dias sin anunciar y tener el preaviso con al menos `prewarn`
     * dias (el puesto, o cuando le llegaria si aun no lo tiene). Asi no entra
     * quien acaba de apagar el cliente.
     */
    private static function onTheWayQuery(): \Illuminate\Database\Query\Builder
    {
        $pasada = now('UTC')->addHours(48)->startOfDay();
        $prewarn = (int) config('hitrun.prewarn');

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
            ->where('h.updated_at', '<', $pasada->copy()->subDays((int) config('hitrun.grace')))
            ->whereRaw(
                'COALESCE(h.prewarned_at, GREATEST(DATE_ADD(h.updated_at, INTERVAL ? DAY), UTC_TIMESTAMP())) <= ?',
                [$prewarn, $pasada->copy()->subDays($prewarn)->toDateTimeString()],
            )
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
            $s .= \sprintf(
                "[*][url=%s/torrents/%d]%s[/url] — faltan [b]%s h[/b]\n",
                rtrim((string) config('app.url'), '/'),
                $t->id,
                $t->name,
                self::num(max(0, $req - (int) $t->seedtime) / 3600),
            );
        }

        return $s.'[/list]';
    }

    private static function dias(int $horas): string
    {
        $d = $horas / 24;

        return (fmod($d, 1.0) === 0.0 ? (string) (int) $d : self::num($d)).' '.($d == 1 ? 'día' : 'días');
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
            return 'Los tres ajustes de qBittorrent que lo evitan: [url='.$base.$id.']esta wiki[/url].';
        }

        $id = DB::table('wikis')->where('name', '=', 'H&R')->value('id');

        return $id === null ? '' : 'Más detalle en [url='.$base.$id.']la wiki de H&R[/url].';
    }
}
