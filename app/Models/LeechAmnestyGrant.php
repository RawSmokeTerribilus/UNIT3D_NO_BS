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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una concesion de la amnistia de descarga durante el freeleech.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $granted_at
 * @property string|null $revoked_at
 * @property string|null $revoked_reason
 */
class LeechAmnestyGrant extends Model
{
    protected $table = 'leech_amnesty_grants';

    protected $guarded = [];

    /**
     * @return array{granted_at: 'datetime', revoked_at: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
