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

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Proyecta las promos globales del panel sobre el tracker Rust.
 *
 * Ni `other.freeleech` ni `other.doubleup` llegaban a ninguna parte: su unico
 * consumidor que cobraba era `ProcessAnnounce.php`, el announce PHP, muerto
 * desde el corte a Rust. El announce Rust calcula los factores en
 * `announce.rs:770-779`:
 *
 *     upload_factor   = max(config.upload_factor,   grupo, torrent)
 *     download_factor = min(config.download_factor, grupo, torrent)
 *
 * y `config.*_factor` sale de las variables UPLOAD_FACTOR / DOWNLOAD_FACTOR de
 * su .env — donde 0 significa freeleech global y 200 doble subida global
 * (`config.rs:44-49`). Esta clase es el cable que faltaba.
 *
 * Por que aqui y no en `groups.is_freeleech` / `groups.is_double_upload`: esas
 * columnas son perks PERMANENTES de un grupo (la UI las llama "Freeleech
 * permanente"). Escribir una promo temporal encima obligaria a recordar que
 * grupos ya las tenian para poder revertir, y un fallo al revertir les quitaria
 * el privilegio en silencio. Las variables del tracker no dejan residuo:
 * apagarlas es imposible de hacer mal.
 *
 * Los grupos sin derecho de descarga no necesitan exclusion: el announce los
 * corta antes de calcular ningun factor — `banned`/`validating`/`disabled` en
 * `announce.rs:438` y `download_slots = 0` en `announce.rs:453`.
 *
 * La verdad sigue siendo la tabla `settings`. El .env del announce es cache
 * derivada, y `sync()` es idempotente: reconcilia sola, ya se la llame al
 * guardar la configuracion o desde el comando periodico.
 */
final class TrackerPromos
{
    /**
     * .env del announce. Vive en un bind mount compartido (docker-compose.yml)
     * para que sobreviva a reinicios y para que este contenedor pueda escribirlo.
     */
    private const ENV_RELATIVE_PATH = '.docker/announce/runtime/.env';

    /**
     * Variable del announce -> ajuste que la gobierna y sus dos valores.
     *
     * @var array<string, array{setting: string, on: int, off: int}>
     */
    private const FACTORS = [
        'DOWNLOAD_FACTOR' => ['setting' => 'other.freeleech', 'on' => 0,   'off' => 100],
        'UPLOAD_FACTOR'   => ['setting' => 'other.doubleup',  'on' => 200, 'off' => 100],
    ];

    /**
     * Ajustes que gobiernan alguna promo, para que quien nos llame sepa cuales
     * tiene que refrescar antes.
     *
     * @return list<string>
     */
    public static function settings(): array
    {
        return array_column(self::FACTORS, 'setting');
    }

    public static function path(): string
    {
        return base_path(self::ENV_RELATIVE_PATH);
    }

    public static function desired(string $variable): int
    {
        $factor = self::FACTORS[$variable];

        return config($factor['setting']) ? $factor['on'] : $factor['off'];
    }

    /**
     * Valor que el announce tiene ahora mismo, o null si no se puede leer.
     */
    public static function current(string $variable): ?int
    {
        $contents = self::read();

        if ($contents === null) {
            return null;
        }

        if (preg_match('/^'.preg_quote($variable, '/').'=(\d{1,3})$/m', $contents, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Idempotente: no escribe ni recarga nada si los factores ya son correctos.
     *
     * Una sola escritura y una sola recarga aunque cambien los dos a la vez.
     *
     * @return array{changed: bool, reloaded: bool, factors: array<string, array{from: null|int, to: int}>}
     */
    public static function sync(): array
    {
        $result = ['changed' => false, 'reloaded' => false, 'factors' => []];

        $contents = self::read();

        if ($contents === null) {
            Log::error('TrackerPromos: no puedo leer el .env del announce.', ['path' => self::path()]);

            return $result;
        }

        $pending = [];

        foreach (array_keys(self::FACTORS) as $variable) {
            $desired = self::desired($variable);
            $current = self::current($variable);

            if ($current === $desired) {
                continue;
            }

            $result['factors'][$variable] = ['from' => $current, 'to' => $desired];
            $pending[$variable]           = $desired;
        }

        if ($pending === []) {
            return $result;
        }

        foreach ($pending as $variable => $value) {
            $replaced = preg_replace(
                '/^'.preg_quote($variable, '/').'=\d{1,3}$/m',
                $variable.'='.$value,
                $contents,
                1,
                $count
            );

            if ($replaced === null || $count !== 1) {
                Log::error('TrackerPromos: no encuentro la linea en el .env del announce.', ['variable' => $variable]);

                return $result;
            }

            $contents = $replaced;
        }

        if (!self::write($contents)) {
            return $result;
        }

        $result['changed']  = true;
        $result['reloaded'] = Unit3dAnnounce::reloadConfig();

        if (!$result['reloaded']) {
            // El fichero ya esta bien, asi que el proximo arranque del announce
            // sale correcto igualmente. Lo que falta es el efecto inmediato.
            Log::error('TrackerPromos: .env reescrito pero el announce no recargo.', $result['factors']);
        }

        return $result;
    }

    private static function read(): ?string
    {
        $path = self::path();

        if (!is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private static function write(string $contents): bool
    {
        $path = self::path();

        if (!is_writable(\dirname($path))) {
            Log::error('TrackerPromos: no puedo escribir en el directorio del announce.', ['path' => $path]);

            return false;
        }

        // Rename atomico dentro del mismo directorio. El announce reabre la ruta
        // en cada /config/reload, asi que cambiar de inodo es seguro — y por eso
        // el bind mount es del DIRECTORIO y no del fichero suelto.
        $temp = $path.'.tmp-'.getmypid();

        if (file_put_contents($temp, $contents, LOCK_EX) === false) {
            return false;
        }

        chmod($temp, 0640);

        if (!rename($temp, $path)) {
            @unlink($temp);

            return false;
        }

        return true;
    }
}
