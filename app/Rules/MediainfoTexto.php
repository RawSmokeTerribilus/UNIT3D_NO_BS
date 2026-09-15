<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El mediainfo tiene que ser la salida de TEXTO de MediaInfo.
 *
 * Llegaban JSON completos de MediaInfo (150 KB para un pack de 13 ficheros),
 * BBCode y copias sin cabeceras. La ficha no los sabe pintar, y el audit de
 * una edición llegó a 361 KB y tumbó /dashboard/audits (2026-09-10).
 *
 * Criterio: si App\Helpers\MediaInfo no encuentra ninguna sección, no es un
 * mediainfo para nosotros. SECCION replica su REGEX_SECTION, que es privada:
 * si cambia allí, hay que cambiarla aquí.
 *
 * En una edición se pasa el valor guardado y, si no cambia, no se revalida:
 * un moderador tiene que poder recategorizar un torrent viejo aunque arrastre
 * un mediainfo mal pegado.
 */
final class MediainfoTexto implements ValidationRule
{
    private const string SECCION = '/^[ \t]*(?:general|video|audio|text|image|menu)(?:\s\#\d+?)*[ \t]*$/im';

    private const string BBCODE = '/\[\/?(?:code|center|b|i|u|size|color|spoiler|quote|pre|url|img)(?:=[^\]]*)?\]/i';

    public function __construct(private readonly ?string $guardado = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            $fail(__('validation.mediainfo-no-reconocido'));

            return;
        }

        $texto = self::normaliza($value);

        if ($texto === '' || ($this->guardado !== null && $texto === self::normaliza($this->guardado))) {
            return;
        }

        $primero = $texto[0];

        if ($primero === '{' || ($primero === '[' && json_validate($texto))) {
            $fail(__('validation.mediainfo-json'));
        } elseif ($primero === '<') {
            $fail(__('validation.mediainfo-xml'));
        } elseif (preg_match(self::BBCODE, $texto) === 1) {
            $fail(__('validation.mediainfo-bbcode'));
        } elseif (preg_match(self::SECCION, $texto) !== 1) {
            $fail(__('validation.mediainfo-no-reconocido'));
        }
    }

    /**
     * Sólo para comparar y comprobar; lo que se guarda es el valor original.
     * El navegador manda los saltos de un textarea como \r\n aunque se hayan
     * guardado como \n, y sin normalizar una edición sin cambios parecería un
     * mediainfo nuevo.
     */
    private static function normaliza(string $valor): string
    {
        $valor = str_replace(["\r\n", "\r", "\xc2\xa0"], ["\n", "\n", ' '], $valor);

        if (str_starts_with($valor, "\u{FEFF}")) {
            $valor = substr($valor, 3);
        }

        return trim($valor);
    }
}
