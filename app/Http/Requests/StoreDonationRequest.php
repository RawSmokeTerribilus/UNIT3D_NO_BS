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

namespace App\Http\Requests;

use App\Enums\ModerationStatus;
use App\Models\Donation;
use App\Models\DonationGateway;
use App\Models\DonationPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDonationRequest extends FormRequest
{
    /**
     * Donaciones pendientes a la vez por usuario.
     */
    private const int MAX_PENDIENTES = 3;

    /**
     * El justificante ya normalizado: lo que casó con el patrón del método.
     */
    public ?string $justificante = null;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array<\Illuminate\Contracts\Validation\Rule|string>|string>
     */
    public function rules(): array
    {
        return [
            'package_id' => 'integer|required|exists:donation_packages,id',
            'gateway_id' => [
                'integer',
                'required',
                Rule::exists('donation_gateways', 'id')->where('is_active', true),
            ],
            'transaction' => 'required|min:1|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gateway_id.required' => 'Elige primero cómo quieres pagar.',
            'gateway_id.exists'   => 'Ese método de pago no está disponible ahora mismo.',
            'transaction.required' => 'Falta el justificante del pago.',
        ];
    }

    /**
     * Lo que la vista ya filtra se vuelve a comprobar aquí: que el método se
     * ofrece para ese tramo y que lo pegado tiene la forma que pide el método.
     * Un hash mal copiado o un código de cheque a medias se para en la puerta,
     * no cuando el Owner se pone a verificarlo.
     *
     * @return array<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $gateway = DonationGateway::find($this->integer('gateway_id'));
                $package = DonationPackage::find($this->integer('package_id'));

                if ($gateway === null || $package === null) {
                    return;
                }

                if (!$gateway->ofreceEn($package)) {
                    $validator->errors()->add('gateway_id', $gateway->name.' no está disponible para este tramo.');

                    return;
                }

                $this->justificante = $gateway->extraerJustificante((string) $this->input('transaction'));

                if ($this->justificante === null) {
                    $validator->errors()->add(
                        'transaction',
                        'Lo que has pegado no tiene la forma de un justificante de '.$gateway->name.'. Revisa que lo hayas copiado entero.',
                    );

                    return;
                }

                // Quien ya tiene varias esperando no suma más: o algo le está
                // saliendo mal y hay que hablarlo, o es un botón pulsado de más.
                $pendientes = Donation::query()
                    ->where('user_id', '=', $this->user()->id)
                    ->where('status', '=', ModerationStatus::PENDING)
                    ->count();

                if ($pendientes >= self::MAX_PENDIENTES) {
                    $validator->errors()->add(
                        'transaction',
                        'Ya tienes '.$pendientes.' donaciones esperando revisión. Espera a que las miremos, y si algo ha salido mal escribe al staff por privado.',
                    );

                    return;
                }

                // El mismo justificante dos veces. Sólo en los métodos con
                // patrón: ahí el justificante identifica UN pago (un hash, un
                // código). En los de texto libre («no tengo cuenta de Ko-fi»)
                // dos personas pueden escribir lo mismo sin que sea el mismo pago.
                //
                // Se compara en PHP porque `transaction` va cifrado con IV
                // aleatorio: el mismo texto cifrado dos veces no coincide en SQL.
                if ($gateway->patron_justificante !== null && $gateway->patron_justificante !== '') {
                    $normalizar = fn (?string $texto) => strtolower(preg_replace('/[\s-]+/', '', (string) $texto));
                    $buscado = $normalizar($this->justificante);

                    $repetida = Donation::query()
                        ->where('status', '!=', ModerationStatus::REJECTED)
                        ->get(['id', 'user_id', 'transaction'])
                        ->first(fn (Donation $donation) => $normalizar($donation->transaction) === $buscado);

                    if ($repetida !== null) {
                        $validator->errors()->add(
                            'transaction',
                            $repetida->user_id === $this->user()->id
                                ? 'Ese justificante ya nos lo enviaste: está en la lista, no hace falta repetirlo.'
                                : 'Ese justificante ya lo ha enviado otra cuenta. Si el pago es tuyo, escribe al staff por privado.',
                        );
                    }
                }
            },
        ];
    }
}
