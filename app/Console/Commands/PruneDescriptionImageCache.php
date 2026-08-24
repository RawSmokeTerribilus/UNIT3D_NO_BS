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
 * Mantiene la caché del proxy de imágenes de descripción por debajo de un tope.
 *
 * Por qué hace falta: el proxy guarda en disco cada imagen que sirve y nunca
 * borraba nada. Medido el 2026-08-23, sólo las capturas de ptscreens que hay
 * en descripciones son **19.862 imágenes de ~2 MB**, o sea ~40 GB si se
 * llegasen a ver todas. Cabe en el disco de hoy, pero una caché sin techo es
 * una bomba de relojería con la fecha sin poner.
 *
 * Se borra lo MENOS usado, no lo más antiguo. La diferencia importa: una
 * captura de un torrent de 2019 que la gente sigue abriendo debe sobrevivir a
 * una de ayer que nadie volvió a mirar.
 *
 * Cómo se sabe qué se usó, con `noatime`: este volumen está montado con
 * `noatime`, así que el sistema NO actualiza la fecha de acceso al leer y
 * ordenar por atime daría un orden falso. El proxy marca el fichero al
 * servirlo desde caché (ver DescriptionImageProxyController::marcarUso), y
 * aquí se ordena por esa marca.
 */
class PruneDescriptionImageCache extends Command
{
    protected $signature = 'images:prune-description-cache
                            {--cap= : Tope en GB. Por defecto, images.description_cache_cap_gb}
                            {--dry-run : Sólo dice qué borraría}';

    protected $description = 'Poda la caché del proxy de imágenes de descripción hasta dejarla bajo su tope';

    final public function handle(): int
    {
        $dir = storage_path('app/desc-proxy');

        if (!is_dir($dir)) {
            $this->info('No hay caché que podar.');

            return self::SUCCESS;
        }

        $topeGb = (float) ($this->option('cap') ?? config('unit3d.description_cache_cap_gb', 10));
        $tope = (int) ($topeGb * 1024 ** 3);
        $seco = (bool) $this->option('dry-run');

        /** @var list<array{ruta: string, bytes: int, usado: int}> $ficheros */
        $ficheros = [];
        $total = 0;
        $marcasCaducadas = 0;

        foreach (scandir($dir) ?: [] as $nombre) {
            if ($nombre === '.' || $nombre === '..') {
                continue;
            }

            $ruta = $dir.'/'.$nombre;

            if (!is_file($ruta)) {
                continue;
            }

            // Las marcas de fallo caducan solas al consultarlas, pero una que
            // no se vuelva a pedir se quedaría ahí para siempre. Pesan nada,
            // aun así se limpian: son ruido al inspeccionar el directorio.
            if (str_ends_with($nombre, '.fail')) {
                if (time() - (int) filemtime($ruta) > 86400) {
                    $seco || @unlink($ruta);
                    $marcasCaducadas++;
                }

                continue;
            }

            // El sidecar `.type` viaja con su imagen; no se contabiliza aparte
            // ni se ordena por su cuenta.
            if (str_ends_with($nombre, '.type')) {
                continue;
            }

            $bytes = (int) filesize($ruta);
            $total += $bytes;

            $ficheros[] = [
                'ruta'  => $ruta,
                'bytes' => $bytes,
                'usado' => (int) filemtime($ruta),
            ];
        }

        $this->line(sprintf(
            'Caché: %s en %d imágenes. Tope: %s.',
            $this->humano($total),
            \count($ficheros),
            $this->humano($tope),
        ));

        if ($marcasCaducadas > 0) {
            $this->line(sprintf('  %d marca(s) de fallo caducada(s) limpiada(s).', $marcasCaducadas));
        }

        if ($total <= $tope) {
            $this->info('Por debajo del tope: no se borra nada.');

            return self::SUCCESS;
        }

        // Menos usado primero. `usado` es la fecha de la última vez que se
        // sirvió, no la de creación.
        usort($ficheros, static fn (array $a, array $b) => $a['usado'] <=> $b['usado']);

        $liberado = 0;
        $borrados = 0;

        foreach ($ficheros as $f) {
            if ($total - $liberado <= $tope) {
                break;
            }

            if (!$seco) {
                @unlink($f['ruta']);
                @unlink($f['ruta'].'.type');
            }

            $liberado += $f['bytes'];
            $borrados++;
        }

        $this->info(sprintf(
            '%s%d imagen(es) borrada(s), %s liberados. Queda %s.',
            $seco ? '[simulacro] ' : '',
            $borrados,
            $this->humano($liberado),
            $this->humano($total - $liberado),
        ));

        return self::SUCCESS;
    }

    private function humano(int $bytes): string
    {
        // En float: dividir en enteros perdia los decimales y "1,9 GB" salia
        // como "1 GB", que en un informe de poda es justo el numero que se mira.
        $v = (float) $bytes;

        foreach (['B', 'KB', 'MB', 'GB'] as $unidad) {
            if ($v < 1024 || $unidad === 'GB') {
                return round($v, 1).' '.$unidad;
            }

            $v /= 1024;
        }

        return $bytes.' B';
    }
}
