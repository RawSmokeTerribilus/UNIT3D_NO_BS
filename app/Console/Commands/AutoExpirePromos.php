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

use App\Models\Group;
use App\Models\User;
use App\Notifications\PromoExpired;
use App\Services\LeechAmnesty;
use App\Services\PromoAnnouncer;
use App\Services\PromoState;
use App\Services\TrackerPromos;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Apaga las promos globales cuya fecha de fin ya ha pasado.
 *
 * El reloj del banner llevaba desde siempre siendo decorativo: al llegar a cero
 * hacia `clearInterval` y nada mas. El 2026-09-07 el sitio siguio regalando
 * descarga horas despues de la hora que el mismo anunciaba. Este comando es lo
 * que faltaba para que lo que promete el reloj ocurra.
 *
 * Regla que no se negocia: si la fecha esta puesta pero no se entiende, NO se
 * apaga nada y se avisa. Adivinar que quiso decir el operador es peor que no
 * hacer nada, porque apagar una promo por equivocacion se nota en el ratio de
 * todo el mundo.
 */
class AutoExpirePromos extends Command
{
    protected $signature = 'auto:expire-promos';

    protected $description = 'Apaga las promos globales (freeleech, doble subida, registro abierto) cuya fecha de fin ha vencido';

    final public function handle(): int
    {
        PromoState::refreshConfig();

        $ahora    = CarbonImmutable::now('UTC');
        $vencidas = [];

        foreach (array_keys(PromoState::PROMOS) as $promo) {
            if (!PromoState::isOn($promo)) {
                continue;
            }

            if (PromoState::hasUnreadableUntil($promo)) {
                $this->avisarFechaIlegible($promo);

                continue;
            }

            $until = PromoState::until($promo);

            if ($until !== null && $until->lessThanOrEqualTo($ahora)) {
                $vencidas[$promo] = $until;
            }
        }

        if ($vencidas === []) {
            $this->info('Sin promos vencidas.');

            return self::SUCCESS;
        }

        $antes = PromoState::states();

        foreach (array_keys($vencidas) as $promo) {
            PromoState::turnOff($promo);
        }

        // Una sola escritura del .env del announce y una sola recarga, aunque
        // venzan varias a la vez.
        $sync = PromoState::persist();

        PromoState::announceTransitions($antes, PromoState::states());

        foreach ($vencidas as $promo => $until) {
            $this->reportar($promo, $until, $ahora, $sync);
        }

        return self::SUCCESS;
    }

    /**
     * Comprobaciones posteriores al apagado, especificas de cada promo.
     *
     * Apagar el ajuste no basta: quien cobra es el announce Rust y su .env es
     * cache derivada. Si el empujon no llego, esto lo dice.
     *
     * @param array{amnesty: null|array<string, mixed>, promos: null|array<string, mixed>} $sync
     *
     * @return list<array{texto: string, ok: bool}>
     */
    private function candados(string $promo, array $sync): array
    {
        $recargo = (bool) ($sync['promos']['reloaded'] ?? false);
        $cambio  = (bool) ($sync['promos']['changed'] ?? false);

        $candados = [];

        if ($promo === 'freeleech') {
            $factor = TrackerPromos::current('DOWNLOAD_FACTOR');
            $candados[] = [
                'texto' => match (true) {
                    $factor === null => 'DOWNLOAD_FACTOR ilegible: no he podido leer el .env del announce.',
                    $factor === 100  => 'DOWNLOAD_FACTOR = 100: el tracker vuelve a cobrar la descarga.',
                    default          => 'DOWNLOAD_FACTOR = '.$factor.', deberia ser 100. El tracker sigue sin cobrar.',
                },
                'ok' => $factor === 100,
            ];

            $slots = Group::where('slug', '=', LeechAmnesty::GROUP_SLUG)->value('download_slots');
            $candados[] = [
                'texto' => $slots === null
                    ? 'No encuentro el grupo Sanguijuela para comprobar la amnistia.'
                    : 'Sanguijuela con download_slots = '.$slots.($slots === 0 ? ': amnistia revertida.' : ', deberia ser 0.'),
                'ok' => $slots === 0,
            ];
        }

        if ($promo === 'doubleup') {
            $factor = TrackerPromos::current('UPLOAD_FACTOR');
            $candados[] = [
                'texto' => match (true) {
                    $factor === null => 'UPLOAD_FACTOR ilegible: no he podido leer el .env del announce.',
                    $factor === 100  => 'UPLOAD_FACTOR = 100: la subida vuelve a contar al ritmo normal.',
                    default          => 'UPLOAD_FACTOR = '.$factor.', deberia ser 100.',
                },
                'ok' => $factor === 100,
            ];
        }

        if ($promo === 'openreg') {
            $cerrado = (bool) config('other.invite-only');
            $candados[] = [
                'texto' => $cerrado
                    ? 'other.invite-only = true: el registro esta cerrado otra vez.'
                    : 'other.invite-only sigue en false: el registro NO se ha cerrado.',
                'ok' => $cerrado,
            ];
        } elseif ($cambio) {
            $candados[] = [
                'texto' => $recargo
                    ? 'El announce recargo su configuracion en caliente.'
                    : 'El announce NO recargo: el fichero esta bien, pero no llegara a memoria hasta que reinicie.',
                'ok' => $recargo,
            ];
        }

        return $candados;
    }

    /**
     * @param array{amnesty: null|array<string, mixed>, promos: null|array<string, mixed>} $sync
     */
    private function reportar(string $promo, CarbonImmutable $until, CarbonImmutable $corte, array $sync): void
    {
        $label    = PromoState::PROMOS[$promo]['label'];
        $vence    = $until->format('Y-m-d H:i');
        $corteStr = $corte->format('Y-m-d H:i');
        $candados = $this->candados($promo, $sync);
        $fallos   = \count(array_filter($candados, static fn (array $c): bool => !$c['ok']));

        $this->info(sprintf('%s vencida el %s UTC: apagada. Comprobaciones en rojo: %d.', $label, $vence, $fallos));

        Log::info('AutoExpirePromos: promo apagada por vencimiento.', [
            'promo'    => $promo,
            'vencio'   => $vence,
            'corte'    => $corteStr,
            'candados' => $candados,
        ]);

        try {
            PromoAnnouncer::staff(PromoExpired::cuerpoPlano($label, $vence, $corteStr, $candados));
        } catch (\Throwable $e) {
            Log::error('AutoExpirePromos: fallo al avisar al staff por Telegram.', ['error' => $e->getMessage()]);
        }

        try {
            $admins = User::whereIn('group_id', Group::where('is_admin', '=', true)->pluck('id'))->get();
            Notification::send($admins, new PromoExpired($label, $vence, $corteStr, $candados));
        } catch (\Throwable $e) {
            Log::error('AutoExpirePromos: fallo al notificar a los admins.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Fecha puesta pero ilegible: no se apaga nada y se avisa una sola vez por
     * valor, para no inundar el grupo de staff cada diez minutos.
     */
    private function avisarFechaIlegible(string $promo): void
    {
        $clave = (string) config(PromoState::PROMOS[$promo]['until']);
        $label = PromoState::PROMOS[$promo]['label'];

        $this->warn(sprintf('%s: la fecha de fin no se entiende. NO se apaga nada.', $label));

        if (!Cache::add('promo-expire:bad-date:'.$promo.':'.md5($clave), true, now()->addDay())) {
            return;
        }

        Log::warning('AutoExpirePromos: fecha de fin ilegible, no se apaga la promo.', [
            'promo' => $promo,
            'valor' => $clave,
        ]);

        try {
            PromoAnnouncer::staff(
                'La fecha de fin de '.$label.' no se entiende: "'.$clave.'".'."\n\n"
                .'NO he apagado nada, porque adivinar seria peor. Corrigela en el panel de configuracion '
                .'(formato: fecha y hora en UTC) o dejala vacia si la promo no debe caducar sola.'
            );
        } catch (\Throwable $e) {
            Log::error('AutoExpirePromos: fallo al avisar de la fecha ilegible.', ['error' => $e->getMessage()]);
        }
    }
}
