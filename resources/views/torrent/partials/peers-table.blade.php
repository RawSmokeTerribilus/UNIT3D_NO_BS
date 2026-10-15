{{-- Tabla de pares. Recibe $rows; usa $torrent, $isStaff y $hasTracker de la vista. --}}
<div class="data-table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('common.user') }}</th>
                <th>{{ __('torrent.progress') }}</th>
                <th>{{ __('common.upload') }}</th>
                <th>{{ __('common.download') }}</th>
                <th>{{ __('torrent.left') }}</th>
                <th>{{ __('torrent.client') }}</th>
                <th>{{ __('common.ip') }}</th>
                <th>{{ __('common.port') }}</th>
                @if (\config('announce.connectable_check') == true)
                    <th>Conectable</th>
                @endif

                <th>{{ __('torrent.started') }}</th>
                <th>{{ __('torrent.last-update') }}</th>
                <th>{{ __('common.status') }}</th>
                <th>Visible</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $peer)
                <tr>
                    <td>
                        <x-user-tag
                            :user="$peer->user"
                            :anon="
                                $peer->user->privacy?->hidden
                                || $peer->user->privacy?->show_peer === 0
                                || ($peer->user->id == $torrent->user->id && $torrent->anon == 1)
                            "
                        />
                    </td>
                    <td>{{ $peer->progress }}%</td>
                    <td class="text-green">
                        {{ \App\Helpers\StringHelper::formatBytes($peer->uploaded, 2) }}
                    </td>
                    <td class="text-red">
                        {{ \App\Helpers\StringHelper::formatBytes($peer->downloaded, 2) }}
                    </td>
                    <td>{{ \App\Helpers\StringHelper::formatBytes($peer->left, 2) }}</td>
                    <td>{{ $peer->agent }}</td>

                    @if (auth()->user()->group->is_modo || auth()->id() == $peer->user_id)
                        <td>{{ $peer->ip }}</td>
                        <td>{{ $peer->port }}</td>
                    @else
                        <td>---</td>
                        <td>---</td>
                    @endif
                    @if (\config('announce.connectable_check') == true)
                        @php
                            $connectable = false;
                            if (config('announce.external_tracker.is_enabled')) {
                                $connectable = $peer->connectable;
                            } elseif (cache()->has('peers:connectable:' . $peer->ip . '-' . $peer->port . '-' . $peer->agent)) {
                                $connectable = cache()->get('peers:connectable:' . $peer->ip . '-' . $peer->port . '-' . $peer->agent);
                            }
                        @endphp

                        <td class="{{ $connectable ? 'text-green' : 'text-red' }}">
                            @choice('user.client-connectable-state', $connectable)
                        </td>
                    @endif

                    <td>
                        <time
                            datetime="{{ $peer->created_at }}"
                            title="{{ $peer->created_at }} UTC"
                        >
                            {{ $peer->created_at ? $peer->created_at->diffForHumans() : 'N/A' }}
                        </time>
                    </td>
                    <td>
                        <time
                            datetime="{{ $peer->updated_at }}"
                            title="{{ $peer->updated_at }} UTC"
                        >
                            {{ $peer->updated_at ? $peer->updated_at->diffForHumans() : 'N/A' }}
                        </time>
                    </td>
                    @if ($peer->active)
                        <td class="{{ $peer->seeder ? 'text-green' : 'text-red' }}">
                            {{ $peer->seeder ? __('torrent.seeder') : __('torrent.leecher') }}
                            @if ($peer->tracker_state === 'inactive')
                                <span
                                    class="text-orange"
                                    title="El announce ya lo da por caducado; la web se pondrá al día en el próximo «Limpiar peers viejos» (cada hora)"
                                >
                                    · caducado en el tracker
                                </span>
                            @elseif ($peer->tracker_state === 'absent')
                                <span
                                    class="text-orange"
                                    title="El announce no tiene este par en memoria: no cuenta para seeders ni para el límite de 3"
                                >
                                    · no está en el tracker
                                </span>
                            @endif
                        </td>
                    @elseif ($peer->tracker_state === 'inactive')
                        <td
                            class="text-orange"
                            title="Dejó de anunciar sin mandar «stopped» (cliente cerrado de golpe, reiniciado o sin red). No cuenta para seeders ni para el límite de 3; el announce lo guarda 21 días por si vuelve con el mismo peer_id"
                        >
                            Fantasma
                            @if ($peer->single_announce)
                                <span class="text-muted">· 1 anuncio</span>
                            @endif
                        </td>
                    @elseif ($peer->tracker_state === 'absent')
                        <td
                            class="text-muted"
                            title="El cliente lo paró bien («stopped») o el announce ya lo purgó. La fila sigue aquí hasta 2 días como histórico"
                        >
                            Parado
                            @if ($peer->single_announce)
                                · 1 anuncio
                            @endif
                        </td>
                    @else
                        <td
                            class="text-orange"
                            title="No cuenta para seeders ni para el límite de 3. La fila se borra a los 2 días"
                        >
                            Inactivo
                        </td>
                    @endif
                    <td class="{{ $peer->visible ? 'text-green' : 'text-red' }}">
                        @if ($peer->visible)
                            {{ __('common.yes') }}
                        @else
                            {{ __('common.no') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
