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

namespace App\Services\Audiobooks;

use App\Services\Metadata\Support\Normalize;
use Closure;

/**
 * Decides which recording an audiobook upload is.
 *
 * Chains the two providers: Audible answers "which ASINs look like this
 * title", Audnexus answers "what is this ASIN". Scoring happens between the
 * two, so only the winner costs a second request.
 *
 * Audible's own relevance ordering is not trusted. Measured on 2026-08-20,
 * a search for "El nombre del viento" returned the right recording third,
 * behind two unrelated books that merely share words. The scoring here is the
 * same Normalize the video and book resolvers use, so "the same title" means
 * one thing across the whole tracker.
 */
final class AudiobookResolver
{
    private const MIN_CANDIDATE_SCORE = 0.50;

    private const TRUST_SCORE = 0.90;

    /**
     * How far ahead of the runner-up the winner must be. Unlike books, two
     * recordings of one title are usually different narrators rather than
     * reprints, so a tie is a genuine question for a human.
     */
    private const LEAD_MARGIN = 0.05;

    public function __construct(
        private readonly AudibleClient $audible,
        private readonly AudnexusClient $audnexus,
    ) {
    }

    /**
     * @param ?Closure(string): void $log
     *
     * @return array{confidence: string, score: float, asin: string, record: array<string, mixed>|null, reason: string}
     */
    public function resolve(
        string $title,
        ?string $author = null,
        string $region = 'es',
        ?string $asinHint = null,
        ?Closure $log = null,
    ): array {
        $log ??= static fn (string $m) => null;

        // An ASIN typed by the uploader is a stated fact; confirm it and stop.
        $hint = strtoupper(trim($asinHint ?? ''));

        if ($hint !== '') {
            $record = $this->audnexus->book($hint, $region);

            if ($record !== null) {
                $log("asin hint {$hint} confirmed by audnexus");

                return $this->result('high', 1.0, $hint, $record, 'asin supplied by uploader');
            }

            $log("asin hint {$hint} unknown to audnexus in region {$region}");
        }

        $products = $this->audible->search($title, $author, $region);

        if ($products === []) {
            return $this->result('none', 0.0, '', null, 'no audible product matched');
        }

        $scored = [];

        foreach ($products as $product) {
            $score = Normalize::titleScore($title, $product['title']);

            if ($product['subtitle'] !== '') {
                $score = max($score, Normalize::titleScore($title, trim($product['title'].' '.$product['subtitle'])));
            }

            if ($author !== null && trim($author) !== '' && $product['authors'] !== []) {
                $authorScore = 0.0;

                foreach ($product['authors'] as $candidateAuthor) {
                    $authorScore = max($authorScore, Normalize::titleScore($author, $candidateAuthor));
                }

                if ($authorScore >= 0.85) {
                    $score += 0.05;
                }
            }

            $score = max(0.0, min(1.0, $score));

            if ($score >= self::MIN_CANDIDATE_SCORE) {
                $scored[] = ['score' => $score] + $product;
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        if ($scored === []) {
            $log("no audiobook candidate above threshold for '{$title}'");

            return $this->result('none', 0.0, '', null, 'no candidate scored high enough');
        }

        $best = $scored[0];
        $lead = isset($scored[1]) ? $best['score'] - $scored[1]['score'] : 1.0;

        // Only the winner is worth a second round trip.
        $record = $this->audnexus->book($best['asin'], $region);

        if ($record === null) {
            $log("audnexus has no record for {$best['asin']} in region {$region}");

            return $this->result('none', $best['score'], $best['asin'], null, 'audnexus has no record for the best match');
        }

        if ($best['score'] >= self::TRUST_SCORE && $lead >= self::LEAD_MARGIN) {
            $confidence = 'high';
            $reason = 'clear single match';
        } else {
            $confidence = 'low';
            $reason = $best['score'] < self::TRUST_SCORE
                ? 'best candidate below trust score'
                : 'several recordings score the same; a human picks the narrator';
        }

        $log(\sprintf(
            "resolved audiobook '%s' -> %s asin=%s score=%.3f lead=%.3f (%s)",
            $title,
            $confidence,
            $best['asin'],
            $best['score'],
            $lead,
            $reason
        ));

        return $this->result($confidence, $best['score'], $best['asin'], $record, $reason);
    }

    /**
     * @param array<string, mixed>|null $record
     *
     * @return array{confidence: string, score: float, asin: string, record: array<string, mixed>|null, reason: string}
     */
    private function result(string $confidence, float $score, string $asin, ?array $record, string $reason): array
    {
        return [
            'confidence' => $confidence,
            'score'      => $score,
            'asin'       => $asin,
            'record'     => $record,
            'reason'     => $reason,
        ];
    }
}
