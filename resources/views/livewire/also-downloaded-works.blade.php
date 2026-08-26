<section class="panelV2">
    <header class="panel__header">
        <h2 class="panel__heading">Also downloaded</h2>
        <div class="panel__actions" x-data="posterRow">
            <div class="panel__action">
                <button class="form__standard-icon-button" x-bind="scrollLeft">
                    <i class="{{ \config('other.font-awesome') }} fa-angle-left"></i>
                </button>
            </div>
            <div class="panel__action">
                <button class="form__standard-icon-button" x-bind="scrollRight">
                    <i class="{{ \config('other.font-awesome') }} fa-angle-right"></i>
                </button>
            </div>
        </div>
    </header>
    <div
        class="panel__body collection-posters"
        x-ref="posters"
        style="max-height: 330px !important"
    >
        {{-- La categoría la pone cada obra, no la página: "TV" y "Anime TV
             Shows" (o "Movies" y "Anime Movies", o "E-Books" y "Audiobooks")
             comparten meta, así que heredar la de la ficha actual componía
             enlaces como /torrents/similar/5.48891 para una serie que sólo
             vive en la categoría 2 --404 seguro--. `category_id` viene del
             MIN() de la subconsulta, y por construcción es una categoría en
             la que esa obra sí tiene torrents. --}}
        @foreach ($alsoDownloadedWorks as $alsoDownloadedWork)
            <figure class="trending-poster">
                @switch($alsoDownloadedWork::class)
                    @case(\App\Models\TmdbMovie::class)
                        <x-movie.poster :movie="$alsoDownloadedWork" :categoryId="$alsoDownloadedWork->category_id ?? $categoryId" />

                        @break
                    @case(\App\Models\TmdbTv::class)
                        <x-tv.poster :tv="$alsoDownloadedWork" :categoryId="$alsoDownloadedWork->category_id ?? $categoryId" />

                        @break
                    @case(\App\Models\IgdbGame::class)
                        <x-game.poster :game="$alsoDownloadedWork" :categoryId="$alsoDownloadedWork->category_id ?? $categoryId" />

                        @break
                    @case(\App\Models\Book::class)
                    @case(\App\Models\Audiobook::class)
                        <x-book.poster :work="$alsoDownloadedWork" :categoryId="$alsoDownloadedWork->category_id ?? $categoryId" />

                        @break
                @endswitch
                <figcaption class="trending-poster__download-count" title="Times downloaded">
                    {{ $alsoDownloadedWork->total }}
                </figcaption>
            </figure>
        @endforeach
    </div>
</section>
