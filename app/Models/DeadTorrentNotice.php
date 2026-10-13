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
 * Peticion de resiembra de un torrent muerto y su reloj. Ver
 * App\Services\DeadTorrents.
 *
 * @property int                             $id
 * @property int                             $torrent_id
 * @property string                          $nombre
 * @property int|null                        $avisado_por
 * @property \Illuminate\Support\Carbon      $avisado_at
 * @property \Illuminate\Support\Carbon      $borrar_at
 * @property \Illuminate\Support\Carbon|null $ultima_semilla_at
 * @property int                             $destinatarios
 * @property string                          $estado
 * @property \Illuminate\Support\Carbon|null $resuelto_at
 */
class DeadTorrentNotice extends Model
{
    final public const string PENDIENTE = 'pendiente';

    final public const string SALVADO = 'salvado';

    final public const string BORRADO = 'borrado';

    final public const string DESAPARECIDO = 'desaparecido';

    final public const string CANCELADO = 'cancelado';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'avisado_at'        => 'datetime',
            'borrar_at'         => 'datetime',
            'ultima_semilla_at' => 'datetime',
            'resuelto_at'       => 'datetime',
        ];
    }

    /**
     * Con los borrados: la fila sobrevive al torrent.
     *
     * @return BelongsTo<Torrent, $this>
     */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class)->withTrashed()->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'avisado_por');
    }
}
