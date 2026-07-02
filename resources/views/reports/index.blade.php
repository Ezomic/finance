@extends('layouts.app')
@section('title', 'Reports')
@section('subtitle', 'The shape of the household\'s money')
@section('content')

<div class="grid gap-6 lg:grid-cols-2 mb-6">
    <div class="card p-6">
        <h2 class="font-display text-lg font-semibold mb-4">Net worth, last 12 months</h2>
        <canvas id="netWorthChart" height="220"></canvas>
    </div>
    <div class="card p-6">
        <h2 class="font-display text-lg font-semibold mb-4">Spending by category, this month</h2>
        @if ($spendingByCategory->isEmpty())
            <p class="text-sm text-ink/50">No expenses recorded this month yet.</p>
        @else
            <canvas id="categoryChart" height="220"></canvas>
        @endif
    </div>
</div>

<div class="card p-6">
    <h2 class="font-display text-lg font-semibold mb-4">Income vs. expense, last 6 months</h2>
    <canvas id="incomeExpenseChart" height="220"></canvas>
</div>

@vite(['resources/js/charts.js'])
<script>
document.addEventListener('DOMContentLoaded', () => {
const netWorthData = @json($netWorthOverTime);
new Chart(document.getElementById('netWorthChart'), {
    type: 'line',
    data: {
        labels: netWorthData.map(d => d.label),
        datasets: [{
            label: 'Net worth',
            data: netWorthData.map(d => d.total),
            borderColor: '#3E6339',
            backgroundColor: 'rgba(78,122,72,0.12)',
            fill: true,
            tension: 0.3,
        }],
    },
    options: { plugins: { legend: { display: false } } },
});

@if ($spendingByCategory->isNotEmpty())
const categoryData = @json($spendingByCategory);
const labels = Object.keys(categoryData);
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: labels,
        datasets: [{
            data: labels.map(l => categoryData[l].total),
            backgroundColor: labels.map(l => categoryData[l].color),
        }],
    },
});
@endif

const ieData = @json($incomeVsExpense);
new Chart(document.getElementById('incomeExpenseChart'), {
    type: 'bar',
    data: {
        labels: ieData.map(d => d.label),
        datasets: [
            { label: 'Income', data: ieData.map(d => d.income), backgroundColor: '#4E7A48' },
            { label: 'Expense', data: ieData.map(d => d.expense), backgroundColor: '#B4602F' },
        ],
    },
});
});
</script>
@endsection
