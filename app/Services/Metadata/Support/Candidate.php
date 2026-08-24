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
 * A single provider hit, normalised to the shared id-set so hits from
 * different databases can be compared and voted on.
 *
 * Port of id_resolver.py's _cand() dict. The id fields stay mutable because
 * TmdbClient::fillExternal() back-fills imdb/tvdb on a TMDB candidate after
 * construction; $score is likewise set by the resolver once queries are known.
 */
final class Candidate
{
    public int $tmdbId;

    public int $tvdbId;

    public string $imdbId;

    public int $malId;

    /**
     * Best combined title+year match score across the resolver's query set.
     */
    public float $score = 0.0;

    public function __construct(
        public readonly string $provider,
        public readonly string $title,
        public readonly ?int $year,
        public readonly string $category,
        mixed $tmdb = 0,
        mixed $tvdb = 0,
        mixed $imdb = '',
        mixed $mal = 0,
        public readonly string $posterUrl = '',
    ) {
        $this->tmdbId = Normalize::toInt($tmdb);
        $this->tvdbId = Normalize::toInt($tvdb);
        $this->imdbId = Normalize::imdbId($imdb);
        $this->malId = Normalize::toInt($mal);
    }
}
