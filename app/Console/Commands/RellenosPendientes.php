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

namespace App\Console\Commands;

use App\Enums\ModerationStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\Torrent;
use App\Models\TorrentRequest;
use App\Models\User;
use App\Services\AvisoDirecto;
use App\Services\RequestFillApproval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Rellenos esperando aprobación, para que no se queden meses parados:
 *
 *   día 0  aviso al que pidió en cuanto se rellena (Telegram o privado);
 *   día 3  «achtung»: sigue sin aprobar y el que rellenó espera su recompensa;
 *   día 7  se aprueba solo, firmado por System, si pasa las comprobaciones
 *          (torrent vivo y aprobado, misma categoría, mismo TMDB, y quien
 *          rellena no es quien pidió); si no, se queda para el staff.
 *
 * Cada pasada del día 7 abre UN ticket de System al staff con lo aprobado
 * (deshacer: «Revoke approval» en la ficha) y lo que hay que mirar a mano.
 * Lo ya hecho se apunta en Redis (conexión de colas, que no vacía
 * cache:clear) por petición y fecha de relleno: un rechazo y un relleno nuevo
 * empiezan de cero.
 */
class RellenosPendientes extends Command
{
    protected $signature = 'requests:rellenos-pendientes {--dry : enseña lo que haría sin avisar, aprobar ni abrir ticket}';

    protected $description = 'Avisa al que pidió de los rellenos sin aprobar (día 0 y 3) y aprueba solos los de 7 días que pasan las comprobaciones';

    final public const int DIAS_ACHTUNG = 3;

    final public const int DIAS_AUTO = 7;

    final public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $redis = Redis::connection((string) config('queue.connections.redis.connection', 'job'));
        $system = User::findOrFail(User::SYSTEM_USER_ID);
        $aprobados = [];
        $aMano = [];

        $pendientes = TorrentRequest::query()
            ->whereNotNull('filled_by')
            ->whereNotNull('filled_when')
            ->whereNull('approved_when')
            ->with(['user', 'filler'])
            ->orderBy('filled_when')
            ->get();

        foreach ($pendientes as $tr) {
            $dias = intdiv((int) $tr->filled_when->diffInHours(now(), true), 24);
            $toca = $dias >= self::DIAS_AUTO ? self::DIAS_AUTO : ($dias >= self::DIAS_ACHTUNG ? self::DIAS_ACHTUNG : 1);
            $clave = "nobs:relleno-aviso:{$tr->id}:{$tr->filled_when->timestamp}";

            if ($toca <= (int) $redis->get($clave)) {
                continue;
            }

            if ($toca === self::DIAS_AUTO) {
                $motivo = self::motivoParaMirar($tr);

                if ($dry) {
                    $this->line("#{$tr->id} día {$dias}: ".($motivo === null ? 'se aprobaría solo' : "a mano ({$motivo})"));

                    continue;
                }

                if ($motivo === null) {
                    $filler = RequestFillApproval::approve($tr, $system);
                    $aprobados[] = [$tr, $filler];
                    $this->line("#{$tr->id} aprobado solo → {$filler->username}");
                } else {
                    $aMano[] = [$tr, $motivo];
                    $this->line("#{$tr->id} para el staff: {$motivo}");
                }
            } elseif ($tr->user_id !== $tr->filled_by && $tr->user !== null) {
                if ($dry) {
                    $this->line("#{$tr->id} día {$dias}: aviso ".($toca === 1 ? 'de relleno' : 'achtung')." a {$tr->user->username}");

                    continue;
                }

                $canal = self::avisar($tr, $toca);
                $this->line("#{$tr->id} aviso ".($toca === 1 ? 'de relleno' : 'achtung')." a {$tr->user->username} por {$canal}");
            } elseif ($dry) {
                continue;
            }

            $redis->setex($clave, 60 * 86400, (string) $toca);
        }

        if (!$dry && ($aprobados !== [] || $aMano !== [])) {
            self::ticket($aprobados, $aMano, $system);
            $this->line('Ticket abierto al staff.');
        }

        return self::SUCCESS;
    }

    /**
     * Por qué un relleno de 7 días NO se aprueba solo; null si pasa todo.
     */
    public static function motivoParaMirar(TorrentRequest $tr): ?string
    {
        $t = Torrent::withTrashed()->withoutGlobalScopes()->find($tr->torrent_id);

        if ($t === null) {
            return 'el torrent ya no existe';
        }

        if ($t->deleted_at !== null) {
            return 'el torrent está borrado';
        }

        if ($t->status !== ModerationStatus::APPROVED) {
            return 'el torrent no está aprobado';
        }

        if ($tr->filled_by === $tr->user_id) {
            return 'se la rellena quien la pidió';
        }

        if ($tr->category_id !== $t->category_id) {
            return 'categoría distinta a la pedida';
        }

        $movie = (int) $tr->tmdb_movie_id;
        $tv = (int) $tr->tmdb_tv_id;

        if ($movie === 0 && $tv === 0) {
            return 'la petición no tiene TMDB y no se puede comprobar';
        }

        if (($movie !== 0 && $movie !== (int) $t->tmdb_movie_id) || ($tv !== 0 && $tv !== (int) $t->tmdb_tv_id)) {
            return 'otra peli o serie en TMDB';
        }

        return null;
    }

    private static function avisar(TorrentRequest $tr, int $toca): string
    {
        $quien = $tr->filled_anon ? 'Alguien (anónimo)' : ($tr->filler->username ?? 'Alguien');
        $bounty = number_format((float) $tr->bounty, 0, ',', '.');
        $sola = $tr->filled_when->copy()->addDays(self::DIAS_AUTO)->timezone('Europe/Madrid')->format('d-m');

        if ($toca === 1) {
            $asunto = '📦 ¡Tu petición está rellenada!';
            $texto = "{$quien} ha rellenado [b]{$tr->name}[/b]. Se lo ha currado: comprueba que es lo que pediste y apruébala para que cobre su recompensa ({$bounty} BON).\n\n"
                ."Si no es lo que pediste, recházala desde la misma página. Si no haces nada, el {$sola} se aprueba sola si cuadra con lo pedido; si no, la revisa el staff.";
        } else {
            $asunto = "⏰ Achtung, {$tr->user->username}";
            $texto = "[b]{$tr->name}[/b] lleva {$toca} días rellenada y sin aprobar, y {$quien} sigue esperando su recompensa ({$bounty} BON).\n\n"
                ."¿Es lo que pediste? Apruébala. ¿No lo es? Recházala. Si no haces nada, el {$sola} se aprueba sola si cuadra con lo pedido; si no, la revisa el staff.";
        }

        return AvisoDirecto::enviar($tr->user, $asunto, $texto, 'Ver petición', href_request($tr));
    }

    /**
     * @param list<array{0: TorrentRequest, 1: User}>   $aprobados
     * @param list<array{0: TorrentRequest, 1: string}> $aMano
     */
    private static function ticket(array $aprobados, array $aMano, User $system): void
    {
        $linea = fn (TorrentRequest $tr): string => '[url='.href_request($tr).']'.$tr->name.'[/url] (pidió '
            .($tr->user->username ?? '?').', rellenó '.($tr->filler->username ?? '?').', '
            .number_format((float) $tr->bounty, 0, ',', '.').' BON)';

        $body = 'Rellenos con '.self::DIAS_AUTO.' días sin aprobar ni rechazar. Ticket automático de requests:rellenos-pendientes.';

        if ($aprobados !== []) {
            $body .= "\n\n[b]Aprobados solos[/b] (pasan las comprobaciones: torrent vivo y aprobado, misma categoría, mismo TMDB, no se la rellena quien la pidió). "
                ."Si alguno está mal: botón «Revoke approval» en su ficha, que le quita el bounty al que rellenó.\n";

            foreach ($aprobados as [$tr]) {
                $body .= "\n• ".$linea($tr);
            }
        }

        if ($aMano !== []) {
            $body .= "\n\n[b]Para mirar a mano[/b] (no se han aprobado). Aprobar o rechazar desde su ficha:\n";

            foreach ($aMano as [$tr, $motivo]) {
                $body .= "\n• ".$linea($tr).' — '.$motivo;
            }
        }

        Ticket::create([
            'user_id'     => $system->id,
            'category_id' => TicketCategory::where('name', '=', 'Requests')->value('id') ?? TicketCategory::orderBy('position')->value('id'),
            'priority_id' => TicketPriority::where('name', '=', 'Medium')->value('id') ?? TicketPriority::orderBy('position')->value('id'),
            'subject'     => 'Rellenos de peticiones: '.\count($aprobados).' aprobados solos, '.\count($aMano).' para mirar',
            'body'        => $body,
        ]);
    }
}
