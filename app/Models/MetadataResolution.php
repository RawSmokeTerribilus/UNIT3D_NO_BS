<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\MetadataResolution.
 *
 * Output ledger of the metadata consensus resolver — one row per torrent.
 *
 * @property int                             $id
 * @property int                             $torrent_id
 * @property string                          $category   MOVIE|TV
 * @property int|null                        $tmdb_id
 * @property int|null                        $tvdb_id
 * @property string|null                     $imdb_id    ttNNNNNNN form
 * @property int|null                        $mal_id
 * @property string|null                     $resolved_title
 * @property int|null                        $resolved_year
 * @property string                          $confidence high|low|none
 * @property int                             $votes
 * @property int                             $mal_votes
 * @property array|null                      $detail     per-provider candidate breakdown
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class MetadataResolution extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tmdb_id'       => 'integer',
            'tvdb_id'       => 'integer',
            'mal_id'        => 'integer',
            'resolved_year' => 'integer',
            'votes'         => 'integer',
            'mal_votes'     => 'integer',
            'detail'        => 'array',
        ];
    }

    public function isTrusted(): bool
    {
        return $this->confidence === 'high';
    }

    /**
     * @return BelongsTo<\App\Models\Torrent, $this>
     */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class);
    }
}
