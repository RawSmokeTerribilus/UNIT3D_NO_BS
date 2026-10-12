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

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use AllowDynamicProperties;

/**
 * App\Models\DonationGateway.
 *
 * @property int                        $id
 * @property int                        $position
 * @property string                     $name
 * @property string|null                $icono
 * @property string                     $address
 * @property string|null                $instrucciones
 * @property string|null                $etiqueta_justificante
 * @property string|null                $patron_justificante
 * @property bool                       $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class DonationGateway extends Model
{
    /** @use HasFactory<\Database\Factories\DonationGatewayFactory> */
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array{position: 'int', name: 'string', address: 'string', is_active: 'bool', created_at: 'datetime', updated_at: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'position'   => 'int',
            'name'       => 'string',
            'address'    => 'string',
            'is_active'  => 'bool',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Un enlace se abre (Ko-fi, la tienda de cheques regalo); una dirección se
     * copia (wallets). Es el mismo criterio que usaba la vista de upstream.
     */
    public function esEnlace(): bool
    {
        return str_starts_with($this->address, 'http');
    }

    /**
     * Los tramos en los que se ofrece este método. Lo encienden y apagan los
     * Owners desde el panel de configuración, combinación a combinación.
     *
     * @return BelongsToMany<DonationPackage, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(DonationPackage::class, 'donation_gateway_package');
    }

    /**
     * ¿Se puede pagar este tramo por este método AHORA? Encendido y con la
     * combinación encendida.
     */
    public function ofreceEn(DonationPackage $package): bool
    {
        return $this->is_active && $this->packages()->whereKey($package->id)->exists();
    }

    /**
     * El patrón se BUSCA dentro de lo pegado, no se exige entero: así valen
     * igual el hash pelado, el enlace del explorador o el código con espacios.
     * Devuelve lo que casa, que es lo que se guarda; null si no aparece.
     * Sin patrón, el texto tal cual.
     */
    public function extraerJustificante(string $texto): ?string
    {
        $texto = trim($texto);

        if ($this->patron_justificante === null || $this->patron_justificante === '') {
            return $texto === '' ? null : $texto;
        }

        return preg_match(self::regex($this->patron_justificante), $texto, $m) === 1 ? $m[0] : null;
    }

    /**
     * El patrón se guarda sin delimitadores para que el panel no tenga que
     * pensar en ellos. Mayúsculas y minúsculas dan igual: los hash se copian de
     * cualquier manera y los códigos de cheque regalo también.
     *
     * Lleva BORDES: lo que casa no puede tener letras ni cifras pegadas ni
     * delante ni detrás. Sin ellos, un hash con dos caracteres de más casaba
     * con sus primeros 64 y se guardaba recortado como si fuera bueno (visto en
     * staging el 2026-10-12). Una barra, un espacio o el final sí valen, que es
     * lo que rodea al hash dentro del enlace de un explorador.
     */
    public static function regex(string $patron): string
    {
        return '~(?<![a-z0-9])(?:'.str_replace('~', '\\~', $patron).')(?![a-z0-9])~i';
    }

    /**
     * Las instrucciones admiten dos huecos que sólo se conocen al pintar.
     */
    public function instruccionesPara(string $importe, string $usuario): ?string
    {
        if ($this->instrucciones === null || $this->instrucciones === '') {
            return null;
        }

        return str_replace(['{importe}', '{usuario}'], [$importe, $usuario], $this->instrucciones);
    }
}
