<x-app-layout>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Expense Summary</h1>
        <a href="{{ route('expenses.index') }}" class="flex items-center gap-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            Back to Expenses
        </a>
    </div>

    <p class="text-sm text-gray-500">Since {{ $start->format('M Y') }} &mdash; farm-wide + per-building, all categories.</p>

    {{-- Top stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50]">
            <div class="text-gray-600 text-sm mb-1">Total Expenses</div>
            <div class="text-3xl font-bold text-gray-800">₱{{ number_format($totalAmount, 2) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-400">
            <div class="text-gray-600 text-sm mb-1">Estimated Share</div>
            <div class="text-3xl font-bold text-gray-800">
                {{ $totalAmount > 0 ? number_format(($estimatedAmount / $totalAmount) * 100, 1) : 0 }}%
            </div>
            <div class="text-xs text-gray-500 mt-1">₱{{ number_format($estimatedAmount, 2) }} of total is a labeled estimate, not a receipt.</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-400">
            <div class="text-gray-600 text-sm mb-1">Recurring Regimens (est. monthly)</div>
            <div class="text-3xl font-bold text-gray-800">₱{{ number_format($recurring->sum('est_monthly'), 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $recurring->count() }} ongoing regimen(s) — projected, not a receipted total.</div>
        </div>
    </div>

    {{-- By Category --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">By Category</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 text-sm font-semibold text-gray-700">Category</th>
                        <th class="text-right py-2 text-sm font-semibold text-gray-700">Total</th>
                        <th class="text-right py-2 text-sm font-semibold text-gray-700">Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byCategory as $cat => $amount)
                        <tr class="border-b last:border-0">
                            <td class="py-2 text-sm text-gray-700">{{ \App\Models\Expense::CATEGORIES[$cat]['label'] }}</td>
                            <td class="text-right py-2 text-sm font-semibold">₱{{ number_format($amount, 2) }}</td>
                            <td class="text-right py-2 text-sm text-gray-500">{{ $totalAmount > 0 ? number_format(($amount / $totalAmount) * 100, 1) : 0 }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- By Month --}}
    <div class="bg-white rounded-lg shadow-md p-6" data-month-chart="{{ $byMonth->map(fn($v, $k) => ['month' => $k, 'total' => $v['total'], 'estimated' => $v['estimated']])->values()->toJson() }}">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Monthly Total</h2>
        <canvas id="monthlyExpenseChart" height="100"></canvas>
        <div class="overflow-x-auto mt-4">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 text-sm font-semibold text-gray-700">Month</th>
                        <th class="text-right py-2 text-sm font-semibold text-gray-700">Total</th>
                        <th class="text-right py-2 text-sm font-semibold text-gray-700">Estimated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($byMonth as $month => $data)
                        <tr class="border-b last:border-0">
                            <td class="py-2 text-sm text-gray-700">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}</td>
                            <td class="text-right py-2 text-sm font-semibold">₱{{ number_format($data['total'], 2) }}</td>
                            <td class="text-right py-2 text-sm text-orange-600">₱{{ number_format($data['estimated'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-gray-400 text-sm">No expense records in this window.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recurring Regimens --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Recurring Regimens</h2>
        <p class="text-sm text-gray-500 mb-3">Ongoing weekly costs (e.g. Aminovit every Monday, Electrocare every Thursday) projected to a monthly figure — not one-off purchases.</p>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 text-sm font-semibold text-gray-700">Description</th>
                        <th class="text-left py-2 text-sm font-semibold text-gray-700">Recurrence</th>
                        <th class="text-right py-2 text-sm font-semibold text-gray-700">Per Occurrence</th>
                        <th class="text-right py-2 text-sm font-semibold text-gray-700">Est. Monthly</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recurring as $r)
                        <tr class="border-b last:border-0">
                            <td class="py-2 text-sm text-gray-700">{{ $r['description'] }}</td>
                            <td class="py-2 text-sm text-gray-500">{{ $r['recurrence'] ?? '—' }}</td>
                            <td class="text-right py-2 text-sm">₱{{ number_format($r['weekly_amount'], 2) }}</td>
                            <td class="text-right py-2 text-sm font-semibold text-blue-600">₱{{ number_format($r['est_monthly'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-gray-400 text-sm">No recurring regimens flagged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const container = document.querySelector('[data-month-chart]');
    const data       = JSON.parse(container?.getAttribute('data-month-chart') || '[]');
    const ctx        = document.getElementById('monthlyExpenseChart');
    if (ctx && data.length) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.month),
                datasets: [{
                    label: 'Monthly Total (₱)',
                    data: data.map(d => d.total),
                    backgroundColor: '#4CAF50',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => '₱' + (c.parsed.y ?? 0).toLocaleString() } }
                },
                scales: {
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } },
                    y: { ticks: { callback: v => '₱' + v.toLocaleString(), font: { size: 11 } }, beginAtZero: true }
                }
            }
        });
    } else if (ctx) {
        ctx.parentElement.innerHTML += '<p class="text-sm text-gray-400 text-center mt-8">No expense data yet.</p>';
    }
})();
</script>
</x-app-layout>
