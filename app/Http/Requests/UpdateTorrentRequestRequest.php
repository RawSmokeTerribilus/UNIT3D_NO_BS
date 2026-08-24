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
 * @author     Roardom <roardom@protonmail.com>
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

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateTorrentRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tmdb_movie_id' => $this->has('movie_exists_on_tmdb') ? ($this->input('tmdb_movie_id') ?: null) : null,
            'tmdb_tv_id'    => $this->has('tv_exists_on_tmdb') ? ($this->input('tmdb_tv_id') ?: null) : null,
            'imdb'          => $this->has('title_exists_on_imdb') ? ($this->input('imdb') ?: null) : null,
            'tvdb'          => $this->has('tv_exists_on_tvdb') ? ($this->input('tvdb') ?: null) : null,
            'mal'           => $this->has('anime_exists_on_mal') ? ($this->input('mal') ?: null) : null,
            'igdb'          => $this->has('game_exists_on_igdb') ? ($this->input('igdb') ?: null) : null,
            'isbn13'        => $this->has('book_exists_on_google') ? ($this->input('isbn13') ?: null) : null,
            'asin'          => $this->has('audiobook_exists_on_audible') ? ($this->input('asin') ?: null) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<\Illuminate\Validation\ConditionalRules|string>|string>
     */
    public function rules(Request $request): array
    {
        $category = Category::findOrFail($request->integer('category_id'));

        $mustBeNull = function (string $attribute, mixed $value, callable $fail): void {
            if ($value !== null) {
                $fail("The {$attribute} must be null.");
            }
        };

        return [
            'name' => [
                'required',
                'max:180',
            ],
            'imdb' => [
                Rule::when($category->movie_meta || $category->tv_meta, [
                    'required_with:title_exists_on_imdb',
                    'nullable',
                    'decimal:0',
                    'min:0',
                ]),
                Rule::when(!($category->movie_meta || $category->tv_meta), [
                    $mustBeNull,
                ]),
            ],
            'tvdb' => [
                Rule::when($category->tv_meta, [
                    'required_with:tv_exists_on_tvdb',
                    'nullable',
                    'decimal:0',
                    'min:0',
                ]),
                Rule::when(!$category->tv_meta, [
                    $mustBeNull,
                ]),
            ],
            'season_number' => [
                Rule::when($category->tv_meta, [
                    'required',
                    'integer',
                    'min:0',
                ]),
                Rule::when(!$category->tv_meta, [
                    $mustBeNull,
                ]),
            ],
            'episode_number' => [
                Rule::when($category->tv_meta, [
                    'required',
                    'integer',
                    'min:0',
                ]),
                Rule::when(!$category->tv_meta, [
                    $mustBeNull,
                ]),
            ],
            'tmdb_movie_id' => [
                Rule::when($category->movie_meta, [
                    'required_with:movie_exists_on_tmdb',
                    'nullable',
                    'decimal:0',
                    'min:0',
                ]),
                Rule::when(!$category->movie_meta, [
                    $mustBeNull,
                ]),
            ],
            'tmdb_tv_id' => [
                Rule::when($category->tv_meta, [
                    'required_with:tv_exists_on_tmdb',
                    'nullable',
                    'decimal:0',
                    'min:0',
                ]),
                Rule::when(!$category->tv_meta, [
                    $mustBeNull,
                ]),
            ],
            'mal' => [
                Rule::when($category->movie_meta || $category->tv_meta, [
                    'required_with:anime_exists_on_mal',
                    'nullable',
                    'decimal:0',
                    'min:0',
                ]),
                Rule::when(!($category->movie_meta || $category->tv_meta), [
                    $mustBeNull,
                ]),
            ],
            'igdb' => [
                Rule::when($category->game_meta, [
                    'required_with:game_exists_on_igdb',
                    'nullable',
                    'decimal:0',
                    'min:0',
                ]),
                Rule::when(!$category->game_meta, [
                    $mustBeNull,
                ]),
            ],
            'isbn13' => [
                Rule::when($category->book_meta, [
                    'required_with:book_exists_on_google',
                    'nullable',
                    'string',
                    'size:13',
                    'regex:/^\d{13}$/',
                ]),
                Rule::when(!$category->book_meta, [
                    $mustBeNull,
                ]),
            ],
            'asin' => [
                Rule::when($category->audiobook_meta, [
                    'required_with:audiobook_exists_on_audible',
                    'nullable',
                    'string',
                    'size:10',
                    'regex:/^[A-Za-z0-9]{10}$/',
                ]),
                Rule::when(!$category->audiobook_meta, [
                    $mustBeNull,
                ]),
            ],
            'category_id' => [
                'required',
                'exists:categories,id',
            ],
            'type_id' => [
                'nullable',
                'exists:types,id',
            ],
            'resolution_id' => [
                'nullable',
                'exists:resolutions,id',
            ],
            'description' => [
                'required',
                'string',
            ],
            'anon' => [
                'required',
                'boolean',
            ],
        ];
    }
}
