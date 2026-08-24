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
