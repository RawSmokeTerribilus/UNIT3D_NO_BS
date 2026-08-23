<?php

declare(strict_types=1);

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
     * Traduce protegiendo nombres propios.
     *
     * El motor traduce los títulos como si fueran frases: «The Curse of Monkey
     * Island» salía como «La curva de la isla Monkey», y el nombre del juego
     * aparece en casi todas las sinopsis de IGDB, así que el error se veía
     * siempre.
     *
     * Cada término se sustituye por un marcador antes de enviar y se repone
     * después. El marcador es `ZQX<n>`: medido el 2026-08-23 contra el
     * LibreTranslate local, sobrevive intacto a la traducción. `__0__` NO
     * sobrevive (queda en ` 0 `) y `[[0]]` sólo a veces, así que no valen.
     *
     * Los términos se sustituyen del más largo al más corto, para que un
     * título que contenga a otro no se rompa por la mitad.
     *
     * @param array<int, string> $terminos
     */
    public function translateKeeping(string $text, array $terminos, string $from, string $to): string
    {
        $terminos = array_values(array_filter(array_unique(array_map('trim', $terminos)), fn ($t) => mb_strlen($t) >= 3));
        usort($terminos, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $mapa = [];
        $protegido = $text;

        foreach ($terminos as $i => $termino) {
            $marca = 'ZQX'.$i;
            $sustituido = str_ireplace($termino, $marca, $protegido);

            // Sólo se protege lo que de verdad aparece: un marcador suelto que
            // no se repone se quedaría en el texto final a la vista.
            if ($sustituido !== $protegido) {
                $mapa[$marca] = $termino;
                $protegido = $sustituido;
            }
        }

        $traducido = $this->translate($protegido, $from, $to);

        if ($traducido === '') {
            return '';
        }

        foreach ($mapa as $marca => $termino) {
            $traducido = str_replace($marca, $termino, $traducido);
        }

        // Si el motor se comió algún marcador, el texto queda con un hueco
        // raro; es preferible eso a devolver «ZQX0» a un lector, así que se
        // limpian los que hayan quedado sin reponer.
        return trim((string) preg_replace('/\bZQX\d+\b/', '', $traducido));
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
