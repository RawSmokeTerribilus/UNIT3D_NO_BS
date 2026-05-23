<?php

declare(strict_types=1);

namespace App\Services\Metadata\Support;

/**
 * Text and id normalisation helpers for the metadata consensus resolver.
 *
 * Port of the module-level helpers in RawLoadrr's id_resolver.py — kept as a
 * stateless utility so the resolver and its provider clients share one
 * definition of "the same title" and "the same id".
 */
final class Normalize
{
    /**
     * Lowercase, drop punctuation, collapse whitespace.
     */
    public static function text(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }

        $s = preg_replace('/[^0-9a-z]+/', ' ', mb_strtolower($s)) ?? '';

        return trim(preg_replace('/\s+/', ' ', $s) ?? '');
    }

    /**
     * @return list<string>
     */
    public static function tokens(?string $s): array
    {
        $norm = self::text($s);

        return $norm === '' ? [] : explode(' ', $norm);
    }

    /**
     * Normalise any IMDB id form to 'ttNNNNNNN', or '' when absent.
     */
    public static function imdbId(mixed $v): string
    {
        if ($v === null || $v === '' || $v === 0) {
            return '';
        }

        $v = trim((string) $v);

        if ($v === '' || \in_array($v, ['0', 'tt0', 'None'], true)) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $v) ?? '';

        if ($digits === '' || (int) $digits === 0) {
            return '';
        }

        return 'tt'.$digits;
    }

    /**
     * Coerce any value to a positive int, or 0.
     */
    public static function toInt(mixed $v): int
    {
        if (\is_int($v)) {
            return $v > 0 ? $v : 0;
        }

        if ($v === null) {
            return 0;
        }

        $s = trim((string) $v);

        if ($s === '' || preg_match('/^-?\d+$/', $s) !== 1) {
            return 0;
        }

        $n = (int) $s;

        return $n > 0 ? $n : 0;
    }

    /**
     * 0..1 title similarity, with a containment boost: when one title is a
     * token-subset of the other (e.g. "Disaster" inside "Disaster 2026", a
     * guessit-trimmed name) it is treated as a strong match, not a partial.
     */
    public static function titleScore(string $query, string $candidate): float
    {
        $q = self::text($query);
        $c = self::text($candidate);

        if ($q === '' || $c === '') {
            return 0.0;
        }

        if ($q === $c) {
            return 1.0;
        }

        similar_text($q, $c, $pct);
        $ratio = $pct / 100;

        $qt = self::tokens($query);
        $ct = self::tokens($candidate);

        if ($qt !== [] && $ct !== []) {
            $qSet = array_unique($qt);
            $cSet = array_unique($ct);

            if (array_diff($qSet, $cSet) === [] || array_diff($cSet, $qSet) === []) {
                $ratio = max($ratio, 0.88);
            }
        }

        return $ratio;
    }

    /**
     * Combined title + year score, clamped to 0..1. A one-year drift is
     * tolerated (release vs air year); a wider gap is penalised.
     */
    public static function score(string $qTitle, ?int $qYear, string $cTitle, ?int $cYear): float
    {
        $score = self::titleScore($qTitle, $cTitle);

        if ($qYear && $cYear) {
            $diff = abs($qYear - $cYear);

            if ($diff === 0) {
                $score += 0.05;
            } elseif ($diff > 1) {
                $score -= 0.30;
            }
        }

        return max(0.0, min(1.0, $score));
    }
}
