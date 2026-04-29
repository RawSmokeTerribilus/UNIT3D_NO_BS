<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Torrent;
use App\Models\TmdbPerson;
use Illuminate\Console\Command;
use Meilisearch\Client;
use Meilisearch\Exceptions\CommunicationException;

class MeilisearchFullRepair extends Command
{
    protected $signature = 'meilisearch:full-repair {--force : Skip confirmation}';

    protected $description = 'Full Meilisearch repair: health check, create indices, sync settings, reindex torrents + people, validate. Equivalent to NO_BS_meilisearch.sh steps 1-5.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('⚠️  This will wipe and rebuild ALL Meilisearch indices (torrents + people). Meilisearch may need a container restart via Portainer after completion. Continue?', false)) {
            $this->warn('Cancelled.');

            return 1;
        }

        $host = config('scout.meilisearch.host');
        $key = config('scout.meilisearch.key');
        $prefix = config('scout.prefix', '');
        $steps = 5;
        $current = 0;

        // ── Step 1: Health check ──────────────────────────────────────────
        $current++;
        $this->info("[{$current}/{$steps}] Health check...");

        try {
            $client = new Client($host, $key);
            $health = $client->health();

            if (($health['status'] ?? '') !== 'available') {
                $this->error("Meilisearch reports status: ".json_encode($health));

                return 1;
            }
        } catch (CommunicationException $e) {
            $this->error("Cannot connect to Meilisearch at {$host}: {$e->getMessage()}");

            return 1;
        }

        $this->line('  ✓ Meilisearch is healthy');

        // ── Step 2: Create indices if missing ─────────────────────────────
        $current++;
        $this->info("[{$current}/{$steps}] Ensuring indices exist...");

        $torrentsIndex = $prefix.'torrents';
        $peopleIndex = $prefix.'people';

        foreach ([$torrentsIndex => 'id', $peopleIndex => 'id'] as $uid => $primaryKey) {
            try {
                $client->getIndex($uid);
                $this->line("  ✓ Index '{$uid}' exists");
            } catch (\Meilisearch\Exceptions\ApiException $e) {
                if ($e->httpStatus === 404) {
                    $task = $client->createIndex($uid, ['primaryKey' => $primaryKey]);
                    $client->waitForTask($task['taskUid'], 30000);
                    $this->line("  ✓ Index '{$uid}' created");
                } else {
                    throw $e;
                }
            }
        }

        // ── Step 3: Sync index settings from scout config ────────────────
        $current++;
        $this->info("[{$current}/{$steps}] Syncing index settings (filterable/sortable attributes)...");

        $indexSettings = config('scout.meilisearch.index-settings', []);

        foreach ($indexSettings as $indexClass => $settings) {
            // Resolve index name: Eloquent model with searchableAs(), or plain string key like 'people'
            if (class_exists($indexClass) && method_exists($indexClass, 'makeAllSearchableUsing')) {
                $uid = (new $indexClass)->searchableAs();
            } else {
                $uid = $prefix.$indexClass;
            }

            try {
                $idx = $client->index($uid);

                if (! empty($settings['filterableAttributes'])) {
                    $idx->updateFilterableAttributes($settings['filterableAttributes']);
                }

                if (! empty($settings['sortableAttributes'])) {
                    $idx->updateSortableAttributes($settings['sortableAttributes']);
                }

                if (! empty($settings['searchableAttributes'])) {
                    $idx->updateSearchableAttributes($settings['searchableAttributes']);
                }

                if (! empty($settings['rankingRules'])) {
                    $idx->updateRankingRules($settings['rankingRules']);
                }

                if (isset($settings['typoTolerance'])) {
                    $idx->updateTypoTolerance($settings['typoTolerance']);
                }

                if (isset($settings['dictionary'])) {
                    $idx->updateDictionary($settings['dictionary']);
                }

                $this->line("  ✓ Settings synced for '{$uid}'");
            } catch (\Throwable $e) {
                $this->warn("  ⚠ Failed to sync '{$uid}': {$e->getMessage()}");
            }
        }

        if (empty($indexSettings)) {
            $this->warn('  ⚠ No index-settings found in config/scout.php');
        }

        // ── Step 4: Wipe + reindex both models ───────────────────────────
        $current++;
        $this->info("[{$current}/{$steps}] Reindexing data (this may take a while)...");

        // Torrents
        $torrentCount = Torrent::query()->count();
        $index = $client->getIndex($torrentsIndex);
        $index->updatePagination(['maxTotalHits' => max(1, $torrentCount) + 1000]);

        Torrent::removeAllFromSearch();
        Torrent::query()->selectRaw(Torrent::SEARCHABLE)->searchable();
        $this->line("  ✓ Torrents: {$torrentCount} records reindexed");

        // People
        $peopleCount = TmdbPerson::query()->count();
        $pIndex = $client->index($peopleIndex);

        TmdbPerson::query()
            ->select(['id', 'name', 'birthday', 'still'])
            ->chunkById(1000, function ($people) use ($pIndex) {
                $documents = $people->map(fn ($person) => [
                    'id'       => $person->id,
                    'name'     => $person->name,
                    'birthday' => $person->birthday,
                    'still'    => $person->still,
                ])->toArray();

                $pIndex->addDocuments($documents);
            });

        $this->line("  ✓ People: {$peopleCount} records reindexed");

        // ── Step 5: Validate ─────────────────────────────────────────────
        $current++;
        $this->info("[{$current}/{$steps}] Validating...");

        $torrentsSettings = $client->getIndex($torrentsIndex)->getSettings();
        $peopleSettings = $client->getIndex($peopleIndex)->getSettings();

        $tFilterable = \count($torrentsSettings['filterableAttributes'] ?? []);
        $tSortable = \count($torrentsSettings['sortableAttributes'] ?? []);
        $pFilterable = \count($peopleSettings['filterableAttributes'] ?? []);
        $pSortable = \count($peopleSettings['sortableAttributes'] ?? []);

        $this->line("  ✓ torrents: {$tFilterable} filterable, {$tSortable} sortable");
        $this->line("  ✓ people:   {$pFilterable} filterable, {$pSortable} sortable");

        if ($tFilterable === 0 || $tSortable === 0) {
            $this->warn('  ⚠ Torrents index may have incomplete settings');
        }

        // ── Summary ──────────────────────────────────────────────────────
        $this->newLine();
        $this->info("✅ Full repair complete. {$torrentCount} torrents + {$peopleCount} people reindexed.");
        $this->comment('💡 If search still fails, restart the Meilisearch container via Portainer.');

        return 0;
    }
}
