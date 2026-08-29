@extends('layout.with-main')

@section('breadcrumbs')
    <li class="breadcrumbV2">
        <a href="{{ route('staff.dashboard.index') }}" class="breadcrumb__link">
            {{ __('staff.staff-dashboard') }}
        </a>
    </li>
    <li class="breadcrumb--active">Donations</li>
@endsection

@section('page', 'page__staff-donation--index')

@section('main')
    @php
        // El importe se pintaba con un «$» escrito a mano, así que el panel decía
        // dólares mientras la pasarela cobra en euros. Sale de la config, igual que
        // en la página pública de tramos.
        //
        // Ojo: dentro de @php estamos en contexto PHP, los comentarios van con //
        // o /* */, nunca con llaves de Blade.
        $moneda = config('donation.currency');
        $importe = fn ($coste) => number_format((float) $coste, 2, ',', '.').' '.($moneda === 'EUR' ? '€' : $moneda);
    @endphp

    {{-- La tabla va ARRIBA y las gráficas debajo. Antes era al revés y había que
         bajar mil píxeles de lienzos para llegar a lo único que pide una acción.
         Las pendientes salen además las primeras dentro de la tabla. --}}
    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">
                Donaciones
                @if ($pendingCount > 0)
                    — <span class="text-warning">{{ $pendingCount }} pendiente{{ $pendingCount === 1 ? '' : 's' }}</span>
                    <x-animation.notification />
                @endif
            </h2>
        </header>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Transaction</th>
                        <th>Cost</th>
                        <th>Upload #</th>
                        <th>Invite #</th>
                        <th>Bonus #</th>
                        <th>Length</th>
                        <th>Status</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($donations as $donation)
                        {{-- Fondo tenue en las pendientes: con la tabla ordenada ya
                             salen arriba, pero al pasar de página conviene que se
                             distingan sin leer la columna de estado. --}}
                        <tr
                            @if ($donation->status === App\Enums\ModerationStatus::PENDING)
                                style="background: rgba(235, 170, 60, 0.08)"
                            @endif
                        >
                            <td>{{ $donation->created_at }}</td>
                            <td>
                                <x-user-tag :user="$donation->user" :anon="false" />
                            </td>
                            <td style="max-width: 80ch; word-wrap: break-word; white-space: normal">
                                {{ $donation->transaction }}
                            </td>
                            <td
                                class="{{ $donation->package->trashed() ? 'text-danger' : '' }}"
                                title="{{ $donation->package->trashed() ? 'Package has been deleted' : '' }}"
                            >
                                {{ $importe($donation->package->cost) }}
                            </td>
                            <td
                                class="{{ $donation->package->trashed() ? 'text-danger' : '' }}"
                                title="{{ $donation->package->trashed() ? 'Package has been deleted' : '' }}"
                            >
                                {{ App\Helpers\StringHelper::formatBytes($donation->package->upload_value ?? 0) }}
                            </td>
                            <td
                                class="{{ $donation->package->trashed() ? 'text-danger' : '' }}"
                                title="{{ $donation->package->trashed() ? 'Package has been deleted' : '' }}"
                            >
                                {{ $donation->package->invite_value ?? 0 }}
                            </td>
                            <td
                                class="{{ $donation->package->trashed() ? 'text-danger' : '' }}"
                                title="{{ $donation->package->trashed() ? 'Package has been deleted' : '' }}"
                            >
                                {{ $donation->package->bonus_value ?? 0 }}
                            </td>
                            <td
                                class="{{ $donation->package->trashed() ? 'text-danger' : '' }}"
                                title="{{ $donation->package->trashed() ? 'Package has been deleted' : '' }}"
                            >
                                @if ($donation->package->donor_value === null)
                                    Lifetime
                                @else
                                    {{ $donation->package->donor_value }} days
                                @endif
                            </td>
                            <td>
                                @if ($donation->status === App\Enums\ModerationStatus::PENDING)
                                    <span class="text-warning">Pending</span>
                                @elseif ($donation->status === App\Enums\ModerationStatus::APPROVED)
                                    <span class="text-success">Approved</span>
                                @else
                                    <span class="text-danger">Rejected</span>
                                @endif
                            </td>

                            <td>
                                @if ($donation->status === App\Enums\ModerationStatus::PENDING)
                                    <menu class="data-table__actions">
                                        <li class="data-table__action">
                                            <form
                                                action="{{ route('staff.donations.update', ['donation' => $donation]) }}"
                                                method="POST"
                                                x-data="confirmation"
                                            >
                                                @csrf
                                                <button
                                                    x-on:click.prevent="confirmAction"
                                                    data-b64-deletion-message="{{ base64_encode('Are you sure you want to approve this donation: ' . $donation->id . '?') }}"
                                                    class="form__button form__button--filled"
                                                >
                                                    Approve
                                                </button>
                                            </form>
                                        </li>

                                        <li class="data-table__action">
                                            <form
                                                action="{{ route('staff.donations.destroy', ['donation' => $donation]) }}"
                                                method="POST"
                                                x-data="confirmation"
                                            >
                                                @csrf
                                                <button
                                                    x-on:click.prevent="confirmAction"
                                                    data-b64-deletion-message="{{ base64_encode('Are you sure you want to reject this donation: ' . $donation->id . '?') }}"
                                                    class="form__button form__button--filled"
                                                >
                                                    Reject
                                                </button>
                                            </form>
                                        </li>
                                    </menu>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $donations->links('partials.pagination') }}
    </section>

    {{-- Alturas FIJAS en los contenedores, y `maintainAspectRatio: false` abajo.
         Sin las dos cosas el lienzo se estira con el flex y una sola barra ocupaba
         ochocientos píxeles de alto. No quitar una sin quitar la otra.

         Antes había tres gráficas y una sobraba: la mensual a secas enseñaba los
         mismos datos que la del objetivo pero sin el objetivo. Quedan dos. --}}
    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">Estadísticas</h2>
        </header>
        <div class="chart-wrapper">
            <div style="height: 260px; min-width: 0">
                <canvas id="dailyDonationsChart"></canvas>
            </div>
            <div style="height: 260px; min-width: 0">
                <canvas id="goalDonationsChart"></canvas>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    @vite('resources/js/vendor/chart.js')
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        document.addEventListener('DOMContentLoaded', function () {
            const dailyDonations = {{ Js::from($dailyDonations) }};
            const monthlyDonations = {{ Js::from($monthlyDonations) }};
            const monthlyGoal = {{ Js::from($monthlyGoal) }};

            // Colores literales y no por variable CSS a propósito: las
            // `--donation-chart-*` viven en los ficheros de tema y añadir más
            // obligaría a un build del frontend entero. Este bloque es script
            // inline con nonce, así que no pasa por Vite.
            const VERDE  = 'rgba(80, 200, 140, 0.75)';
            const AMBAR  = 'rgba(235, 170, 60, 0.75)';
            const MALVA  = 'rgba(153, 102, 255, 0.9)';
            const REJILLA = 'rgba(255, 255, 255, 0.06)';
            const TINTA   = 'rgba(255, 255, 255, 0.55)';

            // Común a las dos. `maintainAspectRatio: false` es lo que hace que el
            // lienzo respete la altura del contenedor en vez de estirarse.
            const base = (extra) => Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: TINTA, boxWidth: 12, padding: 12 } },
                },
            }, extra || {});

            const ejeY = {
                beginAtZero: true,
                grid: { color: REJILLA },
                ticks: { color: TINTA, callback: (v) => v + ' €' },
            };

            // Diarias. Con pocos datos una línea sin puntos visibles es un lienzo
            // vacío, así que el punto se ve y se rellena el área.
            new Chart(document.getElementById('dailyDonationsChart'), {
                type: 'line',
                data: {
                    labels: dailyDonations.map((d) => d.date),
                    datasets: [{
                        label: 'Por día',
                        data: dailyDonations.map((d) => d.total),
                        borderColor: MALVA,
                        backgroundColor: 'rgba(153, 102, 255, 0.15)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointBackgroundColor: MALVA,
                        fill: true,
                        tension: 0.25,
                    }],
                },
                options: base({
                    scales: {
                        x: { type: 'time', time: { unit: 'day' }, grid: { color: REJILLA }, ticks: { color: TINTA } },
                        y: ejeY,
                    },
                }),
            });

            // Mes contra objetivo. Verde si llega, ámbar si no.
            new Chart(document.getElementById('goalDonationsChart'), {
                data: {
                    labels: monthlyDonations.map(
                        (d) => `${d.year}-${String(d.month).padStart(2, '0')}`,
                    ),
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Recaudado',
                            data: monthlyDonations.map((d) => d.total),
                            backgroundColor: monthlyDonations.map((d) =>
                                Number(d.total) >= monthlyGoal ? VERDE : AMBAR,
                            ),
                            borderWidth: 0,
                            // Sin esto una sola barra ocupa todo el ancho del panel.
                            maxBarThickness: 64,
                            order: 2,
                        },
                        {
                            type: 'line',
                            label: `Objetivo (${monthlyGoal} €)`,
                            data: monthlyDonations.map(() => monthlyGoal),
                            borderColor: MALVA,
                            borderWidth: 2,
                            borderDash: [6, 4],
                            pointRadius: 0,
                            fill: false,
                            order: 1,
                        },
                    ],
                },
                options: base({
                    scales: {
                        x: { type: 'category', grid: { display: false }, ticks: { color: TINTA } },
                        // Deja aire por encima del objetivo para que la línea nunca
                        // quede pegada al borde y se pueda leer.
                        y: Object.assign({ suggestedMax: monthlyGoal * 1.15 }, ejeY),
                    },
                    plugins: {
                        legend: { labels: { color: TINTA, boxWidth: 12, padding: 12 } },
                        tooltip: {
                            callbacks: {
                                // La línea es el objetivo de HOY dibujado sobre meses
                                // pasados: no hay histórico de cómo fue cambiando.
                                afterBody: (items) =>
                                    items[0].datasetIndex === 1
                                        ? 'Objetivo vigente ahora, no el que hubiera ese mes'
                                        : '',
                            },
                        },
                    },
                }),
            });
        });
    </script>
@endsection
