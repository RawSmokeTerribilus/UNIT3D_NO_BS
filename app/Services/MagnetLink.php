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
use Illuminate\Support\Facades\URL;

/**
 * Constructor unico de enlaces magnet.
 *
 * Un magnet que FUNCIONE en un tracker privado tiene que llevar una credencial
 * dentro: el cliente necesita anunciar y para eso hace falta el passkey. No
 * existe el magnet privado compartible y seguro. Lo unico que se puede hacer es
 * que la credencial CADUQUE.
 *
 * Lo que habia antes metia el `rsskey` del usuario en el parametro `as`, sin
 * caducidad y sin escapar. Quien recibiera ese enlace se bajaba el .torrent
 * personalizado con el passkey del que lo compartio y anunciaba COMO EL: el que
 * comparte se come el trafico y parece cuenta compartida. Es la razon por la que
 * en la mayoria de instancias de UNIT3D los magnets estan apagados.
 *
 * Aqui el `as` es una URL firmada que expira. Pasada la ventana el enlace no
 * sirve, y mientras vive entrega como mucho UN .torrent, nunca la cuenta.
 *
 * El enlace anterior llevaba ADEMAS `&tr=` con el `passkey` del usuario. Entre
 * eso y el `rsskey` del `as`, compartir un magnet no era filtrar un .torrent:
 * era entregar la cuenta del tracker entera.
 *
 * NO se anade `tr`. Meter el announce con passkey en el propio magnet lo haria
 * funcionar sin descargar el .torrent, pero dejaria el passkey dentro del enlace
 * para siempre. Sin `tr`, el cliente saca el announce del .torrent que consigue
 * por `as`, que ya viene personalizado y con el flag privado puesto.
 */
final class MagnetLink
{
    /**
     * Ventana de vida del enlace firmado. Corta a proposito: es el tiempo entre
     * pulsar el boton y que el cliente lo abra, no el tiempo que dura la
     * descarga.
     */
    public const int TTL_MINUTES = 15;

    /**
     * @param string $infoHashHex los 40 caracteres en hex, NO los 20 bytes crudos
     */
    public static function build(int $torrentId, string $name, string $infoHashHex, User $user, ?int $size = null): string
    {
        $source = URL::temporarySignedRoute(
            'torrent.download.magnet',
            now()->addMinutes(self::TTL_MINUTES),
            ['id' => $torrentId, 'user' => $user->id],
        );

        // rawurlencode en los dos: un nombre con '&' o un espacio partia el
        // magnet por la mitad, y la URL firmada lleva query string propia.
        return 'magnet:?dn='.rawurlencode($name)
            .'&xt=urn:btih:'.$infoHashHex
            .'&as='.rawurlencode($source)
            .($size === null ? '' : '&xl='.$size);
    }

    /**
     * Version para un modelo, que es el caso de las vistas: el `info_hash` de la
     * tabla son 20 bytes binarios y hay que hexearlo una vez. UNA.
     */
    public static function forTorrent(\App\Models\Torrent $torrent, User $user): string
    {
        return self::build(
            $torrent->id,
            (string) $torrent->name,
            bin2hex((string) $torrent->info_hash),
            $user,
            $torrent->size === null ? null : (int) $torrent->size,
        );
    }
}
