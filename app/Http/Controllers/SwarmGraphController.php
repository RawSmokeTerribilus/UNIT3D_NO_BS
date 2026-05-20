<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SwarmGraphController extends Controller
{
    public function index(): View
    {
        return view('swarm.graph');
    }

    public function data(Request $request): JsonResponse
    {
        $mode = $request->string('mode', 'network')->toString();
        $torrentId = $request->integer('torrent_id');
        $cap = $request->integer('cap'); // 0 = uncapped (global propagation)

        $cacheKey = "swarm-graph:{$mode}:{$torrentId}:{$cap}";

        $cached = cache()->get($cacheKey);
        if ($cached !== null && !empty($cached['nodes'])) {
            return response()->json($cached);
        }

        $result = match ($mode) {
            'social'      => $this->socialGraph(),
            'content'     => $this->contentGraph(),
            'propagation' => $this->propagationGraph($torrentId, $cap),
            default       => $this->networkGraph(),
        };

        if (!empty($result['nodes'])) {
            cache()->put($cacheKey, $result, 300);
        }

        return response()->json($result);
    }

    private function networkGraph(): array
    {
        // Nodes are uncapped (cheap GROUP BY). $topIds caps only the O(n²) link self-join.
        $topIds = DB::table('peers')
            ->where('active', '=', 1)
            ->select('torrent_id', DB::raw('COUNT(*) AS cnt'))
            ->groupBy('torrent_id')
            ->orderByDesc('cnt')
            ->limit(1000)
            ->pluck('torrent_id');

        $nodes = DB::table('peers')
            ->join('torrents', 'torrents.id', '=', 'peers.torrent_id')
            ->selectRaw('
                peers.torrent_id AS id,
                torrents.name,
                torrents.category_id,
                torrents.type_id,
                torrents.resolution_id,
                torrents.seeders AS tracker_seeders,
                torrents.leechers AS tracker_leechers,
                torrents.times_completed,
                COUNT(*) AS peer_count,
                SUM(peers.active = 1 AND peers.seeder = 1) AS seeders,
                SUM(peers.active = 1 AND peers.seeder = 0) AS leechers,
                SUM(peers.active = 1 AND peers.updated_at < (NOW() - INTERVAL 2 HOUR)) AS stale,
                ROUND(
                    SUM(peers.active = 1 AND peers.seeder = 1)
                    / NULLIF(SUM(peers.active = 1), 0) * 100,
                1) AS health_pct
            ')
            ->where('peers.active', '=', 1)
            ->groupBy(
                'peers.torrent_id', 'torrents.name', 'torrents.category_id',
                'torrents.type_id', 'torrents.resolution_id',
                'torrents.seeders', 'torrents.leechers', 'torrents.times_completed'
            )
            ->get()
            ->map(fn ($n) => array_merge((array) $n, ['url' => route('torrents.show', ['id' => $n->id])]))
            ->toArray();

        $links = DB::table('peers AS p1')
            ->join('peers AS p2', function ($join): void {
                $join->on('p1.user_id', '=', 'p2.user_id')
                    ->whereColumn('p1.torrent_id', '<', 'p2.torrent_id')
                    ->where('p2.active', '=', 1);
            })
            ->whereIn('p1.torrent_id', $topIds)
            ->whereIn('p2.torrent_id', $topIds)
            ->where('p1.active', '=', 1)
            ->selectRaw('p1.torrent_id AS source, p2.torrent_id AS target, COUNT(*) AS weight')
            ->groupBy('p1.torrent_id', 'p2.torrent_id')
            ->orderByDesc('weight')
            ->limit(800)
            ->get()
            ->toArray();

        return ['nodes' => $nodes, 'links' => $links];
    }

    private function socialGraph(): array
    {
        $seedtimeSub = DB::table('history')
            ->select('user_id', DB::raw('SUM(seedtime) AS total_seedtime'))
            ->groupBy('user_id');

        $nodes = DB::table('peers')
            ->join('users', 'users.id', '=', 'peers.user_id')
            ->leftJoinSub($seedtimeSub, 'st', 'st.user_id', '=', 'peers.user_id')
            ->selectRaw('
                peers.user_id AS id,
                users.username AS name,
                users.uploaded AS uploaded,
                users.downloaded AS downloaded,
                users.hitandruns AS hitandruns,
                users.created_at AS created_at,
                COALESCE(st.total_seedtime, 0) AS seedtime,
                COUNT(DISTINCT peers.torrent_id) AS torrent_count,
                SUM(peers.active = 1 AND peers.seeder = 1) AS seeding_now,
                SUM(peers.active = 1 AND peers.seeder = 0) AS leeching_now
            ')
            ->where('peers.active', '=', 1)
            ->groupBy(
                'peers.user_id', 'users.username', 'users.uploaded', 'users.downloaded',
                'users.hitandruns', 'users.created_at', 'st.total_seedtime'
            )
            ->get()
            ->map(function ($n) {
                $ratio = $n->downloaded > 0
                    ? round($n->uploaded / $n->downloaded, 2)
                    : ($n->uploaded > 0 ? 999.0 : 0.0);

                $ageDays = $n->created_at !== null
                    ? max(0, (int) ((time() - strtotime($n->created_at)) / 86400))
                    : 0;

                return array_merge((array) $n, [
                    'ratio'    => $ratio,
                    'age_days' => $ageDays,
                    'url'      => route('users.show', ['user' => $n->name]),
                ]);
            })
            ->toArray();

        $links = DB::table('peers AS p1')
            ->join('peers AS p2', function ($join): void {
                $join->on('p1.torrent_id', '=', 'p2.torrent_id')
                    ->whereColumn('p1.user_id', '<', 'p2.user_id')
                    ->where('p2.active', '=', 1);
            })
            ->where('p1.active', '=', 1)
            ->selectRaw('p1.user_id AS source, p2.user_id AS target, COUNT(*) AS weight')
            ->groupBy('p1.user_id', 'p2.user_id')
            ->having('weight', '>=', 1)
            ->orderByDesc('weight')
            ->limit(1000)
            ->get()
            ->toArray();

        return ['nodes' => $nodes, 'links' => $links];
    }

    private function contentGraph(): array
    {
        // Top 1000 active torrents by peer count — caps both query and render load.
        $activeIds = DB::table('peers')
            ->where('active', '=', 1)
            ->select('torrent_id', DB::raw('COUNT(*) AS pc'))
            ->groupBy('torrent_id')
            ->orderByDesc('pc')
            ->limit(1000)
            ->pluck('torrent_id');

        $nodes = DB::table('torrents')
            ->leftJoin('history', 'history.torrent_id', '=', 'torrents.id')
            ->whereIn('torrents.id', $activeIds)
            ->selectRaw('
                torrents.id,
                torrents.name,
                torrents.category_id,
                torrents.type_id,
                torrents.size,
                torrents.created_at,
                COUNT(history.torrent_id) AS times_completed
            ')
            ->groupBy(
                'torrents.id', 'torrents.name', 'torrents.category_id',
                'torrents.type_id', 'torrents.size', 'torrents.created_at'
            )
            ->get()
            ->map(function ($n) {
                $ageDays = $n->created_at !== null
                    ? max(0, (int) ((time() - strtotime($n->created_at)) / 86400))
                    : 0;

                return array_merge((array) $n, [
                    'age_days' => $ageDays,
                    'url'      => route('torrents.show', ['id' => $n->id]),
                ]);
            })
            ->toArray();

        // Same TMDB movie — alternate releases (both must have active peers).
        $alternate = DB::table('torrents AS t1')
            ->join('torrents AS t2', function ($j): void {
                $j->on('t1.tmdb_movie_id', '=', 't2.tmdb_movie_id')
                    ->whereColumn('t1.id', '<', 't2.id')
                    ->whereNotNull('t1.tmdb_movie_id');
            })
            ->whereExists(fn ($q) => $q->from('peers')->whereColumn('peers.torrent_id', 't1.id')->where('peers.active', '=', 1))
            ->whereExists(fn ($q) => $q->from('peers')->whereColumn('peers.torrent_id', 't2.id')->where('peers.active', '=', 1))
            ->selectRaw("t1.id AS source, t2.id AS target, 'alternate' AS link_type, 3 AS weight")
            ->get()
            ->toArray();

        // Same uploader — hub-spoke per uploader instead of all-pairs.
        // Hub = most-seeded active torrent per uploader (their "flagship").
        // Spokes = up to 200 other active torrents from same uploader, by seeders desc.
        // Avoids O(n²) hairball on single-uploader trackers.
        // Anon torrents excluded (uploader edge would leak hidden identity).
        $hubMap = DB::table('torrents AS t')
            ->join(
                DB::raw('(
                    SELECT t2.user_id, MAX(t2.seeders) AS max_s
                    FROM torrents AS t2
                    INNER JOIN peers AS p2 ON p2.torrent_id = t2.id AND p2.active = 1
                    WHERE t2.anon = 0
                    GROUP BY t2.user_id
                ) AS mx'),
                fn ($j) => $j->on('t.user_id', '=', 'mx.user_id')->on('t.seeders', '=', 'mx.max_s')
            )
            ->where('t.anon', '=', 0)
            ->select('t.user_id', 't.id AS hub_id')
            ->get()
            ->unique('user_id')
            ->pluck('hub_id', 'user_id');

        $uploader = [];
        foreach ($hubMap as $userId => $hubId) {
            DB::table('torrents')
                ->join('peers', 'peers.torrent_id', '=', 'torrents.id')
                ->where('torrents.user_id', '=', $userId)
                ->where('torrents.anon', '=', 0)
                ->where('torrents.id', '!=', $hubId)
                ->where('peers.active', '=', 1)
                ->groupBy('torrents.id', 'torrents.seeders')
                ->orderByDesc('torrents.seeders')
                ->limit(200)
                ->pluck('torrents.id')
                ->each(function ($spokeId) use ($hubId, &$uploader): void {
                    $uploader[] = (object) ['source' => $hubId, 'target' => $spokeId, 'link_type' => 'uploader', 'weight' => 2];
                });
        }

        // Co-downloaded by ≥3 users
        $codownload = DB::table('history AS h1')
            ->join('history AS h2', function ($j): void {
                $j->on('h1.user_id', '=', 'h2.user_id')
                    ->whereColumn('h1.torrent_id', '<', 'h2.torrent_id');
            })
            ->selectRaw("h1.torrent_id AS source, h2.torrent_id AS target, 'codownload' AS link_type, COUNT(*) AS weight")
            ->groupByRaw('h1.torrent_id, h2.torrent_id')
            ->having('weight', '>=', 3)
            ->orderByDesc('weight')
            ->limit(600)
            ->get()
            ->toArray();

        $links = array_merge($alternate, $uploader, $codownload);

        // Drop any link whose source or target is not in the node set.
        // Codownload edges reference historical torrent IDs that may have no active peers
        // and are therefore absent from nodes — force-graph throws "node not found" on those.
        $nodeIdSet = array_flip(array_column($nodes, 'id'));
        $links = array_values(array_filter(
            $links,
            fn ($l) => isset($nodeIdSet[$l->source]) && isset($nodeIdSet[$l->target])
        ));

        return ['nodes' => $nodes, 'links' => $links];
    }

    private function propagationGraph(int $torrentId, int $cap = 0): array
    {
        if ($torrentId === 0) {
            return $this->globalPropagationGraph($cap);
        }

        $torrent = DB::table('torrents')
            ->where('id', '=', $torrentId)
            ->select(['id', 'name'])
            ->first();

        $timeline = DB::table('history')
            ->join('users', 'users.id', '=', 'history.user_id')
            ->selectRaw('
                DATE(history.created_at) AS date,
                history.user_id,
                users.username,
                history.seeder,
                history.agent
            ')
            ->where('history.torrent_id', '=', $torrentId)
            ->orderBy('history.created_at')
            ->get()
            ->toArray();

        $live = DB::table('peers')
            ->join('users', 'users.id', '=', 'peers.user_id')
            ->where('peers.torrent_id', '=', $torrentId)
            ->where('peers.active', '=', 1)
            ->selectRaw('
                peers.user_id,
                users.username,
                peers.seeder,
                peers.connectable,
                peers.agent,
                peers.created_at,
                DATE(peers.created_at) AS date
            ')
            ->get()
            ->toArray();

        return ['torrent' => $torrent, 'timeline' => $timeline, 'live' => $live];
    }

    /**
     * Global propagation — bipartite graph: torrents on one side, users on the other.
     *
     * Bounded + coherent (the frontend renders it as two separated columns):
     *  - $cap > 0  → top $cap torrents by active peer count (lighter, for 2D layouts).
     *    $cap === 0 → ALL torrents with ≥1 active peer (full map, for 3D).
     *  - All active peers of those torrents (no mid-swarm truncation).
     *  - Only "bridge" users (active in ≥2 of the selected torrents) — singleton
     *    users are leaf noise; dropping them keeps the column readable.
     */
    private function globalPropagationGraph(int $cap = 0): array
    {
        $query = DB::table('peers')
            ->where('active', '=', 1)
            ->select('torrent_id', DB::raw('COUNT(*) AS peer_count'))
            ->groupBy('torrent_id')
            ->orderByDesc('peer_count');

        if ($cap > 0) {
            $query->limit($cap);
        }

        $topTorrents = $query->get();

        if ($topTorrents->isEmpty()) {
            return ['global' => true, 'bipartite' => true, 'nodes' => [], 'links' => []];
        }

        $torrentIds = $topTorrents->pluck('torrent_id')->all();
        $peerCounts = $topTorrents->pluck('peer_count', 'torrent_id');

        $rows = DB::table('peers')
            ->join('torrents', 'torrents.id', '=', 'peers.torrent_id')
            ->join('users', 'users.id', '=', 'peers.user_id')
            ->where('peers.active', '=', 1)
            ->whereIn('peers.torrent_id', $torrentIds)
            ->selectRaw('
                peers.torrent_id,
                torrents.name AS torrent_name,
                torrents.category_id,
                peers.user_id,
                users.username,
                peers.seeder
            ')
            ->get();

        $userDegree = [];
        foreach ($rows as $r) {
            $userDegree[$r->user_id] = ($userDegree[$r->user_id] ?? 0) + 1;
        }

        // total downloads (distinct torrents in history) for the bridge users
        $bridgeUserIds = [];
        foreach ($userDegree as $uid => $deg) {
            if ($deg >= 2) {
                $bridgeUserIds[] = $uid;
            }
        }
        $userDownloads = empty($bridgeUserIds)
            ? collect()
            : DB::table('history')
                ->whereIn('user_id', $bridgeUserIds)
                ->select('user_id', DB::raw('COUNT(DISTINCT torrent_id) AS dl'))
                ->groupBy('user_id')
                ->pluck('dl', 'user_id');

        $nodes = [];
        $links = [];

        foreach ($rows as $r) {
            // bridge users only — those active in ≥2 of the top torrents
            if (($userDegree[$r->user_id] ?? 0) < 2) {
                continue;
            }

            $tid = 't'.$r->torrent_id;
            $uid = 'u'.$r->user_id;

            if (!isset($nodes[$tid])) {
                $nodes[$tid] = [
                    'id'          => $tid,
                    'name'        => $r->torrent_name,
                    'kind'        => 'torrent',
                    'category_id' => $r->category_id,
                    'degree'      => (int) ($peerCounts[$r->torrent_id] ?? 0),
                    'url'         => route('torrents.show', ['id' => $r->torrent_id]),
                ];
            }
            if (!isset($nodes[$uid])) {
                $nodes[$uid] = [
                    'id'        => $uid,
                    'name'      => $r->username,
                    'kind'      => 'user',
                    'degree'    => $userDegree[$r->user_id],
                    'downloads' => (int) ($userDownloads[$r->user_id] ?? 0),
                    'url'       => route('users.show', ['user' => $r->username]),
                ];
            }

            $links[] = [
                'source' => $uid,
                'target' => $tid,
                'weight' => 1,
                'seeder' => (int) $r->seeder,
            ];
        }

        return ['global' => true, 'bipartite' => true, 'nodes' => array_values($nodes), 'links' => $links];
    }
}
