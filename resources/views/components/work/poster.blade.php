{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}

{{--
    La portada de una fila de "tendencias", sea de la clase que sea.

    Antes cada una de las cuatro listas de la página llevaba su propio @switch
    con película y serie escritas a mano. Añadir libros, audiolibros y juegos
    habría sido repetir doce bloques casi idénticos, así que la decisión vive
    aquí y las listas sólo dicen a quién pintan.

    `$fila` es un torrent con su relación ya cargada y con `category_id`
    calculado por la consulta; `$metaType` es la clase que se está mirando.
--}}
@props(['fila', 'metaType'])

@switch($metaType)
    @case('tv_meta')
        <x-tv.poster :tv="$fila->tv" :categoryId="$fila->category_id" :tmdb="$fila->tmdb_tv_id" />

        @break
    @case('game_meta')
        <x-game.poster :game="$fila->game" :categoryId="$fila->category_id" :igdb="$fila->igdb" />

        @break
    @case('book_meta')
        <x-book.poster :work="$fila->book" :categoryId="$fila->category_id" />

        @break
    @case('audiobook_meta')
        {{-- Una lectura libre no tiene ficha de audiolibro, pero sí la del libro
             que lee: se prueba la propia y se cae a la otra, igual que hacen la
             ficha del torrent y la página de similares. --}}
        <x-book.poster :work="$fila->audiobook ?? $fila->book" :categoryId="$fila->category_id" />

        @break
    @default
        <x-movie.poster :movie="$fila->movie" :categoryId="$fila->category_id" :tmdb="$fila->tmdb_movie_id" />
@endswitch
