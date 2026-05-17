<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GlobalRateLimit;
use App\Models\MalAnime;
use App\Services\Mal\Client\Anime;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessMalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $id, public bool $force = false)
    {
    }

    public int $tries = 3;

    public int $backoff = 300;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            Skip::when(!$this->force && cache()->has("mal-anime-scraper:{$this->id}")),
            (new WithoutOverlapping((string) $this->id))->dontRelease()->expireAfter(30),
            new RateLimited(GlobalRateLimit::MAL),
        ];
    }

    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    public function handle(): void
    {
        $client = new Anime($this->id);
        $data = $client->getData();

        if ($data === null) {
            return;
        }

        MalAnime::updateOrCreate(['id' => $this->id], $client->normalize($data));

        // MAL API is free-tier — cache for 8 hours to avoid hammering
        cache()->put("mal-anime-scraper:{$this->id}", now(), 8 * 3600);
    }
}
