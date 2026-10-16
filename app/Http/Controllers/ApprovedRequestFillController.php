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

/**
 * MODIFICADO PARA NOBS
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
 * Se distribuye bajo la misma licencia, GNU AGPL v3.0.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Http\Controllers;

use App\Models\TorrentRequest;
use App\Repositories\ChatRepository;
use App\Services\RequestFillApproval;
use Illuminate\Http\Request;

/**
 * @see \Tests\Todo\Feature\Http\Controllers\RequestControllerTest
 */
class ApprovedRequestFillController extends Controller
{
    /**
     * RequestController Constructor.
     */
    public function __construct(private readonly ChatRepository $chatRepository)
    {
    }

    /**
     * Approve A Torrent Request.
     */
    public function store(Request $request, TorrentRequest $torrentRequest): \Illuminate\Http\RedirectResponse
    {
        abort_unless(($request->user()->id === $torrentRequest->user_id || $request->user()->group->is_modo) && $torrentRequest->approved_when === null, 403);

        // NOBS: la aprobación vive en RequestFillApproval, que también usa la
        // aprobación automática de los 7 días (requests:rellenos-pendientes).
        $filler = RequestFillApproval::approve($torrentRequest, $request->user());

        return to_route('requests.show', ['torrentRequest' => $torrentRequest])
            ->with('success', \sprintf(trans('request.approved-user'), $torrentRequest->name, $torrentRequest->filled_anon ? 'Anonymous' : $filler->username));
    }

    /**
     * Destroy A Torrent Request Fill.
     */
    public function destroy(Request $request, TorrentRequest $torrentRequest): \Illuminate\Http\RedirectResponse
    {
        abort_unless((bool) $request->user()->group->is_modo, 403);

        $filler = $torrentRequest->filler;

        $torrentRequest->update([
            'approved_by'   => null,
            'approved_when' => null,
        ]);

        // TODO: Change database column to signed from unsigned to handle negative bon.
        $refunded = min($torrentRequest->bounty, (float) $filler->seedbonus);

        $filler->decrement('seedbonus', (float) $refunded);

        return to_route('requests.show', ['torrentRequest' => $torrentRequest])
            ->with('success', trans('request.request-reset'));
    }
}
