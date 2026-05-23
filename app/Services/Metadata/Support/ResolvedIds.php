<?php

declare(strict_types=1);

namespace App\Services\Metadata\Support;

/**
 * Outcome of ConsensusResolver::resolve() — a cross-checked id-set.
 *
 * confidence:
 *   'high' — at least two providers agree; safe to use unattended.
 *   'low'  — a best guess exists but is unverified; a human should confirm.
 *   'none' — nothing usable was found.
 */
final readonly class ResolvedIds
{
    /**
     * @param string             $imdbId 'ttNNNNNNN' form, or '' when unknown
     * @param 'MOVIE'|'TV'        $category
     * @param 'high'|'low'|'none' $confidence
     * @param list<Candidate>     $detail per-provider candidates, score-sorted
     */
    public function __construct(
        public int $tmdbId,
        public int $tvdbId,
        public string $imdbId,
        public int $malId,
        public string $category,
        public string $title,
        public ?int $year,
        public string $confidence,
        public int $votes,
        public int $malVotes,
        public array $detail,
    ) {}

    public function isTrusted(): bool
    {
        return $this->confidence === 'high';
    }
}
