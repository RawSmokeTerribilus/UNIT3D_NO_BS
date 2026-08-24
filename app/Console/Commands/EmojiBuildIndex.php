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

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Builds the emoji index the BBCode editor's picker fetches.
 *
 * joypixels/assets ships emoji.json: 3991 entries, ~2 MB, carrying far more
 * than a picker needs (diversity trees, gender variants, ascii aliases, code
 * point families). Shipping that to every browser is wasteful, and parsing it
 * per request is worse, so it gets reduced once — here — to a static file.
 *
 * Runs from composer's post-autoload-dump alongside the joypixels asset
 * publish, so a plain `composer install` leaves a deployment consistent
 * without anyone remembering an extra step.
 */
final class EmojiBuildIndex extends Command
{
    protected $signature = 'emoji:build-index
        {--force : Rewrite even when the output is newer than the source}';

    protected $description = 'Build the trimmed emoji index used by the BBCode editor picker';

    /**
     * Order the picker renders its tabs in, mapped to the category keys
     * joypixels uses. Anything not listed is dropped: "modifier" holds the
     * five skin-tone swatches, which are applied to other emoji rather than
     * inserted on their own.
     */
    private const CATEGORIES = [
        'people'   => 'Smileys & People',
        'nature'   => 'Animals & Nature',
        'food'     => 'Food & Drink',
        'activity' => 'Activity',
        'travel'   => 'Travel & Places',
        'objects'  => 'Objects',
        'symbols'  => 'Symbols',
        'flags'    => 'Flags',
        'regional' => 'Regional',
    ];

    public function handle(): int
    {
        $source = base_path('vendor/joypixels/assets/emoji.json');
        $target = public_path('vendor/joypixels/emoji-index.json');

        if (!is_file($source)) {
            $this->error('Not found: '.$source.' — is joypixels/assets installed?');

            return self::FAILURE;
        }

        if (!$this->option('force') && is_file($target) && filemtime($target) >= filemtime($source)) {
            $this->info('Emoji index is already up to date.');

            return self::SUCCESS;
        }

        $decoded = json_decode((string) file_get_contents($source), true);

        if (!\is_array($decoded)) {
            $this->error('Could not decode '.$source);

            return self::FAILURE;
        }

        $emoji = [];

        foreach ($decoded as $codepoint => $meta) {
            if (!\is_array($meta) || ($meta['display'] ?? 0) !== 1) {
                continue;
            }

            $category = $meta['category'] ?? '';

            if (!isset(self::CATEGORIES[$category])) {
                continue;
            }

            // Skin-tone and gender variants hang off a base emoji. Listing them
            // separately would triple the grid with near-identical thumbnails.
            if (!empty($meta['diversity']) || !empty($meta['gender_parent'])) {
                continue;
            }

            $shortname = trim((string) ($meta['shortname'] ?? ''), ':');

            if ($shortname === '') {
                continue;
            }

            $emoji[] = [
                (string) $codepoint,
                $shortname,
                $category,
                (int) ($meta['order'] ?? 0),
                // Keywords drive the search box. Six is plenty to match on and
                // keeps the payload small; the shortname itself is searched too.
                array_values(\array_slice((array) ($meta['keywords'] ?? []), 0, 6)),
            ];
        }

        usort($emoji, static fn (array $a, array $b): int => $a[3] <=> $b[3]);

        $payload = [
            'built_at'   => now()->toIso8601String(),
            'categories' => collect(self::CATEGORIES)
                ->map(static fn (string $label, string $id): array => ['id' => $id, 'label' => $label])
                ->values()
                ->all(),
            'emoji' => $emoji,
        ];

        $dir = \dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $this->error('Could not create '.$dir);

            return self::FAILURE;
        }

        file_put_contents($target, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info(\sprintf(
            'Emoji index written: %d emoji, %d categories, %.1f KB -> %s',
            \count($emoji),
            \count(self::CATEGORIES),
            filesize($target) / 1024,
            $target
        ));

        return self::SUCCESS;
    }
}
