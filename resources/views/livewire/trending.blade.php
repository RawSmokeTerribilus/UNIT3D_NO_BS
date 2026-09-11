<section
    @class([
        'panelV2',
        'trending',
        'trending--weekly' => in_array($this->interval, ['weekly', 'monthly']),
    ])
>
    <header class="panel__header">
        <h2 class="panel__heading">{{ __('common.trending') }}</h2>
        <div class="panel__actions">
            <div class="panel__action">
                <div class="form__group">
                    <select
                        id="interval"
                        class="form__select"
                        type="date"
                        name="interval"
                        wire:model.live="interval"
                    >
                        <option value="day">Último día</option>
                        <option value="week">Última semana</option>
                        <option value="month">Último mes</option>
                        <option value="year">Último año</option>
                        <option value="all">Todo el tiempo</option>
                        <option value="weekly">Semanal</option>
                        <option value="monthly">Mensual</option>
                        <option value="release_year">Año de lanzamiento</option>
                        <option value="custom">Personalizado</option>
                    </select>
                    <label class="form__label form__label--floating" for="interval">Período</label>
                </div>
            </div>
            @if ($this->interval === 'custom')
                <div class="panel__action">
                    <div class="form__group">
                        <input
                            id="from"
                            class="form__text"
                            name="from"
                            type="date"
                            wire:model.live="from"
                        />
                        <label class="form__label form__label--floating" for="from">Desde</label>
                    </div>
                </div>
                <div class="panel__action">
                    <div class="form__group">
                        <input
                            id="until"
                            class="form__text"
                            name="until"
                            type="date"
                            wire:model.live="until"
                        />
                        <label class="form__label form__label--floating" for="until">Hasta</label>
                    </div>
                </div>
            @endif

            <div class="panel__action">
                <div class="form__group">
                    <select
                        id="metaType"
                        class="form__select"
                        name="metaType"
                        wire:model.live="metaType"
                    >
                        @foreach ($metaTypes as $name => $type)
                            <option value="{{ $type }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <label class="form__label form__label--floating" for="metaType">Categoría</label>
                </div>
            </div>
        </div>
    </header>
    @if ($this->interval === 'weekly')
        <div class="data-table-wrapper">
            <div wire:loading.delay class="panel__body">Calculando...</div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Rankings</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($works as $weeklyRankings)
                        <tr>
                            <th>
                                {{ $weeklyRankings->first()?->week_start?->format('Y-m-d') }}
                            </th>
                            <td class="panel__body trending-weekly__row">
                                @foreach ($weeklyRankings as $ranking)
                                    <figure class="trending-poster">
                                        <x-work.poster :fila="$ranking" :metaType="$this->metaType" />
                                        <figcaption
                                            class="trending-poster__download-count"
                                            title="{{ __('torrent.completed-times') }}"
                                        >
                                            {{ $ranking->download_count }}
                                        </figcaption>
                                    </figure>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($this->interval === 'monthly')
        <div class="data-table-wrapper">
            <div wire:loading.delay class="panel__body">Calculando...</div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Rankings</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($works as $monthlyRankings)
                        <tr>
                            <th>
                                {{ substr($monthlyRankings->first()?->the_year_month, 0, 4) }}-{{ substr($monthlyRankings->first()?->the_year_month, 4) }}
                            </th>
                            <td class="panel__body trending-weekly__row">
                                @foreach ($monthlyRankings as $ranking)
                                    <figure class="trending-poster">
                                        <x-work.poster :fila="$ranking" :metaType="$this->metaType" />
                                        <figcaption
                                            class="trending-poster__download-count"
                                            title="{{ __('torrent.completed-times') }}"
                                        >
                                            {{ $ranking->download_count }}
                                        </figcaption>
                                    </figure>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($this->interval === 'release_year')
        <div class="data-table-wrapper">
            <div wire:loading.delay class="panel__body">Calculando...</div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Año</th>
                        <th>Rankings</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($works as $releaseYearRankings)
                        <tr>
                            <th>{{ $releaseYearRankings->first()?->the_year }}</th>
                            <td class="panel__body trending-weekly__row">
                                @foreach ($releaseYearRankings as $ranking)
                                    <figure class="trending-poster">
                                        <x-work.poster :fila="$ranking" :metaType="$this->metaType" />
                                        <figcaption
                                            class="trending-poster__download-count"
                                            title="{{ __('torrent.completed-times') }}"
                                        >
                                            {{ $ranking->download_count }}
                                        </figcaption>
                                    </figure>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="panel__body torrent-search--poster__results">
            <div wire:loading.delay>Calculando...</div>

            @foreach ($works as $work)
                <figure class="trending-poster">
                    <x-work.poster :fila="$work" :metaType="$this->metaType" />
                    <figcaption
                        class="trending-poster__download-count"
                        title="{{ __('torrent.completed-times') }}"
                    >
                        {{ $work->download_count }}
                    </figcaption>
                </figure>
            @endforeach
        </div>
    @endif
</section>
