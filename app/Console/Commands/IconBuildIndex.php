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

        $icons = [];

        foreach ($seen as $name => $codepoint) {
            $icons[] = [(string) $name, $codepoint];
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
            ['icons' => $icons],
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
}
