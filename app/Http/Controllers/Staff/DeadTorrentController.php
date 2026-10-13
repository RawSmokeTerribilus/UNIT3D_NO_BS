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

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DeadTorrentNotice;
use App\Services\DeadTorrents;
use Illuminate\Http\Request;

/**
 * Staff → Comandos → Torrents muertos. Primero la lista; la peticion de
 * resiembra sale solo al confirmar. El borrado lo hace auto:dead-torrents.
 */
class DeadTorrentController extends Controller
{
    public function index(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('Staff.dead-torrent.index', [
            'horas'      => DeadTorrents::hours(),
            'candidatos' => DeadTorrents::candidates(),
            'pendientes' => DeadTorrentNotice::query()
                ->with('staff:id,username')
                ->where('estado', '=', DeadTorrentNotice::PENDIENTE)
                ->orderBy('borrar_at')
                ->get(),
            'cerrados' => DeadTorrentNotice::query()
                ->where('estado', '!=', DeadTorrentNotice::PENDIENTE)
                ->latest('resuelto_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $hechos = DeadTorrents::notify($ids, $request->user());

        $saltados = \count($ids) - \count($hechos);

        return to_route('staff.commands.dead_torrents.index')
            ->with('info', \sprintf(
                '✅ Resiembra pedida en %d torrent(s), %d privado(s) en cola.%s',
                \count($hechos),
                array_sum(array_column($hechos, 'destinatarios')),
                $saltados > 0 ? " {$saltados} ya no cumplían (volvieron a tener semilla o ya tenían petición)." : '',
            ));
    }

    public function cancel(DeadTorrentNotice $notice): \Illuminate\Http\RedirectResponse
    {
        abort_unless($notice->estado === DeadTorrentNotice::PENDIENTE, 422);

        $notice->update(['estado' => DeadTorrentNotice::CANCELADO, 'resuelto_at' => now()]);

        return to_route('staff.commands.dead_torrents.index')
            ->with('info', "Petición cancelada: #{$notice->torrent_id} {$notice->nombre} ya no se borrará.");
    }
}
