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

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Estado unico de las promos globales del sitio.
 *
 * Hasta ahora la secuencia «escribir el ajuste -> refrescar config() ->
 * proyectar sobre el tracker» vivia solo dentro de ConfigManager. El comando de
 * caducidad y el seeder necesitan exactamente lo mismo, y tres copias de la
 * misma secuencia se separan tarde o temprano. Esta clase es la copia unica.
 *
 * Ojo con `openreg`: la promo esta ENCENDIDA cuando `other.invite-only` vale
 * `false`. Apagarla significa poner `true`. La inversion vive aqui, en la tabla,
 * y no repartida en condicionales por el codigo.
 */
final class PromoState
{
    /**
     * @var array<string, array{setting: string, until: string, on: bool, label: string}>
     */
    public const PROMOS = [
        'freeleech' => [
            'setting' => 'other.freeleech',
            'until'   => 'other.freeleech_until',
            'on'      => true,
            'label'   => 'Freeleech global',
        ],
        'doubleup' => [
            'setting' => 'other.doubleup',
            'until'   => 'other.doubleup_until',
            'on'      => true,
            'label'   => 'Double upload global',
        ],
        'openreg' => [
            'setting' => 'other.invite-only',
            'until'   => 'other.openreg_until',
            'on'      => false,
            'label'   => 'Registro abierto',
        ],
    ];

    /**
     * Ajustes que hay que releer de la tabla antes de sincronizar nada.
     *
     * SettingServiceProvider los vuelca en config() durante el boot, asi que lo
     * que se acabe de escribir en esta misma peticion todavia no esta ahi.
     *
     * @return list<string>
     */
    public static function settings(): array
    {
        $keys = [
            'other.freeleech_leech_amnesty',
            'other.freeleech_leech_slots',
        ];

        foreach (self::PROMOS as $promo) {
            $keys[] = $promo['setting'];
            $keys[] = $promo['until'];
        }

        return array_values(array_unique($keys));
    }

    /**
     * Vuelca los valores vivos de la tabla sobre config().
     */
    public static function refreshConfig(): void
    {
        $fresh = Setting::all()->pluck('value', 'key');

        foreach (self::settings() as $key) {
            if (!$fresh->has($key)) {
                continue;
            }

            $value = $fresh[$key];

            config([$key => match (true) {
                str_ends_with($key, '_until')  => (string) $value,
                is_numeric($value)             => (int) $value,
                default                        => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            }]);
        }
    }

    /**
     * ¿Esta encendida esta promo ahora mismo?
     */
    public static function isOn(string $promo): bool
    {
        $spec = self::PROMOS[$promo];

        return (bool) config($spec['setting']) === $spec['on'];
    }

    /**
     * Fecha de fin de la promo, o null si no tiene o si no se entiende.
     *
     * Devuelve null tambien cuando la cadena es ilegible: quien decide que hacer
     * con eso es quien llama. Adivinar aqui seria adivinar en silencio.
     */
    public static function until(string $promo): ?CarbonImmutable
    {
        $raw = trim((string) config(self::PROMOS[$promo]['until']));

        if ($raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $raw, 'UTC') ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * ¿Tiene fecha puesta pero ilegible? Es el caso que NO debe apagar nada.
     */
    public static function hasUnreadableUntil(string $promo): bool
    {
        $raw = trim((string) config(self::PROMOS[$promo]['until']));

        return $raw !== '' && self::until($promo) === null;
    }

    /**
     * Promos encendidas, para el banner. `until` en ISO-8601 UTC o null.
     *
     * @return list<array{key: string, label: string, until: null|string}>
     */
    public static function active(): array
    {
        $active = [];

        foreach (array_keys(self::PROMOS) as $promo) {
            if (!self::isOn($promo)) {
                continue;
            }

            $until = self::until($promo);

            $active[] = [
                'key'   => $promo,
                'label' => self::PROMOS[$promo]['label'],
                'until' => $until?->toIso8601ZuluString(),
            ];
        }

        return $active;
    }

    /**
     * Estado encendido/apagado de las tres promos, para comparar antes y despues
     * de un guardado y anunciar solo lo que ha cambiado de verdad.
     *
     * @return array<string, bool>
     */
    public static function states(): array
    {
        $states = [];

        foreach (array_keys(self::PROMOS) as $promo) {
            $states[$promo] = self::isOn($promo);
        }

        return $states;
    }

    /**
     * Anuncia a los miembros las promos que han cambiado de estado.
     *
     * Se traga los errores: que Telegram este caido no puede tumbar un guardado
     * de configuracion ni un comando programado.
     *
     * @param array<string, bool> $before
     * @param array<string, bool> $after
     *
     * @return list<string> promos anunciadas
     */
    public static function announceTransitions(array $before, array $after): array
    {
        $anunciadas = [];

        foreach ($after as $promo => $encendida) {
            if (($before[$promo] ?? null) === $encendida) {
                continue;
            }

            try {
                PromoAnnouncer::miembros($promo, $encendida, $encendida ? self::until($promo) : null);
                $anunciadas[] = $promo;
            } catch (\Throwable $e) {
                Log::error('PromoState: fallo al anunciar el cambio de promo.', [
                    'promo' => $promo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $anunciadas;
    }

    /**
     * Apaga la promo y limpia su fecha. No sincroniza: eso lo hace `syncAll()`,
     * para que apagar varias a la vez sea una sola escritura del .env y una sola
     * recarga del announce.
     */
    public static function turnOff(string $promo): void
    {
        $spec = self::PROMOS[$promo];

        self::write($spec['setting'], $spec['on'] ? 'false' : 'true');
        self::write($spec['until'], '');
    }

    /**
     * Escribe un ajuste en la tabla. No toca `settings.json`: el volcado se hace
     * una sola vez al final, en `persist()`.
     */
    public static function write(string $key, string $value): void
    {
        Setting::where('key', '=', $key)->update(['value' => $value]);
    }

    /**
     * Vuelca la tabla a `storage/app/settings.json`, que es lo que restaura el
     * seeder cuando se reconstruyen los contenedores.
     */
    public static function snapshot(): void
    {
        file_put_contents(
            storage_path('app/settings.json'),
            json_encode(
                Setting::all()->pluck('value', 'key')->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );
    }

    /**
     * Releer, proyectar sobre el tracker y reconciliar la amnistia.
     *
     * Se traga sus propios errores a proposito: ni guardar la configuracion ni
     * sembrar la base pueden fracasar porque el announce este caido. Si esto no
     * corre, `auto:leech-amnesty` reconcilia en menos de diez minutos.
     *
     * @return array{amnesty: null|array<string, mixed>, promos: null|array<string, mixed>}
     */
    public static function syncAll(): array
    {
        $result = ['amnesty' => null, 'promos' => null];

        self::refreshConfig();

        try {
            $result['amnesty'] = LeechAmnesty::sync();
        } catch (\Throwable $e) {
            Log::error('PromoState: fallo al resincronizar la amnistia de Sanguijuela.', ['error' => $e->getMessage()]);
        }

        try {
            $result['promos'] = TrackerPromos::sync();
        } catch (\Throwable $e) {
            Log::error('PromoState: fallo al proyectar las promos globales sobre el tracker.', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Volcado + sincronizacion, que es lo que quiere quien acaba de escribir.
     *
     * @return array{amnesty: null|array<string, mixed>, promos: null|array<string, mixed>}
     */
    public static function persist(): array
    {
        self::snapshot();

        return self::syncAll();
    }
}
