<?php

declare(strict_types=1);

namespace App\Services\Books\Support;

/**
 * What the book resolver decided, and how sure it is.
 *
 * `confidence` follows the same vocabulary as the video resolver so the
 * upload flow and the staff tooling can treat both the same way:
 *   high — apply without asking
 *   low  — show it, let a human confirm
 *   none — nothing usable, fall back to the manual path
 */
final class BookResolution
{
    /**
     * @param list<BookCandidate> $candidates ranked best first, for the detail view
     */
    public function __construct(
        public readonly string $confidence,
        public readonly float $score,
        public readonly ?BookCandidate $best,
        public readonly array $candidates = [],
        public readonly string $reason = '',
    ) {
    }

    public function isTrusted(): bool
    {
        return $this->confidence === 'high' && $this->best !== null;
    }

    public function isbn13(): string
    {
        return $this->best?->isbn13 ?? '';
    }
}
