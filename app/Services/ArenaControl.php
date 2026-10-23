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

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente de la API de control de un módulo de la Arena (agente de salas).
 *
 * Habla por la red interna `arena` con `control_url` del módulo (config/arena.php),
 * con el token compartido ARENA_CONTROL_TOKEN. Nunca toca Docker: el agente gestiona
 * procesos dentro de su contenedor. Cada acción lleva X-Arena-Actor para su auditoría.
 */
class ArenaControl
{
    public function __construct(private readonly string $slug)
    {
    }

    /**
     * @return array{ok: bool, status: int, data: array<mixed>, error: ?string}
     */
    public function llamar(string $metodo, string $ruta, array $cuerpo = [], ?string $actor = null): array
    {
        $base = config('arena.modulos.'.$this->slug.'.control_url');
        $token = (string) config('arena.control_token');

        if (!$base || $token === '') {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Módulo sin control_url o sin ARENA_CONTROL_TOKEN en el .env'];
        }

        try {
            /** @var PendingRequest $peticion */
            $peticion = Http::withHeaders(['X-Arena-Token' => $token, 'X-Arena-Actor' => $actor ?? 'sistema'])
                ->acceptJson()
                ->timeout(8);

            /** @var Response $respuesta */
            $respuesta = match ($metodo) {
                'GET'    => $peticion->get($base.$ruta, $cuerpo),
                'POST'   => $peticion->post($base.$ruta, $cuerpo),
                'DELETE' => $peticion->delete($base.$ruta),
                default  => throw new \InvalidArgumentException($metodo),
            };
        } catch (ConnectionException) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'El agente de salas no responde (¿contenedor parado?)'];
        }

        return [
            'ok'     => $respuesta->successful(),
            'status' => $respuesta->status(),
            'data'   => $respuesta->json() ?? [],
            'error'  => $respuesta->successful() ? null : (string) ($respuesta->json('error') ?? 'HTTP '.$respuesta->status()),
        ];
    }
}
