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

namespace App\Http\Controllers\MediaHub;

use App\Http\Controllers\Controller;
use App\Models\Audiobook;
use App\Models\Book;
use App\Models\BookAuthor;
use App\Models\BookGenre;
use App\Models\BookNarrator;
use App\Models\BookPublisher;
use App\Models\BookSeries;
use App\Models\Category;
use App\Models\TmdbCollection;
use App\Models\TmdbCompany;
use App\Models\TmdbGenre;
use App\Models\TmdbMovie;
use App\Models\TmdbNetwork;
use App\Models\TmdbPerson;
use App\Models\TmdbTv;

class HomeController extends Controller
{
    /**
     * Display Media Hubs.
     */
    public function index(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('mediahub.index', [
            'tv'               => TmdbTv::count(),
            'movies'           => TmdbMovie::count(),
            'movieCategoryIds' => Category::where('movie_meta', '=', 1)->pluck('id')->toArray(),
            'tvCategoryIds'    => Category::where('tv_meta', '=', 1)->pluck('id')->toArray(),
            'collections'      => TmdbCollection::count(),
            'persons'          => TmdbPerson::whereNotNull('still')->count(),
            'genres'           => TmdbGenre::count(),
            'networks'         => TmdbNetwork::count(),
            'companies'        => TmdbCompany::count(),
            // Catalogo de libros. Los cuatro catalogos compartidos cuentan
            // ebooks y audiolibros juntos, asi que no se desglosan aqui: el
            // desglose por formato lo hace cada tarjeta de su hub.
            'books'                => Book::count(),
            'audiobooks'           => Audiobook::count(),
            'bookCategoryIds'      => Category::where('book_meta', '=', 1)->pluck('id')->toArray(),
            'audiobookCategoryIds' => Category::where('audiobook_meta', '=', 1)->pluck('id')->toArray(),
            'authors'              => BookAuthor::count(),
            'narrators'            => BookNarrator::count(),
            'bookGenres'           => BookGenre::count(),
            'publishers'           => BookPublisher::count(),
            'bookSeries'           => BookSeries::count(),
        ]);
    }
}
