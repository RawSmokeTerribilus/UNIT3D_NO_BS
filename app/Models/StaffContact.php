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

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un contacto del staff con un socio. Ver la migracion de staff_contacts.
 *
 * @property int                             $id
 * @property int                             $user_id
 * @property \Illuminate\Support\Carbon      $contacted_at
 * @property string                          $campana
 * @property string                          $canal
 * @property string|null                     $plantilla
 * @property int|null                        $hr_activos
 * @property int|null                        $hr_en_camino
 * @property string|null                     $horas_pendientes
 * @property int|null                        $conversation_id
 * @property \Illuminate\Support\Carbon|null $plazo_hasta
 * @property \Illuminate\Support\Carbon|null $visto_at
 * @property int|null                        $staff_id
 * @property string                          $origen
 * @property string|null                     $nota
 */
class StaffContact extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
            'plazo_hasta'  => 'datetime',
            'visto_at'     => 'datetime',
        ];
    }

    /**
     * Contactos cuya ventana sigue abierta: el socio esta «en proceso».
     *
     * @param Builder<self> $query
     */
    public function scopeAbiertos(Builder $query): void
    {
        $query->where('plazo_hasta', '>', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
