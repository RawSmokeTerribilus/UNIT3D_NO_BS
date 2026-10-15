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

namespace App\Http\Controllers;

use App\Models\Peer;
use App\Models\Scopes\ApprovedScope;
use App\Models\Torrent;
use App\Services\Unit3dAnnounce;
use Illuminate\Http\Request;

class TorrentPeerController extends Controller
{
    /**
     * Display Peers Of A Torrent.
     */
    public function index(int $id, Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        $torrent = Torrent::withoutGlobalScope(ApprovedScope::class)->findOrFail($id);
        $isStaff = (bool) $request->user()->group->is_modo;

        // Estetoscopio para el staff: la tabla `peers` y la memoria del announce
        // guardan los inactivos con plazos distintos (2 días / 21 días) y un
        // `stopped` sólo desaparece de la memoria. Cruzando las dos se sabe por
        // qué está inactivo cada par. Clave en memoria: "{user_id}-{HEX(peer_id)}".
        $trackerPeers = null;

        if ($isStaff && config('announce.external_tracker.is_enabled')) {
            $externalTorrent = Unit3dAnnounce::getTorrent($id);

            if (\is_array($externalTorrent)) {
                $trackerPeers = $externalTorrent['peers'] ?? [];
            }
        }

        $peers = Peer::query()
            ->with('user.group')
            ->select(['torrent_id', 'user_id', 'peer_id', 'uploaded', 'downloaded', 'left', 'port', 'agent', 'created_at', 'updated_at', 'seeder', 'active', 'visible', 'connectable'])
            ->selectRaw('INET6_NTOA(ip) as ip')
            ->where('torrent_id', '=', $id)
            ->orderByRaw('user_id = ? DESC', [$request->user()->id])
            ->orderByDesc('active')
            ->orderByDesc('seeder')
            ->get()
            ->map(function ($peer) use ($torrent, $trackerPeers) {
                $progress = 100 * (1 - $peer->left / $torrent->size);
                $peer['progress'] = match (true) {
                    0 < $progress && $progress < 1    => 1,
                    99 < $progress && $progress < 100 => 99,
                    default                           => round($progress),
                };

                // Un único anuncio: el par nació y no volvió a anunciar. Si son
                // varios del mismo socio, es un cliente reiniciado sin `stopped`.
                $peer['single_announce'] = $peer->created_at !== null
                    && $peer->updated_at !== null
                    && $peer->updated_at->diffInSeconds($peer->created_at, true) < 5;

                // active | inactive | absent | null (sin datos del announce)
                $peer['tracker_state'] = null;

                if ($trackerPeers !== null) {
                    $trackerPeer = $trackerPeers[$peer->user_id.'-'.strtoupper(bin2hex($peer->peer_id))] ?? null;
                    $peer['tracker_state'] = match (true) {
                        $trackerPeer === null       => 'absent',
                        $trackerPeer['is_active']   => 'active',
                        default                     => 'inactive',
                    };
                }

                return $peer;
            });

        [$activePeers, $inactivePeers] = $peers->partition(fn ($peer) => $peer->active);

        return view('torrent.peers', [
            'torrent'       => $torrent,
            'isStaff'       => $isStaff,
            'hasTracker'    => $trackerPeers !== null,
            'activePeers'   => $activePeers,
            'inactivePeers' => $inactivePeers,
            'diagnosis'     => $isStaff ? $this->diagnose($peers) : collect(),
        ]);
    }

    /**
     * Resumen por socio para el staff: sólo los que tienen algo que mirar
     * (más de un par o algún inactivo).
     *
     * @param  \Illuminate\Support\Collection<int, Peer>                     $peers
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function diagnose(\Illuminate\Support\Collection $peers): \Illuminate\Support\Collection
    {
        return $peers
            ->groupBy('user_id')
            ->filter(fn ($userPeers) => $userPeers->count() > 1 || $userPeers->contains(fn ($peer) => !$peer->active))
            ->map(function ($userPeers) {
                $inactive = $userPeers->reject(fn ($peer) => $peer->active);
                $restarts = $inactive->filter(fn ($peer) => $peer->single_announce)->sortBy('created_at');

                return [
                    'user'      => $userPeers->first()->user,
                    'active'    => $userPeers->count() - $inactive->count(),
                    'ghosts'    => $inactive->filter(fn ($peer) => $peer->tracker_state === 'inactive')->count(),
                    'stopped'   => $inactive->filter(fn ($peer) => $peer->tracker_state === 'absent')->count(),
                    'inactive'  => $inactive->count(),
                    'restarts'  => $restarts->pluck('created_at'),
                    'agents'    => $userPeers->pluck('agent')->unique()->values(),
                    'mismatch'  => $userPeers->filter(fn ($peer) => $peer->active && $peer->tracker_state === 'inactive')->count(),
                ];
            })
            ->values();
    }
}
