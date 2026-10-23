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

use App\Models\User;

/**
 * Pase firmado de la Arena en su propio origen (arena.rawsmoke.net): el iframe de los juegos
 * no comparte origen con el tracker, así que no ve su sesión. /arena/<slug> firma un pase
 * corto que va en la URL del iframe; la puerta de nginx (/__arena/pase) lo cambia por una
 * cookie del origen de la Arena con la duración del pase de la Arena.
 *
 * Formato: base64url(json {u, n, e, t}) . "." . base64url(hmac_sha256)
 *   u = id, n = usuario, e = caduca (epoch), t = "url" | "cookie"
 */
class ArenaPase
{
    public const string URL = 'url';

    public const string COOKIE = 'cookie';

    public static function disponible(): bool
    {
        return \is_string(config('arena.origen')) && config('arena.origen') !== ''
            && \is_string(config('arena.pase_secreto')) && \strlen(config('arena.pase_secreto')) >= 32;
    }

    public static function firmar(User $user, string $tipo, int $segundos): string
    {
        $carga = self::b64(json_encode([
            'u' => $user->id,
            'n' => $user->username,
            'e' => now()->getTimestamp() + $segundos,
            't' => $tipo,
        ], JSON_THROW_ON_ERROR));

        return $carga.'.'.self::b64(hash_hmac('sha256', $carga, (string) config('arena.pase_secreto'), true));
    }

    /**
     * @return array{u: int, n: string, e: int, t: string}|null
     */
    public static function verificar(?string $pase, string $tipo): ?array
    {
        if ($pase === null || $pase === '' || \strlen($pase) > 512 || !str_contains($pase, '.')) {
            return null;
        }

        [$carga, $firma] = explode('.', $pase, 2);
        $esperada = self::b64(hash_hmac('sha256', $carga, (string) config('arena.pase_secreto'), true));

        if (!hash_equals($esperada, $firma)) {
            return null;
        }

        $datos = json_decode((string) base64_decode(strtr($carga, '-_', '+/'), true), true);

        if (!\is_array($datos) || ($datos['t'] ?? null) !== $tipo || !\is_int($datos['u'] ?? null)
            || !\is_int($datos['e'] ?? null) || $datos['e'] < now()->getTimestamp()) {
            return null;
        }

        return $datos;
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
