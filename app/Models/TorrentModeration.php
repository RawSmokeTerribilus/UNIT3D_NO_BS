<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationStatus;
use AllowDynamicProperties;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una decisión de moderación sobre un torrent: quién, cuándo, qué y por qué.
 *
 * Existe porque el «por qué» no se guardaba en ningún sitio. Al rechazar o
 * aplazar, el motivo es obligatorio, pero acababa únicamente dentro de un
 * mensaje privado entre el moderador que decidió y el uploader: ningún otro
 * moderador podía leerlo.
 *
 * @property int                         $id
 * @property int                         $torrent_id
 * @property ?int                        $user_id
 * @property ?int                        $conversation_id
 * @property ModerationStatus            $status
 * @property ?string                     $message
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class TorrentModeration extends Model
{
    /** @var string[] */
    protected $guarded = [];

    /**
     * @return array{status: class-string<ModerationStatus>}
     */
    protected function casts(): array
    {
        return [
            'status' => ModerationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Torrent, $this>
     */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class);
    }

    /**
     * El moderador que decidió.
     *
     * Puede ser null: si la cuenta desaparece, la decisión sigue siendo un
     * hecho y no debe irse con ella.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La conversación con el uploader, donde sigue el ida y vuelta.
     *
     * Aquí sólo vive la decisión y su motivo. Todo lo que se hable después
     * --que es lo habitual: el uploader corrige, pregunta, vuelve a subir--
     * se queda en su sitio y no se duplica.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Etiqueta corta del estado, para pintarla sin un `match` en cada vista.
     */
    public function etiqueta(): string
    {
        return match ($this->status) {
            ModerationStatus::APPROVED  => __('torrent.approved'),
            ModerationStatus::REJECTED  => __('torrent.rejected'),
            ModerationStatus::POSTPONED => __('torrent.postponed'),
            ModerationStatus::PENDING   => __('torrent.pending'),
        };
    }
}
