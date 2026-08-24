{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
@props(['work', 'categoryId' => null])

{{--
    La carátula de una obra en la fila de "Colección". El espejo de
    components/movie/poster, con dos diferencias que vienen de los datos:

    - La portada es una URL remota (Google Books o Amazon), no una ruta de
      TMDB, así que pasa por el proxy de arte igual que en el listado.
      `coverAtLeast(300)` pide el peldaño más pequeño que sirva: la ficha
      guarda hasta 2177 px y aquí se pinta a 90.
    - El enlace lo calcula el componente Livewire y viaja en `enlace`, porque
      depende del torrent: un libro va a su página de similares y un
      audiolibro sin ISBN de obra va a su torrent, que es lo único que tiene.
--}}
@php($portada = method_exists($work, 'coverAtLeast') ? ($work->coverAtLeast(300) ?? $work->cover_url) : $work->cover_url)
@php($anyo = $work->first_publish_year ?? substr((string) ($work->release_date ?? ''), 0, 4))

{{-- Sin `enlace` --el bloque de "también descargaron" no tiene torrent a
     mano-- se arma el de similares, que necesita saber la categoría. --}}
@php($destino = $work->enlace
    ?? ($categoryId && $work->isbn13
        ? route('torrents.similar', ['category_id' => $categoryId, 'tmdb' => $work->isbn13])
        : '#'))

<article class="torrent-search--poster__result">
    <figure>
        <a href="{{ $destino }}" class="torrent-search--poster__poster">
            <img
                src="{{ $portada ? tmdb_image('poster_mid', $portada) : 'https://via.placeholder.com/90x135' }}"
                alt="{{ __('torrent.similar') }}"
                loading="lazy"
            />
        </a>
        <figcaption class="torrent-search--poster__caption">
            <h2 class="torrent-search--poster__title">
                {{ $work->title ?? '' }}
            </h2>
            <h3 class="torrent-search--poster__release-date">
                <time>{{ $anyo }}</time>
            </h3>
        </figcaption>
    </figure>
</article>
