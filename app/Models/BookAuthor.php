<?php

declare(strict_types=1);

namespace App\Models;

use AllowDynamicProperties;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * App\Models\BookAuthor.
 *
 * Ficha de autor, cacheada desde OpenLibrary. El espejo de TmdbPerson.
 *
 * OpenLibrary no sirve para identificar ediciones españolas -- de ahí que no
 * vote en el resolver -- pero su catálogo de AUTORES sí es bueno, y es la
 * única fuente gratuita con foto y biografía. Por eso entra aquí y no allí.
 *
 * @property string                      $olid
 * @property string                      $name
 * @property ?string                     $personal_name
 * @property ?array<int, string>         $alternate_names
 * @property ?string                     $bio
 * @property ?string                     $birth_date
 * @property ?string                     $death_date
 * @property ?string                     $photo_url
 * @property ?array<string, string>      $remote_ids
 * @property ?int                        $work_count
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
#[AllowDynamicProperties]
final class BookAuthor extends Model
{
    /** @var string[] */
    protected $guarded = [];

    /** @var string */
    protected $primaryKey = 'olid';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /**
     * @return array{alternate_names: 'array', remote_ids: 'array'}
     */
    protected function casts(): array
    {
        return [
            'alternate_names' => 'array',
            'remote_ids'      => 'array',
        ];
    }

    /**
     * @return BelongsToMany<Book, $this>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_author', 'author_olid', 'isbn13', 'olid', 'isbn13')
            ->withPivot('position');
    }

    /**
     * URL pública de la ficha, derivada del id. No se guarda porque no aporta
     * nada que no esté ya en la clave.
     */
    /**
     * The audiobooks this author wrote. Separate pivot from books() because
     * that one is keyed by isbn13 and an audiobook is keyed by asin — its own
     * isbn13, when it has one, belongs to the audio edition and does not match
     * the e-book's.
     *
     * @return BelongsToMany<Audiobook, $this>
     */
    public function audiobooks(): BelongsToMany
    {
        return $this->belongsToMany(Audiobook::class, 'audiobook_author', 'author_olid', 'asin', 'olid', 'asin')
            ->withPivot('position');
    }

    public function openLibraryUrl(): string
    {
        return 'https://openlibrary.org/authors/'.$this->olid;
    }

    /**
     * Iniciales para el monograma cuando no hay foto, igual que hace la
     * página de personas.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }
}
