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

use App\Achievements\UserFilled100Requests;
use App\Achievements\UserFilled25Requests;
use App\Achievements\UserFilled50Requests;
use App\Achievements\UserFilled75Requests;
use App\Models\TorrentRequest;
use App\Models\User;
use App\Notifications\NewRequestFillApprove;
use App\Repositories\ChatRepository;
use Illuminate\Support\Carbon;

/**
 * Aprobar un relleno: lo que hacía el botón «Aprobar», sacado aquí para que la
 * aprobación automática de los 7 días (requests:rellenos-pendientes) pase por
 * el mismo camino que la de una persona y no por una copia.
 */
class RequestFillApproval
{
    /**
     * Marca la petición como aprobada, paga el bounty al que la rellenó, le
     * suma los logros, lo canta en el chat y le avisa. Devuelve al rellenador.
     */
    public static function approve(TorrentRequest $torrentRequest, User $approver): User
    {
        $filler = $torrentRequest->filler()->sole();

        $torrentRequest->update([
            'approved_by'   => $approver->id,
            'approved_when' => Carbon::now(),
        ]);

        $filler->increment('seedbonus', (float) $torrentRequest->bounty);

        // Achievements
        if (!$torrentRequest->filled_anon) {
            $filler->addProgress(new UserFilled25Requests(), 1);
            $filler->addProgress(new UserFilled50Requests(), 1);
            $filler->addProgress(new UserFilled75Requests(), 1);
            $filler->addProgress(new UserFilled100Requests(), 1);
        }

        // Auto Shout
        $chat = app(ChatRepository::class);

        if ($torrentRequest->filled_anon) {
            $chat->systemMessage(
                \sprintf('An anonymous user has filled request, [url=%s]%s[/url]', href_request($torrentRequest), $torrentRequest->name)
            );
        } else {
            $chat->systemMessage(
                \sprintf('[url=%s]%s[/url] has filled request, [url=%s]%s[/url]', href_profile($filler), $filler->username, href_request($torrentRequest), $torrentRequest->name)
            );
        }

        $filler->notify(new NewRequestFillApprove($torrentRequest));

        return $filler;
    }
}
