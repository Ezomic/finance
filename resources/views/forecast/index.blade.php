@extends('layouts.app')
@section('title', 'Forecast')
@section('subtitle', 'Projected balance based on bills and recurring income/expenses')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div class="text-sm text-ink/50">
        Starting from {{ number_format($startBalance, 2) }}, including an estimated {{ number_format($discretionaryDaily, 2) }}/day for everyday spending not covered by a bill or recurring charge.
    </div>
    <div class="flex gap-2 text-sm">
        @foreach ([30, 60, 90] as $option)
            <a href="{{ route('forecast.index', ['days' => $option]) }}" class="px-3 py-1.5 rounded-full {{ $days === $option ? 'bg-moss-100 text-moss-900 font-medium' : 'text-ink/60 hover:bg-moss-50' }}">{{ $option }} days</a>
        @endforeach
    </div>
</div>

<div class="card p-6 mb-6">
    <h2 class="font-display text-lg font-semibold mb-4">Projected balance</h2>
    <canvas id="forecastChart" height="220"></canvas>
</div>

<div class="card overflow-hidden">
    <div class="px-4 py-3 border-b border-moss-100">
        <h2 class="font-display text-lg font-semibold">Known upcoming events</h2>
    </div>
    @if ($events->isEmpty())
        <div class="p-10 text-center text-ink/50 text-sm">No bills or recurring charges expected in this window.</div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-moss-50 text-left text-xs uppercase tracking-wide text-ink/50">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-moss-100">
                @foreach ($events as $event)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $event['date']->format('M j, Y') }}</td>
                        <td class="px-4 py-3">{{ $event['label'] }}</td>
                        <td class="px-4 py-3 text-ink/50 capitalize">{{ $event['type'] }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $event['amount'] < 0 ? 'text-clay' : 'text-moss-700' }}">
                            {{ $event['amount'] < 0 ? '-' : '+' }}{{ number_format(abs($event['amount']), 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@vite(['resources/js/charts.js'])
<script>
document.addEventListener('DOMContentLoaded', () => {
    const points = @json($points->map(fn ($p) => ['label' => $p['date']->format('M j'), 'balance' => $p['balance']]));
    new Chart(document.getElementById('forecastChart'), {
        type: 'line',
        data: {
            labels: points.map(p => p.label),
            datasets: [{
                label: 'Projected balance',
                data: points.map(p => p.balance),
                borderColor: '#3E6339',
                backgroundColor: 'rgba(78,122,72,0.12)',
                fill: true,
                tension: 0.3,
                pointRadius: 0,
            }],
        },
        options: { plugins: { legend: { display: false } } },
    });
});
</script>
@endsection
