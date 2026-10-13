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

use App\Enums\ModerationStatus;
use App\Models\DeadTorrentNotice;
use App\Models\Scopes\ApprovedScope;
use App\Models\Torrent;
use App\Models\User;
use App\Notifications\DeadTorrentReseedRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;
use stdClass;

/**
 * Torrents muertos: localizarlos, pedir resiembra y borrarlos si nadie vuelve.
 *
 * «Muerto» no es `seeders = 0` a secas: `peers` solo dice quien esta conectado
 * AHORA. Hacen falta las tres cosas:
 *   - 0 semillas ahora (contador del torrent y ningun peer semilla activo);
 *   - ninguna fila de `history` con seeder=1 anunciada dentro del plazo
 *     (siempre con deleted_at IS NULL: el borrado logico toca updated_at);
 *   - el torrent tiene mas antiguedad que el plazo (fuera los recien subidos).
 *
 * El plazo es uno solo para las tres medidas y para la espera hasta el borrado:
 * `hitrun.dead_torrent_hours`, que con 0 vale lo mismo que `hitrun.seedtime`.
 *
 * Flujo: el staff ve la lista y confirma (notify) → privado al que lo subio y
 * a quien lo completo, y una fila «pendiente» con su fecha de borrado →
 * auto:dead-torrents (cada hora) la cierra como «salvado» si vuelve a haber
 * semilla o la borra por el ritual nativo (TorrentPurge) al vencer.
 */
final class DeadTorrents
{
    public static function hours(): int
    {
        $hours = (int) config('hitrun.dead_torrent_hours');

        return $hours > 0 ? $hours : max(1, intdiv((int) config('hitrun.seedtime'), 3600));
    }

    /**
     * Torrents muertos sin peticion pendiente, el que mas tiempo lleva sin
     * semilla primero (los que nunca la tuvieron, arriba del todo).
     *
     * @param  list<int>|null            $ids solo estos (para revalidar al confirmar)
     * @return Collection<int, stdClass>
     */
    public static function candidates(?array $ids = null): Collection
    {
        $limite = now()->subHours(self::hours());

        return DB::table('torrents as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->whereNull('t.deleted_at')
            ->where('t.status', '=', ModerationStatus::APPROVED)
            ->where('t.seeders', '=', 0)
            ->where('t.created_at', '<', $limite)
            ->whereNotExists(fn (Builder $q) => self::seederSince($q, $limite))
            ->whereNotExists(fn (Builder $q) => $q->from('peers as p')
                ->whereColumn('p.torrent_id', 't.id')
                ->where('p.seeder', '=', true)
                ->where('p.active', '=', true))
            ->whereNotExists(fn (Builder $q) => $q->from('dead_torrent_notices as n')
                ->whereColumn('n.torrent_id', 't.id')
                ->where('n.estado', '=', DeadTorrentNotice::PENDIENTE))
            ->when($ids !== null, fn ($q) => $q->whereIn('t.id', $ids))
            ->select([
                't.id',
                't.name',
                't.size',
                't.user_id',
                'u.username as subidor',
                't.times_completed',
                't.leechers',
                't.created_at',
                DB::raw('(SELECT MAX(h.updated_at) FROM history h WHERE h.torrent_id = t.id AND h.deleted_at IS NULL AND h.seeder = 1) AS ultima_semilla'),
                DB::raw('(SELECT COUNT(DISTINCT h.user_id) FROM history h WHERE h.torrent_id = t.id AND h.deleted_at IS NULL AND h.completed_at IS NOT NULL AND h.user_id <> t.user_id) AS completaron'),
            ])
            ->orderByRaw('ultima_semilla IS NOT NULL, ultima_semilla, t.id')
            ->get();
    }

    /**
     * Pide la resiembra de los torrents dados que SIGAN muertos ahora mismo
     * (la lista que vio el staff puede tener minutos) y abre su reloj.
     *
     * @param  list<int>                                                $ids
     * @return list<array{id: int, nombre: string, destinatarios: int}>
     */
    public static function notify(array $ids, User $staff): array
    {
        $horas = self::hours();
        $hechos = [];

        foreach (self::candidates($ids) as $fila) {
            $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->find($fila->id);

            if ($torrent === null) {
                continue;
            }

            $destinatarios = User::query()
                ->where(fn ($q) => $q
                    ->where('id', '=', $torrent->user_id)
                    ->orWhereIn('id', DB::table('history')
                        ->select('user_id')
                        ->where('torrent_id', '=', $torrent->id)
                        ->whereNull('deleted_at')
                        ->whereNotNull('completed_at')))
                ->where('id', '!=', User::SYSTEM_USER_ID)
                ->get();

            $ahora = now();
            $borrarAt = $ahora->copy()->addHours($horas);

            DeadTorrentNotice::create([
                'torrent_id'        => $torrent->id,
                'nombre'            => $torrent->name,
                'avisado_por'       => $staff->id,
                'avisado_at'        => $ahora,
                'borrar_at'         => $borrarAt,
                'ultima_semilla_at' => $fila->ultima_semilla,
                'destinatarios'     => $destinatarios->count(),
                'estado'            => DeadTorrentNotice::PENDIENTE,
            ]);

            foreach ($destinatarios as $user) {
                $user->notify(new DeadTorrentReseedRequest($torrent, $borrarAt, $user->id === $torrent->user_id));
            }

            $hechos[] = ['id' => $torrent->id, 'nombre' => $torrent->name, 'destinatarios' => $destinatarios->count()];
        }

        return $hechos;
    }

    /**
     * Pasada del reloj: cierra lo que volvio a tener semilla y borra lo vencido.
     *
     * @return array{
     *     salvados: list<array{id: int, nombre: string}>,
     *     borrados: list<array{id: int, nombre: string, rehabilitados: list<int>}>,
     *     desaparecidos: list<array{id: int, nombre: string}>,
     *     errores: list<array{id: int, nombre: string, error: string}>,
     * }
     */
    public static function sweep(): array
    {
        $resultado = ['salvados' => [], 'borrados' => [], 'desaparecidos' => [], 'errores' => []];

        $pendientes = DeadTorrentNotice::query()
            ->where('estado', '=', DeadTorrentNotice::PENDIENTE)
            ->orderBy('id')
            ->get();

        foreach ($pendientes as $aviso) {
            $fila = ['id' => $aviso->torrent_id, 'nombre' => $aviso->nombre];
            $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->find($aviso->torrent_id);

            // Lo borro otro (staff, el subidor) mientras corria el plazo.
            if ($torrent === null) {
                $aviso->update(['estado' => DeadTorrentNotice::DESAPARECIDO, 'resuelto_at' => now()]);
                $resultado['desaparecidos'][] = $fila;

                continue;
            }

            if (self::revived($torrent, $aviso->avisado_at)) {
                $aviso->update(['estado' => DeadTorrentNotice::SALVADO, 'resuelto_at' => now()]);
                $resultado['salvados'][] = $fila;

                continue;
            }

            if ($aviso->borrar_at->isFuture()) {
                continue;
            }

            $motivo = \sprintf(
                'Torrent muerto: sin semillas durante %d horas después de pedir su resiembra el %s.',
                (int) round($aviso->avisado_at->diffInHours($aviso->borrar_at)),
                $aviso->avisado_at->copy()->timezone('Europe/Madrid')->format('d/m/Y'),
            );

            try {
                $hecho = TorrentPurge::purge($torrent, $motivo);
            } catch (Throwable $e) {
                // Se queda pendiente: la siguiente pasada lo reintenta, y el
                // resumen al staff lo cuenta mientras tanto.
                $resultado['errores'][] = $fila + ['error' => $e->getMessage()];

                continue;
            }

            $aviso->update(['estado' => DeadTorrentNotice::BORRADO, 'resuelto_at' => now()]);
            $resultado['borrados'][] = $fila + ['rehabilitados' => $hecho['rehabilitados']];
        }

        return $resultado;
    }

    /**
     * ¿Ha vuelto a tener semilla desde la peticion?
     */
    public static function revived(Torrent $torrent, Carbon $desde): bool
    {
        if ($torrent->seeders > 0) {
            return true;
        }

        if (DB::table('peers')->where('torrent_id', '=', $torrent->id)->where('seeder', '=', true)->where('active', '=', true)->exists()) {
            return true;
        }

        return DB::table('history as h')
            ->where('h.torrent_id', '=', $torrent->id)
            ->whereNull('h.deleted_at')
            ->where('h.seeder', '=', true)
            ->where('h.updated_at', '>=', $desde)
            ->exists();
    }

    /**
     * Texto del resumen al staff, o null si la pasada no cambio nada. Va a
     * Telegram con parse_mode HTML: los nombres se escapan.
     *
     * @param array{salvados: list<array>, borrados: list<array>, desaparecidos: list<array>, errores: list<array>} $r
     */
    public static function summary(array $r): ?string
    {
        if ($r['salvados'] === [] && $r['borrados'] === [] && $r['desaparecidos'] === [] && $r['errores'] === []) {
            return null;
        }

        $texto = '🪦 Torrents muertos — '.now()->timezone('Europe/Madrid')->format('d/m/Y H:i')."\n";

        $bloques = [
            'borrados'      => '🗑 Borrados sin resiembra',
            'salvados'      => '🌱 Salvados (volvieron a tener semilla)',
            'desaparecidos' => '👻 Ya los había borrado otro',
            'errores'       => '❌ Error al borrar (se reintenta en la próxima pasada)',
        ];

        foreach ($bloques as $clave => $titulo) {
            if ($r[$clave] === []) {
                continue;
            }

            $texto .= "\n".$titulo.': '.\count($r[$clave])."\n";

            // Telegram corta en 4096 caracteres: a partir de 25 por bloque, solo
            // el numero. El detalle completo esta en el panel.
            foreach (\array_slice($r[$clave], 0, 25) as $fila) {
                $texto .= \sprintf('· #%d %s', $fila['id'], e($fila['nombre']));

                if (!empty($fila['rehabilitados'])) {
                    $texto .= ' — descarga devuelta a '.\count($fila['rehabilitados']).' socio(s)';
                }

                if (isset($fila['error'])) {
                    $texto .= ' — '.e(mb_substr($fila['error'], 0, 120));
                }

                $texto .= "\n";
            }

            if (\count($r[$clave]) > 25) {
                $texto .= '· … y '.(\count($r[$clave]) - 25)." más\n";
            }
        }

        $pendientes = DeadTorrentNotice::query()->where('estado', '=', DeadTorrentNotice::PENDIENTE)->count();

        return $texto."\nSiguen pendientes: ".$pendientes;
    }

    private static function seederSince(Builder $q, Carbon $desde): Builder
    {
        return $q->from('history as h')
            ->whereColumn('h.torrent_id', 't.id')
            ->whereNull('h.deleted_at')
            ->where('h.seeder', '=', true)
            ->where('h.updated_at', '>=', $desde);
    }
}
