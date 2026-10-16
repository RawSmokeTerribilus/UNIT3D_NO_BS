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

namespace App\Console\Commands;

use App\Models\TorrentRequestClaim;
use App\Notifications\NewRequestUnclaim;
use App\Repositories\ChatRepository;
use App\Services\AvisoDirecto;
use Illuminate\Console\Command;
use Exception;
use Throwable;

class AutoRecycleClaimedTorrentRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:recycle_claimed_torrent_requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recycle torrent requests that were claimed but not filled within 7 days.';

    /**
     * AutoRecycleClaimedTorrentRequests Constructor.
     */
    public function __construct(private readonly ChatRepository $chatRepository)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @throws Exception|Throwable If there is an error during the execution of the command.
     */
    final public function handle(): void
    {
        TorrentRequestClaim::query()
            ->with(['request.user', 'user'])
            ->where('created_at', '<', now()->subDays(7))
            ->whereHas(
                'request',
                fn ($query) => $query
                    ->whereNull('filled_by')
                    ->whereNull('filled_when')
                    ->whereNull('torrent_id')
            )
            ->chunkById(100, function ($claims): void {
                foreach ($claims as $claim) {
                    $trUrl = href_request($claim->request);

                    $this->chatRepository->systemMessage(
                        \sprintf('[url=%s]%s[/url] claim has been reset due to not being filled within 7 days.', $trUrl, $claim->request->name)
                    );

                    // NOBS: antes solo lo cantaba el chat; quien pidió y quien
                    // la tenía reclamada no se enteraban si no lo leían ahí.
                    $claim->request->user?->notify(new NewRequestUnclaim('torrent', 'System', $claim));

                    if ($claim->user !== null) {
                        AvisoDirecto::enviar(
                            $claim->user,
                            '🔓 Reclamo liberado',
                            "Tu reclamo de [b]{$claim->request->name}[/b] se ha liberado: pasaron 7 días sin rellenarla. Si sigues con ella, vuelve a reclamarla.",
                            'Ver petición',
                            $trUrl,
                        );
                    }

                    $claim->delete();
                }
            });

        $this->comment('Automated request claim reset command complete');
    }
}
