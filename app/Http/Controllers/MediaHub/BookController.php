<?php

declare(strict_types=1);

namespace App\Http\Controllers\MediaHub;

use App\Http\Controllers\Controller;
use App\Models\BookAuthor;
use App\Models\BookGenre;
use App\Models\BookNarrator;
use App\Models\BookPublisher;
use App\Models\BookSeries;

/**
 * Los hubs del catálogo de libros.
 *
 * Autores, géneros, editoriales y sagas cuentan libros Y audiolibros a la vez,
 * a propósito: un autor es el mismo lo lea quien lo lea. Separarlos daría dos
 * hubs con la mitad del catálogo cada uno. Sólo el de narradores es exclusivo
 * de audiolibro, porque es lo único que no existe en papel.
 */
class BookController extends Controller
{
    /**
     * Todos los autores con ficha, los más prolíficos primero.
     */
    public function authors(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('mediahub.book.author', [
            'authors' => BookAuthor::query()
                ->withCount(['books', 'audiobooks'])
                ->having('books_count', '>', 0)
                ->orHaving('audiobooks_count', '>', 0)
                ->orderByDesc('books_count')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function narrators(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('mediahub.book.narrator', [
            'narrators' => BookNarrator::query()
                ->withCount('audiobooks')
                ->orderByDesc('audiobooks_count')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function genres(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('mediahub.book.genre', [
            'genres' => BookGenre::query()
                ->withCount(['books', 'audiobooks'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function publishers(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('mediahub.book.publisher', [
            'publishers' => BookPublisher::query()
                ->withCount(['books', 'audiobooks'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function series(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return view('mediahub.book.series', [
            'series' => BookSeries::query()
                ->withCount(['books', 'audiobooks'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
