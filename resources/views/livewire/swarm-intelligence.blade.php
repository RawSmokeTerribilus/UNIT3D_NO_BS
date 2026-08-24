{{--
    NOBS — Nuclear Order Bit Syndicate

    Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>

    Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
    (HDInnovations) del que hereda la licencia.

    @project    NOBS — https://nobs.rawsmoke.net
    @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
--}}
<div class="panelV2" x-data="toggle">
    <h2 class="panel__heading" style="cursor: pointer" x-on:click="toggle">
        <i class="{{ config('other.font-awesome') }} fa-satellite-dish"></i>
        Swarm Intel
        <i class="{{ config('other.font-awesome') }} fa-plus-circle fa-pull-right" x-show="isToggledOff"></i>
        <i class="{{ config('other.font-awesome') }} fa-minus-circle fa-pull-right" x-show="isToggledOn" x-cloak></i>
    </h2>

    <div class="panel__body" x-show="isToggledOn" x-cloak>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">

            <div style="flex: 1; min-width: 100px; text-align: center;">
                <div style="font-size: 1.75rem; font-weight: 700;">{{ $stats['active'] }}</div>
                <div style="font-size: 1rem; font-weight: 700; opacity: .7;">Active</div>
            </div>

            <div style="flex: 1; min-width: 100px; text-align: center;">
                <div style="font-size: 1.75rem; font-weight: 700; color: var(--success);">{{ $stats['seeders'] }}</div>
                <div style="font-size: 1rem; font-weight: 700; opacity: .7;">Seeders</div>
            </div>

            <div style="flex: 1; min-width: 100px; text-align: center;">
                <div style="font-size: 1.75rem; font-weight: 700; color: var(--warning);">{{ $stats['leechers'] }}</div>
                <div style="font-size: 1rem; font-weight: 700; opacity: .7;">Leechers</div>
            </div>

            <div style="flex: 1; min-width: 100px; text-align: center;">
                <div style="font-size: 1.75rem; font-weight: 700; color: {{ $stats['stale'] > 0 ? 'var(--danger)' : 'inherit' }};">{{ $stats['stale'] }}</div>
                <div style="font-size: 1rem; font-weight: 700; opacity: .7;">Stale (>2h)</div>
            </div>

            @if ($stats['avg_leech_progress'] !== null)
                <div style="flex: 1; min-width: 100px; text-align: center;">
                    <div style="font-size: 1.75rem; font-weight: 700;">{{ $stats['avg_leech_progress'] }}%</div>
                    <div style="font-size: 1rem; font-weight: 700; opacity: .7;">Avg leech</div>
                </div>
            @endif

            @if ($stats['active'] > 0)
                <div style="flex: 1; min-width: 160px; text-align: center;">
                    <div style="height: 10px; border-radius: 4px; background: var(--warning); overflow: hidden; margin-bottom: .4rem;">
                        <div style="width: {{ $stats['health_pct'] }}%; height: 100%; background: var(--success);"></div>
                    </div>
                    <div style="font-size: 1.25rem; font-weight: 700;">{{ $stats['health_pct'] }}% seeded</div>
                </div>
            @endif

            @foreach ($topAgents as $agent)
                <div style="flex: 1; min-width: 100px; text-align: center;">
                    <div style="font-size: 1.75rem; font-weight: 700;">{{ $agent->cnt }}</div>
                    <div style="font-size: .85rem; font-weight: 700; opacity: .7;">{{ $agent->agent ?: 'Unknown' }}</div>
                </div>
            @endforeach

        </div>
    </div>
</div>
