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

namespace App\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SwarmIntelligence extends Component
{
    public int $torrentId;

    final public function mount(int $torrentId): void
    {
        $this->torrentId = $torrentId;
    }

    final protected array $stats {
        get {
            $row = DB::table('peers')
                ->selectRaw('
                    COUNT(*) AS total,
                    SUM(active = 1) AS active_total,
                    SUM(active = 1 AND seeder = 1) AS seeder_count,
                    SUM(active = 1 AND seeder = 0) AS leecher_count,
                    SUM(active = 1 AND updated_at < (NOW() - INTERVAL 2 HOUR)) AS stale_count,
                    AVG(CASE
                        WHEN active = 1 AND seeder = 0 AND (downloaded + `left`) > 0
                        THEN downloaded / (downloaded + `left`) * 100
                        ELSE NULL
                    END) AS avg_leech_progress
                ')
                ->where('torrent_id', '=', $this->torrentId)
                ->first();

            $active   = (int) ($row->active_total ?? 0);
            $seeders  = (int) ($row->seeder_count ?? 0);
            $leechers = (int) ($row->leecher_count ?? 0);

            return [
                'active'             => $active,
                'seeders'            => $seeders,
                'leechers'           => $leechers,
                'stale'              => (int) ($row->stale_count ?? 0),
                'avg_leech_progress' => $row->avg_leech_progress !== null
                    ? round((float) $row->avg_leech_progress, 1)
                    : null,
                'health_pct'         => $active > 0
                    ? round($seeders / $active * 100, 1)
                    : 0.0,
            ];
        }
    }

    final protected array $topAgents {
        get {
            return DB::table('peers')
                ->select(['agent', DB::raw('COUNT(*) AS cnt')])
                ->where('torrent_id', '=', $this->torrentId)
                ->where('active', '=', 1)
                ->groupBy('agent')
                ->orderByDesc('cnt')
                ->limit(3)
                ->get()
                ->toArray();
        }
    }

    final public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.swarm-intelligence', [
            'stats'     => $this->stats,
            'topAgents' => $this->topAgents,
        ]);
    }
}
