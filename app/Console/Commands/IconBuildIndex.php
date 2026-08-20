<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Builds the Font Awesome icon index the staff forms' icon picker fetches.
 *
 * There is no icons.json metadata to lean on: the site ships a vendored
 * Font Awesome Pro build as one big SCSS file plus webfonts. That file is
 * therefore the ground truth of which glyphs actually render here, so the
 * index is distilled from its `.fa-name:before { content: "\fxxx" }` rules
 * once — here — into a static file the browser can fetch.
 *
 * Runs from composer's post-autoload-dump next to the emoji index build, so
 * a plain `composer install` leaves a deployment consistent without anyone
 * remembering an extra step.
 */
final class IconBuildIndex extends Command
{
    protected $signature = 'icon:build-index
        {--force : Rewrite even when the output is newer than the source}';

    protected $description = 'Build the Font Awesome icon index used by the staff icon picker';

    /**
     * Style prefixes in mask-bit order; bit i of an icon's mask says the
     * font behind styles[i] really contains its glyph.
     */
    private const STYLES = [
        'fas' => 'fa-solid-900.ttf',
        'far' => 'fa-regular-400.ttf',
        'fal' => 'fa-light-300.ttf',
        'fat' => 'fa-thin-100.ttf',
        'fad' => 'fa-duotone-900.ttf',
        'fab' => 'fa-brands-400.ttf',
    ];

    public function handle(): int
    {
        $source = resource_path('sass/vendor/_font-awesome.scss');
        $target = public_path('vendor/fontawesome/icon-index.json');

        if (!is_file($source)) {
            $this->error('Not found: '.$source.' — the vendored Font Awesome build is missing.');

            return self::FAILURE;
        }

        if (!$this->option('force') && is_file($target) && filemtime($target) >= filemtime($source)) {
            $this->info('Icon index is already up to date.');

            return self::SUCCESS;
        }

        $scss = (string) file_get_contents($source);

        // Only :before rules carry the primary glyph; duotone :after rules
        // repeat the same names and would only produce duplicates. The code
        // point travels with the name so the picker can ask the loaded font,
        // glyph by glyph via document.fonts.check(), which icons a given
        // style really covers — brands only exist in fab, and vice versa.
        preg_match_all('/\.fa-([a-z0-9-]+):before\s*\{\s*content:\s*"\\\\([0-9a-f]+)"/', $scss, $matches, PREG_SET_ORDER);

        $seen = [];

        foreach ($matches as $match) {
            $seen[$match[1]] ??= $match[2];
        }

        ksort($seen);

        // Which styles really cover each glyph is decided by the vendored
        // TTFs' cmap tables — document.fonts.check() in the browser is
        // face-level, not glyph-level, and answers differently per browser,
        // so the coverage is resolved here once, where it is deterministic.
        $coverage = [];

        foreach (self::STYLES as $style => $file) {
            $font = resource_path('sass/vendor/webfonts/font-awesome/'.$file);

            if (!is_file($font)) {
                $this->error('Not found: '.$font);

                return self::FAILURE;
            }

            $coverage[$style] = $this->cmapCodepoints($font);
        }

        $icons = [];
        $dropped = 0;

        foreach ($seen as $name => $codepoint) {
            $value = hexdec($codepoint);
            $mask = 0;
            $bit = 1;

            foreach (self::STYLES as $style => $file) {
                if (isset($coverage[$style][$value])) {
                    $mask |= $bit;
                }

                $bit <<= 1;
            }

            // A name whose glyph no shipped font contains would only ever
            // render as an empty square; it has no business in the picker.
            if ($mask === 0) {
                $dropped++;

                continue;
            }

            $icons[] = [(string) $name, $codepoint, $mask];
        }

        if ($dropped > 0) {
            $this->info($dropped.' names dropped: no shipped font contains their glyph.');
        }

        if ($icons === []) {
            $this->error('No icon rules found — has the vendored SCSS changed format?');

            return self::FAILURE;
        }

        $dir = \dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->error('Cannot create '.$dir);

            return self::FAILURE;
        }

        $json = json_encode(
            ['styles' => array_keys(self::STYLES), 'icons' => $icons],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        file_put_contents($target, $json);

        $this->info(\sprintf(
            'Icon index written: %d icons, %.1f KB -> %s',
            \count($icons),
            \strlen($json) / 1024,
            $target
        ));

        return self::SUCCESS;
    }

    /**
     * The set of code points a TrueType font's cmap covers, as a lookup
     * table (codepoint => true). Reads a format 12 subtable when present,
     * falling back to format 4 — the same tables every browser consults.
     *
     * @return array<int, true>
     */
    private function cmapCodepoints(string $path): array
    {
        $data = (string) file_get_contents($path);

        $tableCount = unpack('n', substr($data, 4, 2))[1];
        $cmapOffset = null;

        for ($i = 0; $i < $tableCount; $i++) {
            $entry = substr($data, 12 + 16 * $i, 16);

            if (substr($entry, 0, 4) === 'cmap') {
                $cmapOffset = unpack('N', substr($entry, 8, 4))[1];
            }
        }

        if ($cmapOffset === null) {
            return [];
        }

        $subtableCount = unpack('n', substr($data, $cmapOffset + 2, 2))[1];
        $best = null;

        for ($i = 0; $i < $subtableCount; $i++) {
            $record = unpack('nplatform/nencoding/Noffset', substr($data, $cmapOffset + 4 + 8 * $i, 8));
            $subtable = $cmapOffset + $record['offset'];
            $format = unpack('n', substr($data, $subtable, 2))[1];
            $pair = [$record['platform'], $record['encoding']];

            if ($format === 12 && \in_array($pair, [[3, 10], [0, 4]], true)) {
                $best = [12, $subtable];

                break;
            }

            if ($format === 4 && $best === null && \in_array($pair, [[3, 1], [0, 3]], true)) {
                $best = [4, $subtable];
            }
        }

        if ($best === null) {
            return [];
        }

        [$format, $subtable] = $best;
        $codepoints = [];

        if ($format === 12) {
            $groupCount = unpack('N', substr($data, $subtable + 12, 4))[1];

            for ($g = 0; $g < $groupCount; $g++) {
                $group = unpack('Nstart/Nend', substr($data, $subtable + 16 + 12 * $g, 8));

                for ($cp = $group['start']; $cp <= $group['end']; $cp++) {
                    $codepoints[$cp] = true;
                }
            }

            return $codepoints;
        }

        $segCountX2 = unpack('n', substr($data, $subtable + 6, 2))[1];
        $segCount = intdiv($segCountX2, 2);
        $ends = array_values(unpack('n*', substr($data, $subtable + 14, $segCountX2)));
        $starts = array_values(unpack('n*', substr($data, $subtable + 16 + $segCountX2, $segCountX2)));

        for ($i = 0; $i < $segCount; $i++) {
            if ($starts[$i] === 0xFFFF) {
                continue;
            }

            for ($cp = $starts[$i]; $cp <= $ends[$i]; $cp++) {
                $codepoints[$cp] = true;
            }
        }

        return $codepoints;
    }
}
