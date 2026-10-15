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
 * @author     Roardom <roardom@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Unit3dAnnounce;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PeerController extends Controller
{
    /**
     * Show user peers.
     */
    public function index(Request $request, User $user): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        abort_unless($request->user()->group->is_modo || $request->user()->is($user), 403);

        return view('user.peer.index', [
            'user'    => $user,
            'history' => DB::table('history')
                ->where('user_id', '=', $user->id)
                ->where('created_at', '>', $user->created_at)
                ->selectRaw('sum(actual_uploaded) as upload')
                ->selectRaw('sum(uploaded) as credited_upload')
                ->selectRaw('sum(actual_downloaded) as download')
                ->selectRaw('sum(downloaded) as credited_download')
                ->first(),
        ]);
    }

    /**
     * Delete user peers.
     */
    public function massDestroy(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        // El socio vacía los suyos; el staff, los de cualquiera menos los del owner.
        $isSelf = $request->user()->is($user);
        $isStaffActing = !$isSelf && $request->user()->group->is_modo && !$user->group->is_owner;

        abort_unless($isSelf || $isStaffActing, 403);

        // Check if User can flush
        if ($isSelf && $request->user()->own_flushes <= 0) {
            return redirect()->back()->withErrors('Solo puedes vaciar tus pares fantasma dos veces al día.');
        }

        // Only peers older than 70 minutes are allowed to be flushed otherwise users could use this to exploit leech slots
        $inactiveFor = 70 * 60;
        $cutoff = (new Carbon())->copy()->subSeconds($inactiveFor);

        // Con el announce en Rust el límite de 3 pares se cuenta en su memoria:
        // se le pide que suelte los pares viejos del socio torrent a torrent
        // (`peers/reap-user`). En la base se marcan inactivos en vez de borrarse,
        // para que el staff siga viendo la pista en /torrents/{id}/peers.
        $reaped = 0;

        if (config('announce.external_tracker.is_enabled')) {
            $torrentIds = $user->peers()
                ->where('updated_at', '<', $cutoff)
                ->distinct()
                ->pluck('torrent_id');

            foreach ($torrentIds as $torrentId) {
                $count = Unit3dAnnounce::reapUserPeers($torrentId, $user->id, $inactiveFor);

                if ($count === false) {
                    return redirect()->back()->withErrors('El tracker no ha respondido. Prueba en un rato.');
                }

                $reaped += $count;
            }
        }

        $marked = $user->peers()
            ->where('active', '=', 1)
            ->where('updated_at', '<', $cutoff)
            ->update([
                'active'     => false,
                'updated_at' => DB::raw('updated_at'),
            ]);

        $user->history()
            ->where('active', '=', 1)
            ->where('updated_at', '<', $cutoff)
            ->update([
                'active'     => 0,
                'updated_at' => DB::raw('updated_at'),
            ]);

        if ($isSelf) {
            $user->decrement('own_flushes');
        }

        $count = max($marked, $reaped);

        return redirect()->back()->with('success', $count > 0
            ? "Listo: {$count} par(es) sin anunciar desde hace más de 70 min ya no cuentan para el límite de 3."
            : 'No había pares sin anunciar desde hace más de 70 min. Los más recientes pueden seguir vivos.');
    }
}
