<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Descarga portadas (Named_Boxarts) desde el repo libretro-thumbnails de
 * GitHub al directorio público local.  No se hace HEAD por cada miss para
 * no martillear: si la portada no existe en el repo simplemente se queda
 * sin cover y la card cae al icono del sistema.
 *
 * Uso típico una vez por scan:
 *   php artisan retroarch:scan-roms
 *   php artisan retroarch:fetch-covers
 *   php artisan retroarch:scan-roms   # 2da pasada → catalog.json registra los .png nuevos
 */
class RetroArchFetchCovers extends Command
{
    protected $signature = 'retroarch:fetch-covers
        {--system= : Sólo un sistema (snes, nes, ...)}
        {--force : Reintentar incluso ROMs ya marcados como sin cover}';

    protected $description = 'Descarga portadas Named_Boxarts de libretro-thumbnails para cada ROM en disco.';

    /**
     * sistema → nombre del repo libretro-thumbnails (rama master).
     */
    private const REPO_MAP = [
        'snes'    => 'libretro-thumbnails/Nintendo_-_Super_Nintendo_Entertainment_System',
        'nes'     => 'libretro-thumbnails/Nintendo_-_Nintendo_Entertainment_System',
        'fds'     => 'libretro-thumbnails/Nintendo_-_Family_Computer_Disk_System',
        'gb'      => 'libretro-thumbnails/Nintendo_-_Game_Boy',
        'gbc'     => 'libretro-thumbnails/Nintendo_-_Game_Boy_Color',
        'gba'     => 'libretro-thumbnails/Nintendo_-_Game_Boy_Advance',
        'genesis' => 'libretro-thumbnails/Sega_-_Mega_Drive_-_Genesis',
        'sms'     => 'libretro-thumbnails/Sega_-_Master_System_-_Mark_III',
        'gg'      => 'libretro-thumbnails/Sega_-_Game_Gear',
        'pce'     => 'libretro-thumbnails/NEC_-_PC_Engine_-_TurboGrafx_16',
        'arcade'  => 'libretro-thumbnails/MAME',
    ];

    public function handle(): int
    {
        $only = $this->option('system');
        $force = (bool) $this->option('force');
        $romsRoot = public_path('retroarch/assets/cores/roms');
        $coversRoot = public_path('retroarch/covers');

        if (! is_dir($coversRoot) && ! mkdir($coversRoot, 0775, true) && ! is_dir($coversRoot)) {
            $this->error("No pude crear {$coversRoot}");

            return self::FAILURE;
        }

        $totalHits = 0;
        $totalMisses = 0;

        foreach (self::REPO_MAP as $system => $repo) {
            if ($only !== null && $only !== $system) {
                continue;
            }

            $sysDir = $romsRoot.'/'.$system;
            if (! is_dir($sysDir)) {
                continue;
            }

            $sysCoverDir = $coversRoot.'/'.$system;
            if (! is_dir($sysCoverDir)) {
                mkdir($sysCoverDir, 0775, true);
            }

            $missMarker = $sysCoverDir.'/.misses';
            $misses = is_file($missMarker) && ! $force
                ? array_flip(array_filter(explode("\n", (string) file_get_contents($missMarker))))
                : [];

            $files = array_values(array_filter(
                scandir($sysDir) ?: [],
                fn ($f) => $f !== '.' && $f !== '..' && is_file($sysDir.'/'.$f),
            ));

            $sysHits = 0;
            $sysMisses = 0;

            foreach ($files as $file) {
                $stem = pathinfo($file, PATHINFO_FILENAME);
                $dest = $sysCoverDir.'/'.$stem.'.png';

                if (is_file($dest)) {
                    continue;
                }
                if (isset($misses[$stem])) {
                    continue;
                }

                $url = sprintf(
                    'https://raw.githubusercontent.com/%s/master/Named_Boxarts/%s.png',
                    $repo,
                    rawurlencode($stem),
                );

                // cURL → stream directo a disco. Evita acumulación en memoria
                // que tumbó el comando con OOM cuando usábamos Http::get()
                // (Guzzle retiene response bodies en su pool interno).
                $out = @fopen($dest, 'wb');
                if ($out === false) {
                    $this->warn("No pude crear {$dest}");
                    continue;
                }

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_FILE           => $out,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_FAILONERROR    => false,
                    CURLOPT_USERAGENT      => 'UNIT3D-RetroArch-CoverFetch/1.0',
                ]);
                curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($out);

                if ($status === 200 && filesize($dest) > 0) {
                    $sysHits++;
                } else {
                    @unlink($dest);
                    $misses[$stem] = true;
                    $sysMisses++;
                }

                // Persistencia periódica: si el proceso muere a mitad de un
                // sistema grande (snes 313, arcade 514), no perdemos lo que
                // ya sabemos que es miss.  Cada 50 itrs es un buen balance.
                if (($sysHits + $sysMisses) % 50 === 0) {
                    file_put_contents($missMarker, implode("\n", array_keys($misses)));
                }

                usleep(80000); // ~12 req/s ceiling
            }

            // Persistir misses para no reintentar en próxima pasada (salvo --force).
            file_put_contents($missMarker, implode("\n", array_keys($misses)));

            $this->line(sprintf('[%-8s] +%d covers, %d misses', $system, $sysHits, $sysMisses));
            $totalHits += $sysHits;
            $totalMisses += $sysMisses;
        }

        $this->info("Total: +{$totalHits} portadas, {$totalMisses} sin match.");

        return self::SUCCESS;
    }
}
