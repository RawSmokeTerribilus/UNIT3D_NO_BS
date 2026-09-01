{{--
    Pista que acompana al "sin resultados" del buscador de torrents.

    El buscador exige que TODAS las palabras casen (matchingStrategy: all), asi
    que una sola palabra de mas devuelve cero. Cuando la consulta trae varias
    palabras esa es casi siempre la causa, y se dice; si trae una o ninguna, el
    cero suele ser legitimo y lo util es contar donde busca.

    El estilo va en linea a proposito: .form__hint solo esta definido como
    hermano de un input, y meter SCSS nuevo obligaria a reconstruir el bundle
    de Vite en produccion para una linea de texto.
--}}
@php
    $tipQuery = trim($tipQuery ?? '');
    $tipWordCount = $tipQuery === '' ? 0 : \count(preg_split('/\s+/', $tipQuery));
@endphp

<p
    class="torrent-search__tip"
    style="margin-top: 8px; color: var(--label-fg); font-size: 12px;"
>
    @if ($tipWordCount > 1)
        {{ __('torrent.search-tip-fewer-words') }}
    @else
        {{ __('torrent.search-tip-scope') }}
    @endif
</p>
