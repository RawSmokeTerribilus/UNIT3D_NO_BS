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
 * Proyecta el freeleech global del panel sobre el tracker Rust.
 *
 * El ajuste `other.freeleech` no llegaba a ninguna parte: su unico consumidor
 * que cobraba era `ProcessAnnounce.php`, el announce PHP, muerto desde el corte
 * a Rust. El announce Rust decide el cobro en `announce.rs:777` con
 * min(config.download_factor, grupo, torrent), y `config.download_factor` sale
 * de la variable DOWNLOAD_FACTOR de su .env — donde 0 significa freeleech
 * global (`config.rs:49`). Esta clase es el cable que faltaba.
 *
 * Por que aqui y no en `groups.is_freeleech`: esa columna es el perk PERMANENTE
 * de un grupo (la UI la llama "Freeleech permanente" en
 * Staff/group/index.blade.php). Escribir una promo temporal encima obligaria a
 * recordar que grupos ya lo tenian para poder revertir, y un fallo al revertir
 * les quitaria el privilegio en silencio. La variable del tracker no deja
 * residuo: apagarla es imposible de hacer mal.
 *
 * Los grupos sin derecho de descarga no necesitan exclusion: el announce los
 * corta antes de calcular ningun factor — `banned`/`validating`/`disabled` en
 * `announce.rs:438` y `download_slots = 0` en `announce.rs:453`.
 *
 * La verdad sigue siendo la tabla `settings`. El .env del announce es cache
 * derivada, y `sync()` es idempotente: reconcilia sola, ya se la llame al
 * guardar la configuracion o desde el comando periodico.
 */
final class TrackerFreeleech
{
    /**
     * .env del announce. Vive en un bind mount compartido (docker-compose.yml)
     * para que sobreviva a reinicios y para que este contenedor pueda escribirlo.
     */
    private const ENV_RELATIVE_PATH = '.docker/announce/runtime/.env';

    public static function path(): string
    {
        return base_path(self::ENV_RELATIVE_PATH);
    }

    public static function desiredFactor(): int
    {
        return config('other.freeleech') ? 0 : 100;
    }

    /**
     * Factor que el announce tiene ahora mismo, o null si no se puede leer.
     */
    public static function currentFactor(): ?int
    {
        $path = self::path();

        if (!is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        if (preg_match('/^DOWNLOAD_FACTOR=(\d{1,3})$/m', $contents, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Idempotente: no escribe ni recarga nada si el factor ya es el correcto.
     *
     * @return array{changed: bool, from: null|int, to: int, reloaded: bool}
     */
    public static function sync(): array
    {
        $desired = self::desiredFactor();
        $current = self::currentFactor();

        $result = [
            'changed'  => false,
            'from'     => $current,
            'to'       => $desired,
            'reloaded' => false,
        ];

        if ($current === $desired) {
            return $result;
        }

        if (!self::write($desired)) {
            return $result;
        }

        $result['changed']  = true;
        $result['reloaded'] = Unit3dAnnounce::reloadConfig();

        if (!$result['reloaded']) {
            // El fichero ya esta bien, asi que el proximo arranque del announce
            // sale correcto igualmente. Lo que falta es el efecto inmediato.
            Log::error('TrackerFreeleech: DOWNLOAD_FACTOR reescrito pero el announce no recargo.', [
                'from' => $current,
                'to'   => $desired,
            ]);
        }

        return $result;
    }

    private static function write(int $factor): bool
    {
        $path = self::path();

        if (!is_readable($path) || !is_writable(\dirname($path))) {
            Log::error('TrackerFreeleech: no puedo escribir el .env del announce.', ['path' => $path]);

            return false;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        $updated = preg_replace(
            '/^DOWNLOAD_FACTOR=\d{1,3}$/m',
            'DOWNLOAD_FACTOR='.$factor,
            $contents,
            1,
            $count
        );

        if ($updated === null || $count !== 1) {
            Log::error('TrackerFreeleech: no encuentro la linea DOWNLOAD_FACTOR en el .env del announce.');

            return false;
        }

        // Rename atomico dentro del mismo directorio. El announce reabre la ruta
        // en cada /config/reload, asi que cambiar de inodo es seguro — y por eso
        // el bind mount es del DIRECTORIO y no del fichero suelto.
        $temp = $path.'.tmp-'.getmypid();

        if (file_put_contents($temp, $updated, LOCK_EX) === false) {
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
