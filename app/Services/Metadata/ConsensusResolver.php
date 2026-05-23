<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Services\Metadata\Support\Candidate;
use App\Services\Metadata\Support\Normalize;
use App\Services\Metadata\Support\ResolvedIds;
use Closure;

/**
 * Multi-provider ID resolver with cross-reference consensus.
 *
 * Relying on a single database (TMDB) and blindly taking the first search
 * result mis-identifies releases when the real entry is missing, renamed, or
 * out-ranked by an unrelated title. Instead this queries several providers,
 * normalises every hit to a shared id-set, and votes:
 *
 *   - General providers (always): TMDB, OMDb/IMDB, TVmaze — vote on the
 *     IMDB id, which all three expose.
 *   - Anime providers (only when the release looks like anime): MAL via
 *     Jikan, AniList — vote on the MAL id.
 *
 * When at least two providers agree the result is 'high' confidence; a lone
 * hit is 'low'; nothing usable is 'none'.
 *
 * PHP port of RawLoadrr's id_resolver.py (the override store / pending-triage
 * persistence is deliberately omitted — that is CSI-side interactive state,
 * not part of the tracker-side consensus engine).
 *
 * TVDB has no client here on purpose: its v4 API moved to a paid per-user
 * PIN model. TVDB *numbers* are still harvested for free from TVmaze's
 * `externals` block and TMDB's `/external_ids`.
 */
final class ConsensusResolver
{
    /** Below this score a provider hit is not a valid vote. */
    private const MIN_CANDIDATE_SCORE = 0.50;

    /** Providers that must agree for 'high' confidence. */
    private const CONSENSUS_VOTES = 2;

    /** Minimum score for a hit to trigger by-id verification. */
    private const STRONG_LEAD = 0.70;

    /** @var list<string> providers that vote on the IMDB id */
    private const IMDB_PROVIDERS = ['tmdb', 'omdb', 'tvmaze'];

    /** @var list<string> providers that vote on the MAL id (anime) */
    private const MAL_PROVIDERS = ['jikan', 'anilist'];

    public function __construct(
        private readonly TmdbClient $tmdb,
        private readonly ImdbClient $imdb,
        private readonly TvmazeClient $tvmaze,
        private readonly MalClient $mal,
        private readonly AnilistClient $anilist,
    ) {}

    /**
     * Resolve a release to a cross-checked id-set.
     *
     * @param string                   $title    guessed title (may be trimmed)
     * @param ?int                     $year     release/air year, or null
     * @param string                  $category 'MOVIE' or 'TV'
     * @param list<string>             $aliases  extra title candidates (folder-derived)
     * @param bool                     $malHint  query the anime providers
     * @param ?Closure(string):void    $log      optional progress sink
     */
    public function resolve(
        string $title,
        ?int $year,
        string $category,
        array $aliases = [],
        bool $malHint = false,
        ?Closure $log = null,
    ): ResolvedIds {
        $log ??= static fn (string $m) => null;
        $category = strtoupper($category) === 'MOVIE' ? 'MOVIE' : 'TV';

        // guessit often eats a trailing year that is part of the real name
        // ("Disaster 2026" -> "Disaster"), so a year-inclusive variant is
        // always tried, plus any folder-derived aliases.
        $queries = $this->buildQueries($title, $year, $aliases);

        $candidates = $this->gather($queries, $year, $category, $malHint);

        foreach ($candidates as $c) {
            $c->score = $this->bestScore($c, $queries, $year);
        }

        $candidates = $this->dedupe($candidates);
        $valid = array_values(array_filter(
            $candidates,
            static fn (Candidate $c): bool => $c->score >= self::MIN_CANDIDATE_SCORE,
        ));

        // category may be wrong (movie tagged as TV or vice versa) -> retry once
        if (\count($valid) < self::CONSENSUS_VOTES) {
            $other = $category === 'TV' ? 'MOVIE' : 'TV';
            $log("weak results for {$category}, retrying as {$other}");

            $extra = $this->gather($queries, $year, $other, $malHint);

            foreach ($extra as $c) {
                $c->score = $this->bestScore($c, $queries, $year);
            }

            $candidates = $this->dedupe(array_merge($candidates, $extra));
            $more = array_values(array_filter(
                $candidates,
                static fn (Candidate $c): bool => $c->score >= self::MIN_CANDIDATE_SCORE && $c->category === $other,
            ));

            if (\count($more) > \count($valid)) {
                $valid = $more;
                $category = $other;
            }
        }

        // TMDB search hits carry no IMDB id; fetch external_ids for the
        // strongest few so they can join the vote (bounded to cap API calls).
        usort($valid, static fn (Candidate $a, Candidate $b): int => $b->score <=> $a->score);
        $expanded = 0;

        foreach ($valid as $c) {
            if ($c->provider === 'tmdb' && $c->imdbId === '' && $expanded < 3) {
                $this->tmdb->fillExternal($c);
                $expanded++;
            }
        }

        $detail = $candidates;
        usort($detail, static fn (Candidate $a, Candidate $b): int => $b->score <=> $a->score);

        // ── consensus 1: vote on the IMDB id (general providers) ──
        /** @var array<string,array<string,true>> $imdbVotes provider-set per imdb id */
        $imdbVotes = [];
        /** @var array<string,array{tmdb:int,tvdb:int,title:string,year:?int,score:float}> $pool */
        $pool = [];

        // accumulate the best title/year + any ids known for an imdb id
        $addToPool = static function (string $imdb, array $rec, float $score) use (&$pool): void {
            $m = $pool[$imdb] ?? ['tmdb' => 0, 'tvdb' => 0, 'title' => '', 'year' => null, 'score' => 0.0];
            $m['tmdb'] = $m['tmdb'] ?: Normalize::toInt($rec['tmdb'] ?? 0);
            $m['tvdb'] = $m['tvdb'] ?: Normalize::toInt($rec['tvdb'] ?? 0);

            if (!empty($rec['title']) && ($m['title'] === '' || $score > $m['score'])) {
                $m['title'] = (string) $rec['title'];
                $m['year'] = $rec['year'] ?? null;
            }

            $m['score'] = max($m['score'], $score);
            $pool[$imdb] = $m;
        };

        // search votes: every valid hit votes; a provider with several hits
        // can vote for each, but only once per id.
        foreach ($valid as $c) {
            if (\in_array($c->provider, self::IMDB_PROVIDERS, true) && $c->imdbId !== '') {
                $imdbVotes[$c->imdbId][$c->provider] = true;
                $addToPool($c->imdbId, [
                    'tmdb'  => $c->tmdbId,
                    'tvdb'  => $c->tvdbId,
                    'title' => $c->title,
                    'year'  => $c->year,
                ], $c->score);
            }
        }

        // by-id cross-verification: a fuzzy title search can bury the right
        // entry when regional licensing splits a title across databases. For
        // each strong lead, ask every provider DIRECTLY by IMDB id — an
        // exact-id hit is a real, unranked confirmation.
        if (!$this->hasConsensus($imdbVotes)) {
            foreach ($this->strongLeads($valid, $pool) as $imdbId) {
                $voters = $imdbVotes[$imdbId] ?? [];

                if (!isset($voters['omdb'])) {
                    $rec = $this->imdb->byId($imdbId);

                    if ($rec !== null) {
                        $imdbVotes[$imdbId]['omdb'] = true;
                        $addToPool($imdbId, $rec, 0.0);
                    }
                }

                if (!isset($voters['tmdb'])) {
                    $rec = $this->tmdb->verifyImdb($imdbId);

                    if ($rec !== null) {
                        $imdbVotes[$imdbId]['tmdb'] = true;
                        $addToPool($imdbId, $rec, 0.0);
                    }
                }

                if (!isset($voters['tvmaze'])) {
                    $rec = $this->tvmaze->byImdb($imdbId);

                    if ($rec !== null) {
                        $imdbVotes[$imdbId]['tvmaze'] = true;
                        $addToPool($imdbId, $rec, 0.0);
                    }
                }
            }
        }

        $imdbN = 0;
        $resTmdb = 0;
        $resTvdb = 0;
        $resImdb = '';
        $resTitle = $title;
        $resYear = $year;

        if ($imdbVotes !== []) {
            $winner = '';
            $best = [-1, -1.0];

            foreach ($imdbVotes as $id => $voters) {
                $rank = [\count($voters), $pool[$id]['score'] ?? 0.0];

                if ($rank > $best) {
                    $best = $rank;
                    $winner = (string) $id;
                }
            }

            $imdbN = \count($imdbVotes[$winner]);
            $merged = $pool[$winner];
            $resImdb = $winner;
            $resTmdb = $merged['tmdb'];
            $resTvdb = $merged['tvdb'];
            $resTitle = $merged['title'] !== '' ? $merged['title'] : $title;
            $resYear = $merged['year'] ?? $year;

            // backfill missing tmdb id from the agreed imdb id
            if (!$resTmdb) {
                $rec = $this->tmdb->verifyImdb($winner);

                if ($rec !== null) {
                    $resTmdb = $rec['tmdb'];
                    $category = $rec['category'] ?: $category;
                }
            }
        }

        // ── consensus 2: vote on the MAL id (anime providers) ──
        /** @var array<int,array<string,true>> $malVotes */
        $malVotes = [];
        /** @var array<int,float> $malScore */
        $malScore = [];

        foreach ($valid as $c) {
            if (\in_array($c->provider, self::MAL_PROVIDERS, true) && $c->malId) {
                $malVotes[$c->malId][$c->provider] = true;
                $malScore[$c->malId] = max($malScore[$c->malId] ?? 0.0, $c->score);
            }
        }

        $malN = 0;
        $resMal = 0;

        if ($malVotes !== []) {
            $mwin = 0;
            $best = [-1, -1.0];

            foreach ($malVotes as $id => $voters) {
                $rank = [\count($voters), $malScore[$id] ?? 0.0];

                if ($rank > $best) {
                    $best = $rank;
                    $mwin = $id;
                }
            }

            $malN = \count($malVotes[$mwin]);
            $resMal = $mwin;
        }

        // ── overall confidence: best of the two consensus tracks ──
        $bestN = max($imdbN, $malN);
        $confidence = match (true) {
            $bestN >= self::CONSENSUS_VOTES => 'high',
            $bestN === 1                    => 'low',
            default                         => 'none',
        };

        $log(sprintf(
            "resolved '%s' (%s) -> %s imdb=%s tmdb=%d tvdb=%d mal=%d votes=%d/%d",
            $title,
            $year ?? '',
            $confidence,
            $resImdb !== '' ? $resImdb : '-',
            $resTmdb,
            $resTvdb,
            $resMal,
            $imdbN,
            $malN,
        ));

        return new ResolvedIds(
            tmdbId: $resTmdb,
            tvdbId: $resTvdb,
            imdbId: $resImdb,
            malId: $resMal,
            category: $category,
            title: $resTitle,
            year: $resYear,
            confidence: $confidence,
            votes: $imdbN,
            malVotes: $malN,
            detail: $detail,
        );
    }

    /**
     * @param list<string> $aliases
     *
     * @return list<string>
     */
    private function buildQueries(string $title, ?int $year, array $aliases): array
    {
        $queries = [];

        $add = static function (?string $q) use (&$queries): void {
            $q = trim(preg_replace('/\s+/', ' ', (string) $q) ?? '');

            if ($q === '') {
                return;
            }

            foreach ($queries as $existing) {
                if (strcasecmp($existing, $q) === 0) {
                    return;
                }
            }

            $queries[] = $q;
        };

        $add($title);

        if ($year) {
            $add("{$title} {$year}");
        }

        foreach ($aliases as $a) {
            $add($a);
        }

        return $queries;
    }

    /**
     * @param list<string> $queries
     *
     * @return list<Candidate>
     */
    private function gather(array $queries, ?int $year, string $cat, bool $malHint): array
    {
        $cands = [];

        foreach ($queries as $q) {
            $cands = array_merge($cands, $this->tmdb->search($q, $year, $cat));
            $cands = array_merge($cands, $this->imdb->search($q, $year, $cat));
            $cands = array_merge($cands, $this->tvmaze->search($q, $cat));

            if ($malHint) {
                $cands = array_merge($cands, $this->mal->search($q));
                $cands = array_merge($cands, $this->anilist->search($q));
            }
        }

        return $cands;
    }

    /**
     * @param list<string> $queries
     */
    private function bestScore(Candidate $c, array $queries, ?int $year): float
    {
        $best = 0.0;

        foreach ($queries as $q) {
            $best = max($best, Normalize::score($q, $year, $c->title, $c->year));
        }

        return $best;
    }

    /**
     * @param list<Candidate> $cands
     *
     * @return list<Candidate>
     */
    private function dedupe(array $cands): array
    {
        $seen = [];

        foreach ($cands as $c) {
            $key = implode('|', [
                $c->provider,
                $c->imdbId,
                $c->tmdbId,
                $c->tvdbId,
                $c->malId,
                Normalize::text($c->title),
            ]);

            if (!isset($seen[$key]) || $c->score > $seen[$key]->score) {
                $seen[$key] = $c;
            }
        }

        return array_values($seen);
    }

    /**
     * @param array<string,array<string,true>> $imdbVotes
     */
    private function hasConsensus(array $imdbVotes): bool
    {
        foreach ($imdbVotes as $voters) {
            if (\count($voters) >= self::CONSENSUS_VOTES) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strong-lead IMDB ids worth a direct by-id check, best score first,
     * capped at 3 to bound API calls.
     *
     * @param list<Candidate>                                                   $valid
     * @param array<string,array{tmdb:int,tvdb:int,title:string,year:?int,score:float}> $pool
     *
     * @return list<string>
     */
    private function strongLeads(array $valid, array $pool): array
    {
        $leads = [];

        foreach ($valid as $c) {
            if ($c->imdbId !== '' && $c->score >= self::STRONG_LEAD) {
                $leads[$c->imdbId] = true;
            }
        }

        $keys = array_keys($leads);
        usort(
            $keys,
            static fn (string $a, string $b): int => ($pool[$b]['score'] ?? 0.0) <=> ($pool[$a]['score'] ?? 0.0),
        );

        return array_slice($keys, 0, 3);
    }
}
