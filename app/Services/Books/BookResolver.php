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

namespace App\Services\Books;

use App\Services\Books\Support\BookCandidate;
use App\Services\Books\Support\BookResolution;
use App\Services\Books\Support\Isbn;
use App\Services\Metadata\Support\Normalize;
use Closure;

/**
 * Decides which edition an e-book upload is.
 *
 * Unlike the video resolver this does not take a vote: there is only one
 * provider that can identify a book here. OpenLibrary was measured on
 * 2026-08-20 and does not cover the Spanish catalogue — 404 by ISBN, wrong
 * books by title — so it enriches and never votes. Confidence therefore comes
 * from the score and from how clearly the best candidate beats the next one.
 *
 * That second guard matters more than it looks. A popular book has half a
 * dozen editions with identical titles, and they all score 1.0. Picking one
 * of those without asking would silently attach the wrong edition to a
 * torrent, so an ambiguous top pair is deliberately demoted to "low" and put
 * in front of a human.
 */
final class BookResolver
{
    /**
     * A hit below this is not worth showing at all.
     */
    private const MIN_CANDIDATE_SCORE = 0.50;

    /**
     * At or above this, and clearly ahead of the runner-up, the match is
     * applied without asking.
     */
    private const TRUST_SCORE = 0.90;

    /**
     * How far ahead of the second candidate the winner has to be. Two
     * editions of the same book tie exactly, so anything under this is
     * treated as ambiguous rather than as a win.
     */
    private const LEAD_MARGIN = 0.05;

    public function __construct(
        private readonly GoogleBooksClient $google,
        private readonly OpenLibraryClient $openLibrary,
    ) {
    }

    /**
     * @param ?Closure(string): void $log
     */
    public function resolve(
        string $title,
        ?string $author = null,
        ?int $year = null,
        ?string $isbnHint = null,
        ?Closure $log = null,
    ): BookResolution {
        $log ??= static fn (string $m) => null;

        // An ISBN typed by the uploader short-circuits everything: it is a
        // stated fact, not a guess.
        $hint = Isbn::toIsbn13($isbnHint ?? '');

        if ($hint !== '') {
            $candidate = $this->google->byIsbn($hint);

            if ($candidate !== null) {
                $log("isbn hint {$hint} confirmed by google books");

                return new BookResolution('high', 1.0, $candidate, [$candidate], 'isbn supplied by uploader');
            }

            $log("isbn hint {$hint} not found upstream");
        }

        if (!$this->google->isEnabled()) {
            return new BookResolution('none', 0.0, null, [], 'google books unavailable');
        }

        $candidates = $this->google->search($title, $author, 10);

        foreach ($candidates as $candidate) {
            $candidate->score = $this->scoreOf($candidate, $title, $author, $year);
        }

        $candidates = array_values(array_filter(
            $candidates,
            static fn (BookCandidate $c): bool => $c->score >= self::MIN_CANDIDATE_SCORE
        ));

        usort($candidates, static fn (BookCandidate $a, BookCandidate $b): int => $b->score <=> $a->score);

        if ($candidates === []) {
            $log("no book candidate above threshold for '{$title}'");

            return new BookResolution('none', 0.0, null, [], 'no candidate scored high enough');
        }

        $best = $candidates[0];
        $runnerUp = $candidates[1] ?? null;
        $lead = $runnerUp === null ? 1.0 : $best->score - $runnerUp->score;

        if ($best->score >= self::TRUST_SCORE && $lead >= self::LEAD_MARGIN) {
            $confidence = 'high';
            $reason = 'clear single match';
        } else {
            $confidence = 'low';
            $reason = $best->score < self::TRUST_SCORE
                ? 'best candidate below trust score'
                : 'several editions score the same; a human picks the edition';
        }

        $log(\sprintf(
            "resolved '%s' -> %s isbn13=%s score=%.3f lead=%.3f (%s)",
            $title,
            $confidence,
            $best->isbn13,
            $best->score,
            $lead,
            $reason
        ));

        return new BookResolution($confidence, $best->score, $best, $candidates, $reason);
    }

    /**
     * Fields OpenLibrary can add to an edition Google Books already named.
     *
     * @return array{olid?: string, subjects?: list<string>, description?: string, page_count?: int, cover_url?: string}
     */
    public function enrich(string $isbn13): array
    {
        return $this->openLibrary->enrich($isbn13);
    }

    /**
     * Title score, with a small bonus when the author also lines up. Release
     * names are usually "Author - Title", so the author is a real signal and
     * not just decoration.
     */
    private function scoreOf(BookCandidate $candidate, string $title, ?string $author, ?int $year): float
    {
        $score = Normalize::score($title, $year, $candidate->title, $candidate->year);

        // The subtitle often carries the series ("Cronica del asesino de reyes
        // 1"), which is how a query naming the series still matches.
        if ($candidate->subtitle !== '') {
            $withSubtitle = trim($candidate->title.' '.$candidate->subtitle);
            $score = max($score, Normalize::score($title, $year, $withSubtitle, $candidate->year));
        }

        if ($author !== null && trim($author) !== '' && $candidate->authors !== []) {
            $authorScore = 0.0;

            foreach ($candidate->authors as $candidateAuthor) {
                $authorScore = max($authorScore, Normalize::titleScore($author, $candidateAuthor));
            }

            if ($authorScore >= 0.85) {
                $score += 0.05;
            }
        }

        return max(0.0, min(1.0, $score));
    }
}
