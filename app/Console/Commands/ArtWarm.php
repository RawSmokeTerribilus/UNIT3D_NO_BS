<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\ArtImageProxyController;
use App\Models\MetadataArtwork;
use App\Models\TmdbMovie;
use App\Models\TmdbTv;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Pre-warm the art-proxy disk cache (storage/app/art-proxy) so users never pay
 * the first-hit fetch+resize.
 *
 * Enumerates every poster/backdrop URL the site can render, then for each
 * (url, size) fetches + resizes + caches if missing — the same logic as
 * ArtImageProxyController::show(), minus HTTP / auth / throttle. Kept in sync
 * with that controller by reusing its HOSTS + SIZES constants.
 *
 * Idempotent: already-cached files are skipped (no network hit). Throttled
 * (--rate fetches/sec, only on actual network hits) to be polite to provider
 * CDNs. Safe on a live site — only outbound image fetches + writes to the cache
 * dir, never touches request handling.
 *
 * Schedule after meta:rotate-covers to pre-cache rotated covers before any user
 * hits them.
 */
final class ArtWarm extends Command
{
    protected $signature = 'art:warm
        {--rate=5 : Max network fetches per second}
        {--limit=0 : Stop after N targets (0 = all) — for dry runs}
        {--max-seconds=0 : Stop after N wall-clock seconds (0 = no budget) — keeps scheduled/queued runs under the worker timeout}
        {--posters-only : Warm posters only}
        {--backdrops-only : Warm backdrops only}';

    protected $description = 'Pre-warm the art-proxy image cache for all poster/backdrop URLs';

    /** @var list<string> poster size tokens the site renders */
    private const array POSTER_SIZES = ['poster_small', 'poster_mid', 'poster_big'];

    /** @var list<string> backdrop size tokens the site renders */
    private const array BACKDROP_SIZES = ['back_big', 'back_small'];

    final public function handle(): int
    {
        // Batch image decoding (GD) needs more headroom than the 128M default.
        @ini_set('memory_limit', '512M');

        $rate       = max(1, (int) $this->option('rate'));
        $sleepUs    = (int) (1_000_000 / $rate);
        $limit      = (int) $this->option('limit');
        $maxSeconds = (int) $this->option('max-seconds');
        $startedAt  = microtime(true);
        $hosts      = ArtImageProxyController::HOSTS;

        $allowed = static fn (?string $u): bool => $u !== null && $u !== ''
            && \in_array(strtolower((string) parse_url($u, PHP_URL_HOST)), $hosts, true);

        /** @var list<array{0: string, 1: string}> $jobs */
        $jobs = [];

        if (!$this->option('backdrops-only')) {
            foreach ($this->posterUrls()->filter($allowed)->unique() as $u) {
                foreach (self::POSTER_SIZES as $s) {
                    $jobs[] = [$s, $u];
                }
            }
        }

        if (!$this->option('posters-only')) {
            foreach ($this->backdropUrls()->filter($allowed)->unique() as $u) {
                foreach (self::BACKDROP_SIZES as $s) {
                    $jobs[] = [$s, $u];
                }
            }
        }

        if ($limit > 0) {
            $jobs = \array_slice($jobs, 0, $limit);
        }

        $total = \count($jobs);
        $this->info("art:warm — {$total} (size,url) targets at {$rate}/sec");
        Log::info('art:warm starting', ['targets' => $total, 'rate' => $rate]);

        $cached = 0;
        $fetched = 0;
        $failed = 0;
        $stoppedEarly = false;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($jobs as [$size, $url]) {
            $result = $this->warmOne($size, $url);

            match ($result) {
                'cached'  => $cached++,
                'fetched' => $fetched++,
                default   => $failed++,
            };

            // Only throttle on actual network hits; cached skips run free.
            if ($result !== 'cached') {
                usleep($sleepUs);
            }

            $bar->advance();

            // Wall-clock budget: stop cleanly so scheduled/queued runs never hit
            // the worker timeout. Idempotent skip means the next run resumes.
            if ($maxSeconds > 0 && (microtime(true) - $startedAt) >= $maxSeconds) {
                $stoppedEarly = true;
                break;
            }
        }

        $bar->finish();
        $this->newLine();
        $done = $fetched + $cached + $failed;
        $this->info("done: fetched={$fetched} already-cached={$cached} failed={$failed} processed={$done}/{$total}".($stoppedEarly ? ' (time budget reached — resume next run)' : ''));
        Log::info('art:warm finished', compact('total', 'done', 'fetched', 'cached', 'failed', 'stoppedEarly'));

        return self::SUCCESS;
    }

    /**
     * Fetch + resize + cache one (size, url) if missing.
     *
     * Mirrors ArtImageProxyController::show() — keep in sync.
     *
     * @return string 'cached' | 'fetched' | 'failed'
     */
    private function warmOne(string $size, string $url): string
    {
        $sizes = ArtImageProxyController::SIZES;

        if (!isset($sizes[$size])) {
            return 'failed';
        }

        $width     = $sizes[$size]['width'];
        $cacheDir  = storage_path('app/art-proxy/'.$size);
        $cachePath = $cacheDir.'/'.sha1($url).'.jpg';

        if (file_exists($cachePath)) {
            return 'cached';
        }

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0o755, true);
        }

        try {
            if (strtolower((string) parse_url($url, PHP_URL_HOST)) === 'image.tmdb.org') {
                $fetch    = str_replace('/original/', '/'.$sizes[$size]['tmdb'].'/', $url);
                $response = Http::timeout(10)->get($fetch);

                if (!$response->successful()) {
                    return 'failed';
                }

                file_put_contents($cachePath, $response->body());
            } else {
                $response = Http::timeout(10)->get($url);

                if (!$response->successful()) {
                    return 'failed';
                }

                $image = Image::decode($response->body());

                if ($image->width() > $width) {
                    $image->scaleDown(width: $width);
                }

                $image->encode(new JpegEncoder(quality: 85))->save($cachePath);

                // Drop the decoded image now — PHP's GC won't reclaim it fast
                // enough in a tight loop, which exhausts memory_limit otherwise.
                // Intervention Image 4 has no destroy(); releasing the last
                // reference is what frees the underlying GD resource.
                unset($image);
            }
        } catch (\Throwable $e) {
            return 'failed';
        }

        return 'fetched';
    }

    private function posterUrls(): Collection
    {
        return collect()
            ->merge(TmdbMovie::query()->whereNotNull('poster')->pluck('poster'))
            ->merge(TmdbTv::query()->whereNotNull('poster')->pluck('poster'))
            ->merge(MetadataArtwork::query()->where('type', 'poster')->pluck('url'));
    }

    private function backdropUrls(): Collection
    {
        return collect()
            ->merge(TmdbMovie::query()->whereNotNull('backdrop')->pluck('backdrop'))
            ->merge(TmdbTv::query()->whereNotNull('backdrop')->pluck('backdrop'));
    }
}
