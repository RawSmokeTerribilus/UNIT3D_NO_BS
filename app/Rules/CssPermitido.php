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

use App\Helpers\CssPermitido as Lista;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que una hoja de estilo enlazada esté en la lista blanca.
 *
 * La lógica vive en App\Helpers\CssPermitido, que es la MISMA que usa la
 * plantilla al pintarla: si sólo se validase al guardar, los valores que ya
 * estuvieran en la base seguirían cargándose.
 */
final class CssPermitido implements ValidationRule
{
    public function __construct(private readonly bool $soloLocal = false)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value) || Lista::limpia($value, $this->soloLocal) === null) {
            $fail($this->soloLocal
                ? __('validation.css-solo-local')
                : __('validation.css-no-permitido', [
                    'dominios' => implode(', ', Lista::DOMINIOS),
                ]));
        }
    }
}
