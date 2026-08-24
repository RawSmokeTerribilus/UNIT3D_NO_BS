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

/**
 * MODIFICADO PARA NOBS
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Este fichero contiene cambios sobre el original de UNIT3D Community Edition.
 * Se distribuye bajo la misma licencia, GNU AGPL v3.0.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use AllowDynamicProperties;

/**
 * App\Models\Type.
 *
 * @property int    $id
 * @property string $name
 * @property int    $position
 */
#[AllowDynamicProperties]
final class Type extends Model
{
    use Auditable;

    /** @use HasFactory<\Database\Factories\TypeFactory> */
    use HasFactory;

    /**
     * La lista se cachea 1 h fresca / 2 h rancia en los buscadores
     * (TorrentSearch, TorrentRequestSearch, SimilarTorrent) y nada la
     * invalidaba: crear una tipo por el panel no aparecia en la busqueda
     * avanzada hasta dos horas despues, que parece que esta rota.
     */
    protected static function booted(): void
    {
        static::saved(static fn () => cache()->forget('types'));
        static::deleted(static fn () => cache()->forget('types'));
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var string[]
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Get the torrents for the type.
     *
     * @return HasMany<Torrent, $this>
     */
    public function torrents(): HasMany
    {
        return $this->hasMany(Torrent::class);
    }

    /**
     * Get the requests for the type.
     *
     * @return HasMany<TorrentRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(TorrentRequest::class);
    }
}
