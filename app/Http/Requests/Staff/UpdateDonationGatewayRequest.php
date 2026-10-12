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

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDonationGatewayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->group->is_owner;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array<\Illuminate\Contracts\Validation\Rule|string>|string>
     */
    public function rules(): array
    {
        return [
            'address'               => 'required',
            'name'                  => 'required',
            'icono'                 => 'nullable|string|max:255|regex:/^[a-z0-9 -]+$/',
            'instrucciones'         => 'nullable|string|max:5000',
            'etiqueta_justificante' => 'nullable|string|max:255',
            'patron_justificante'   => [
                'nullable',
                'string',
                'max:255',
                // Un patrón que no compila haría fallar TODAS las donaciones
                // por ese método con un error de PHP; mejor pararlo aquí.
                fn (string $attribute, mixed $value, \Closure $fail) => @preg_match(\App\Models\DonationGateway::regex((string) $value), '') === false
                    ? $fail('El patrón no es una expresión regular válida.')
                    : null,
            ],
            'position'              => 'min:0|max:9999999',
            'is_active'             => 'required|boolean',
        ];
    }
}
