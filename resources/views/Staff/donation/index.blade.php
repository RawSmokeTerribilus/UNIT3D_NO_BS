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
    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">Donation statistics</h2>
        </header>
        <div class="chart-wrapper">
            <div>
                <canvas id="dailyDonationsChart"></canvas>
            </div>
            <div>
                <canvas id="monthlyDonationsChart"></canvas>
            </div>
        </div>
    </section>

    {{-- Histórico contra el objetivo. Va aparte y no como tercera columna de las de
         arriba porque necesita el ancho: con doce meses en el eje, apretada no se lee.
         El objetivo sale del ajuste vivo, no escrito a mano, para que mover la meta
         desde /dashboard/config mueva también la línea. --}}
    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">Histórico contra el objetivo</h2>
        </header>
        <div class="chart-wrapper">
            <div>
                <canvas id="goalDonationsChart"></canvas>
            </div>
        </div>
    </section>

    <section class="panelV2">
        <header class="panel__header">
            <h2 class="panel__heading">Donations</h2>
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
                        <tr>
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
                                $ {{ $donation->package->cost }}
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
@endsection

@section('scripts')
    @vite('resources/js/vendor/chart.js')
    <script nonce="{{ HDVinnie\SecureHeaders\SecureHeaders::nonce('script') }}">
        document.addEventListener('DOMContentLoaded', function () {
            const dailyDonations = {{ Js::from($dailyDonations) }};
            const monthlyDonations = {{ Js::from($monthlyDonations) }};

            // Daily donations chart
            const dailyCtx = document.getElementById('dailyDonationsChart').getContext('2d');
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: dailyDonations.map((donation) => donation.date),
                    datasets: [
                        {
                            label: 'Daily donations',
                            data: dailyDonations.map((donation) => donation.total),
                            backgroundColor: getComputedStyle(
                                document.documentElement,
                            ).getPropertyValue('--donation-chart-daily-bg'),
                            borderColor: getComputedStyle(
                                document.documentElement,
                            ).getPropertyValue('--donation-chart-daily-border'),
                            borderWidth: 1,
                            fill: false,
                        },
                    ],
                },
                options: {
                    scales: {
                        x: { type: 'time', time: { unit: 'day' } },
                        y: { beginAtZero: true },
                    },
                },
            });

            // Monthly donations chart
            const monthlyCtx = document.getElementById('monthlyDonationsChart').getContext('2d');
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyDonations.map(
                        (donation) => `${donation.year}-${donation.month}`,
                    ),
                    datasets: [
                        {
                            label: 'Monthly donations',
                            data: monthlyDonations.map((donation) => donation.total),
                            backgroundColor: getComputedStyle(
                                document.documentElement,
                            ).getPropertyValue('--donation-chart-monthly-bg'),
                            borderColor: getComputedStyle(
                                document.documentElement,
                            ).getPropertyValue('--donation-chart-monthly-border'),
                            borderWidth: 1,
                            fill: false,
                        },
                    ],
                },
                options: {
                    scales: {
                        x: { type: 'category' },
                        y: { beginAtZero: true },
                    },
                },
            });

            // Histórico contra el objetivo.
            //
            // Reutiliza el mismo dataset que la gráfica de arriba: no hace falta otra
            // consulta, lo único que añade es el contexto de si el mes llegó o no.
            //
            // Los colores van literales y no por variable CSS a propósito: las
            // `--donation-chart-*` viven en los ficheros de tema y añadir dos nuevas
            // obligaría a un build del frontend entero por dos rgba. Este bloque es
            // script inline con nonce, así que no pasa por Vite.
            const monthlyGoal = {{ Js::from($monthlyGoal) }};
            const VERDE = 'rgba(75, 192, 120, 0.55)';
            const AMBAR = 'rgba(235, 170, 60, 0.55)';

            const goalCtx = document.getElementById('goalDonationsChart').getContext('2d');
            new Chart(goalCtx, {
                data: {
                    labels: monthlyDonations.map(
                        (donation) => `${donation.year}-${String(donation.month).padStart(2, '0')}`,
                    ),
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Recaudado en el mes',
                            data: monthlyDonations.map((donation) => donation.total),
                            backgroundColor: monthlyDonations.map((donation) =>
                                Number(donation.total) >= monthlyGoal ? VERDE : AMBAR,
                            ),
                            borderWidth: 0,
                            order: 2,
                        },
                        {
                            type: 'line',
                            label: `Objetivo actual (${monthlyGoal} €)`,
                            data: monthlyDonations.map(() => monthlyGoal),
                            borderColor: getComputedStyle(
                                document.documentElement,
                            ).getPropertyValue('--donation-chart-monthly-border'),
                            borderWidth: 2,
                            borderDash: [6, 4],
                            pointRadius: 0,
                            fill: false,
                            order: 1,
                        },
                    ],
                },
                options: {
                    scales: {
                        x: { type: 'category' },
                        y: { beginAtZero: true, title: { display: true, text: '€' } },
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                // La línea es el objetivo de HOY dibujado sobre meses
                                // pasados. Si la meta cambia, los meses viejos se
                                // juzgan con la vara nueva — se avisa aquí en vez de
                                // fingir que teníamos este objetivo desde siempre.
                                afterBody: (items) =>
                                    items[0].datasetIndex === 1
                                        ? 'Objetivo vigente ahora, no el que hubiera ese mes'
                                        : '',
                            },
                        },
                    },
                },
            });
        });
    </script>
@endsection
