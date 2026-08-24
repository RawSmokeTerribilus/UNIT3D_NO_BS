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

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Traductor local. Habla con el LibreTranslate que corre en esta misma
 * máquina; no sale a internet ni tiene cuota.
 *
 * Por qué autohospedado y no DeepL: traducir un catálogo entero no puede
 * depender de una cuota mensual ni de un tercero que cierre el grifo. El
 * compose vive en ~/scripts/Media-Management/libretranslate/.
 *
 * Nunca inventa: si el servicio no responde, devuelve cadena vacía y el
 * llamante se queda con el texto original. Una sinopsis en inglés es peor que
 * una en castellano, pero mucho mejor que ninguna.
 */
final class LibreTranslateClient
{
    private const TIMEOUT = 120;

    private const FAILURE_THRESHOLD = 5;

    /**
     * LibreTranslate tarda con textos largos y una sinopsis no lo justifica.
     * Por encima de esto se recorta antes de enviar.
     */
    private const MAX_CHARS = 6000;

    private int $failures = 0;

    private readonly string $base;

    public function __construct()
    {
        $this->base = rtrim((string) config('services.libretranslate.url'), '/');
    }

    public function isEnabled(): bool
    {
        return $this->base !== '' && $this->failures < self::FAILURE_THRESHOLD;
    }

    /**
     * Traduce, o devuelve '' si no se puede. Nunca lanza al llamante.
     */
    public function translate(string $text, string $from, string $to): string
    {
        $text = trim($text);

        if (!$this->isEnabled() || $text === '' || $from === $to) {
            return '';
        }

        try {
            $json = Http::timeout(self::TIMEOUT)
                ->asJson()
                ->post($this->base.'/translate', [
                    'q'      => mb_substr($text, 0, self::MAX_CHARS),
                    'source' => $from,
                    'target' => $to,
                    'format' => 'text',
                ])
                ->throw()
                ->json();

            $this->failures = 0;

            $traducido = \is_array($json) ? trim((string) ($json['translatedText'] ?? '')) : '';

            return $traducido === '' ? '' : $this->limpiar($traducido);
        } catch (Throwable $e) {
            $this->failures++;
            Log::warning('libretranslate falló: '.$e->getMessage());

            return '';
        }
    }

    /**
     * Detección barata de castellano frente a inglés, contando palabras
     * vacías. Suficiente para un párrafo entero, que es lo que se traduce.
     */
    public static function pareceCastellano(string $texto): bool
    {
        $t = ' '.mb_strtolower($texto).' ';
        $es = 0;
        $en = 0;

        foreach ([' de ', ' la ', ' el ', ' que ', ' los ', ' las ', ' una ', ' con ', ' por ', ' para '] as $w) {
            $es += substr_count($t, $w);
        }

        foreach ([' the ', ' of ', ' and ', ' to ', ' in ', ' is ', ' for ', ' with ', ' this ', ' that '] as $w) {
            $en += substr_count($t, $w);
        }

        return $es >= $en;
    }

    /**
     * Arregla lo que el motor estropea de forma sistemática.
     *
     * Medido el 2026-08-21: se come los símbolos de marca registrada
     * ("Bluetooth™" sale como "BluetoothTM") y parte algún compuesto técnico.
     * Lo primero es mecánico y se corrige aquí; lo segundo no, y se asume.
     */
    private function limpiar(string $texto): string
    {
        return strtr($texto, [
            'TM)'  => '™)',
            'TM,'  => '™,',
            'TM.'  => '™.',
            'TM '  => '™ ',
            '(R)'  => '®',
            ' ,'   => ',',
            ' .'   => '.',
        ]);
    }
}
