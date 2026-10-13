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
 * Un baneo por H&R abandonado y su seguimiento. Ver App\Services\HitRunBans.
 *
 * @property int                             $id
 * @property int                             $user_id
 * @property int|null                        $ban_id
 * @property int|null                        $staff_id
 * @property string                          $origen           hitrun | manual (botón de banear del perfil)
 * @property \Illuminate\Support\Carbon      $banned_at
 * @property int|null                        $telegram_user_id
 * @property string|null                     $invite_link
 * @property string|null                     $invite_name
 * @property bool                            $telegram_enviado
 * @property bool                            $correo_enviado
 * @property \Illuminate\Support\Carbon|null $joined_at
 * @property \Illuminate\Support\Carbon      $prune_after
 * @property \Illuminate\Support\Carbon|null $pruned_at
 * @property int|null                        $pruned_by
 */
class HitRunBan extends Model
{
    protected $table = 'hitrun_bans';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'banned_at'        => 'datetime',
            'joined_at'        => 'datetime',
            'prune_after'      => 'datetime',
            'pruned_at'        => 'datetime',
            'telegram_enviado' => 'bool',
            'correo_enviado'   => 'bool',
        ];
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
