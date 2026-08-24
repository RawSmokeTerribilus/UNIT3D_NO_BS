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
use Illuminate\Support\Str;

/**
 * Genera storage/app/retroarch/catalog.json y reescribe
 * public/retroarch/assets/cores/.index-xhr con el contenido real del disco.
 *
 * El primer fichero alimenta al RetroArchController.  El segundo lo lee
 * BrowserFS dentro del navegador para saber qué ROMs puede pedir vía XHR.
 * Si añades/quitas ROMs y no relanzas este comando, el catálogo o BrowserFS
 * quedan desincronizados.
 */
class RetroArchScanRoms extends Command
{
    protected $signature = 'retroarch:scan-roms {--dry-run : No escribe ficheros, sólo informa}';

    protected $description = 'Escanea public/retroarch/assets/cores/roms/* y regenera catalog.json + .index-xhr.';

    public function handle(): int
    {
        $romsRoot = public_path('retroarch/assets/cores/roms');
        if (! is_dir($romsRoot)) {
            $this->error("Directorio de ROMs no existe: {$romsRoot}");

            return self::FAILURE;
        }

        $configured = config('retroarch.systems', []);
        $catalog = [
            'generated_at' => now()->toIso8601String(),
            'rom_mount'    => config('retroarch.rom_mount'),
            'systems'      => [],
        ];
        $xhrIndex = ['roms' => []];
        $usedSlugs = [];

        // Mapa romset-stem → display-name para arcade. Generado desde la DAT
        // de FBNeo en libretro-database; sin él, los stems quedan en formato
        // críptico tipo "armwaru" o "aodk". Si el JSON no existe, arcade cae
        // al cleanTitle() genérico (acepta el stem tal cual).
        $arcadeNamesPath = storage_path('app/retroarch/arcade-names.json');
        $arcadeNames = [];
        if (is_file($arcadeNamesPath)) {
            $raw = (string) file_get_contents($arcadeNamesPath);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $arcadeNames = $decoded;
            }
        }

        foreach ($configured as $system => $meta) {
            $sysDir = $romsRoot.'/'.$system;
            if (! is_dir($sysDir)) {
                $this->warn("[{$system}] sin directorio en disco — saltado.");

                continue;
            }

            $files = array_values(array_filter(
                scandir($sysDir) ?: [],
                fn ($f) => $f !== '.' && $f !== '..' && is_file($sysDir.'/'.$f),
            ));
            sort($files, SORT_NATURAL | SORT_FLAG_CASE);

            $roms = [];
            $sysSlugs = [];

            foreach ($files as $file) {
                $stem = pathinfo($file, PATHINFO_FILENAME);
                if ($system === 'arcade' && isset($arcadeNames[$stem])) {
                    $title = $this->cleanTitle($arcadeNames[$stem]);
                } else {
                    $title = $this->cleanTitle($stem);
                }
                $slug = $this->uniqueSlug($stem, $sysSlugs);
                $sysSlugs[$slug] = true;

                $coverWebp = public_path('retroarch/covers/'.$system.'/'.$stem.'.webp');
                $coverPng  = public_path('retroarch/covers/'.$system.'/'.$stem.'.png');
                if (is_file($coverWebp)) {
                    $cover = '/retroarch/covers/'.rawurlencode($system).'/'.rawurlencode($stem.'.webp');
                } elseif (is_file($coverPng)) {
                    $cover = '/retroarch/covers/'.rawurlencode($system).'/'.rawurlencode($stem.'.png');
                } else {
                    $cover = null;
                }

                $roms[] = [
                    'slug'     => $slug,
                    'title'    => $title,
                    'filename' => $file,
                    'size'     => filesize($sysDir.'/'.$file) ?: 0,
                    'cover'    => $cover,
                ];

                $xhrIndex['roms'][$system][$file] = null;
            }

            $catalog['systems'][$system] = [
                'label'     => $meta['label'] ?? ucfirst($system),
                'core'      => $meta['core'],
                'icon'      => $meta['icon'] ?? null,
                'rom_count' => count($roms),
                'roms'      => $roms,
            ];

            $usedSlugs[$system] = count($sysSlugs);
            $this->line(sprintf('[%-8s] %4d ROMs → core=%s', $system, count($roms), $meta['core']));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run: no se ha escrito nada.');

            return self::SUCCESS;
        }

        // catalog.json
        $catalogPath = storage_path('app/retroarch/catalog.json');
        if (! is_dir(dirname($catalogPath))) {
            mkdir(dirname($catalogPath), 0775, true);
        }
        file_put_contents(
            $catalogPath,
            json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        $this->info("Escrito {$catalogPath}");

        // .index-xhr (BrowserFS)
        $xhrPath = public_path('retroarch/assets/cores/.index-xhr');
        file_put_contents(
            $xhrPath,
            json_encode($xhrIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        $this->info("Escrito {$xhrPath}");

        return self::SUCCESS;
    }

    /**
     * Limpia "Super Mario World (USA) [T-En by foo].zip" → "Super Mario World".
     */
    private function cleanTitle(string $stem): string
    {
        $clean = preg_replace('/\s*[\(\[].*$/u', '', $stem);

        return trim((string) $clean);
    }

    /**
     * Slug determinista, único por sistema.  Colisiones → sufijo numérico.
     *
     * @param array<string, true> $taken
     */
    private function uniqueSlug(string $stem, array $taken): string
    {
        $base = Str::slug($stem) ?: 'rom';
        $base = substr($base, 0, 80);  // no nos vamos a 200 chars de URL

        if (! isset($taken[$base])) {
            return $base;
        }

        for ($i = 2; $i < 9999; $i++) {
            $candidate = $base.'-'.$i;
            if (! isset($taken[$candidate])) {
                return $candidate;
            }
        }

        return $base.'-'.substr(sha1($stem), 0, 8);
    }
}
