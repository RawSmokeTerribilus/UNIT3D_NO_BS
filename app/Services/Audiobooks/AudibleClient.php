<?php

declare(strict_types=1);

namespace App\Services\Audiobooks;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Audible catalogue search — the only job here is turning a title into ASINs.
 *
 * Audnexus, which holds the metadata worth having, is addressable by ASIN and
 * nothing else, so something has to do the lookup. This is the same two-step
 * Audiobookshelf uses.
 *
 * Two things were measured against the live API on 2026-08-20 and both shape
 * this class:
 *
 *  - Without `response_groups` every product comes back with a null title,
 *    leaving nothing to score. It is not optional.
 *  - Ordering by Relevance put the correct book *third*, behind two unrelated
 *    ones. Taking results[0] would be the "Disaster 2026" mistake again, so
 *    this returns the whole list and lets the resolver score it.
 *
 * The marketplace matters: a Spanish audiobook lives on api.audible.es and is
 * absent from .com.
 */
final class AudibleClient
{
    private const TIMEOUT = 12;

    private const FAILURE_THRESHOLD = 8;

    /**
     * Audible marketplace domains, keyed by the region code Audnexus uses.
     *
     * @var array<string, string>
     */
    private const DOMAINS = [
        'es' => 'api.audible.es',
        'us' => 'api.audible.com',
        'uk' => 'api.audible.co.uk',
        'fr' => 'api.audible.fr',
        'de' => 'api.audible.de',
        'it' => 'api.audible.it',
        'ca' => 'api.audible.ca',
        'au' => 'api.audible.com.au',
        'br' => 'api.audible.com.br',
        'jp' => 'api.audible.co.jp',
        'in' => 'api.audible.in',
    ];

    private int $failures = 0;

    public function isEnabled(): bool
    {
        return $this->failures < self::FAILURE_THRESHOLD;
    }

    public static function isKnownRegion(string $region): bool
    {
        return isset(self::DOMAINS[strtolower($region)]);
    }

    /**
     * The marketplace to search by default, derived from the site's metadata
     * locale rather than hardcoded. Falls back to 'us' when the locale names
     * a country Audible does not sell in.
     */
    public static function defaultRegion(): string
    {
        $region = strtolower(substr((string) config('app.meta_locale', 'es_ES'), 0, 2));

        return self::isKnownRegion($region) ? $region : 'us';
    }

    /**
     * Search one marketplace. Returns raw products; scoring is the caller's job.
     *
     * @return list<array{asin: string, title: string, subtitle: string, authors: list<string>, narrators: list<string>}>
     */
    public function search(string $title, ?string $author = null, string $region = 'es', int $limit = 10): array
    {
        $domain = self::DOMAINS[strtolower($region)] ?? null;

        if (!$this->isEnabled() || $domain === null || trim($title) === '') {
            return [];
        }

        $params = [
            'title'            => $title,
            'num_results'      => max(1, min(50, $limit)),
            'products_sort_by' => 'Relevance',
            'response_groups'  => 'product_desc,contributors,product_attrs',
        ];

        if ($author !== null && trim($author) !== '') {
            $params['author'] = $author;
        }

        try {
            $json = Http::timeout(self::TIMEOUT)
                ->get('https://'.$domain.'/1.0/catalog/products', $params)
                ->throw()
                ->json();

            $this->failures = 0;
        } catch (Throwable $e) {
            $this->failures++;
            Log::warning('audible.search failed: '.$e->getMessage());

            return [];
        }

        $out = [];

        foreach ((\is_array($json) ? $json['products'] ?? [] : []) as $product) {
            $asin = (string) ($product['asin'] ?? '');

            if ($asin === '') {
                continue;
            }

            $out[] = [
                'asin'      => $asin,
                'title'     => (string) ($product['title'] ?? ''),
                'subtitle'  => (string) ($product['subtitle'] ?? ''),
                'authors'   => $this->names($product['authors'] ?? []),
                'narrators' => $this->names($product['narrators'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * @param mixed $people
     *
     * @return list<string>
     */
    private function names(mixed $people): array
    {
        if (!\is_array($people)) {
            return [];
        }

        $names = [];

        foreach ($people as $person) {
            $name = \is_array($person) ? ($person['name'] ?? null) : null;

            if (\is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
