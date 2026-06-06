<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Reports</h1>

    {{-- === SECTION 1: DATE FILTER + EXPORTS === --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                <input type="date" name="start_date" value="{{ $startDate }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </div>
            <div class="flex-1 min-w-[160px]">
                <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                <input type="date" name="end_date" value="{{ $endDate }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Period</label>
                <select name="period" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                    @foreach(['Daily','Weekly','Monthly'] as $p)
                        <option value="{{ $p }}" {{ $period === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button type="submit" class="bg-[#4CAF50] text-white px-5 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Apply</button>
                <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:underline self-center">Reset</a>
            </div>
            <div class="flex gap-2 flex-wrap">
                @php $exportParams = http_build_query(['start_date' => $startDate, 'end_date' => $endDate, 'period' => $period]); @endphp
                <a href="{{ route('reports.export.pdf') . '?' . $exportParams }}"
                    class="flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    PDF
                </a>
                <a href="{{ route('reports.export.csv') . '?' . $exportParams }}"
                    class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    CSV
                </a>
            </div>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Summary</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Total Eggs Produced</div>
                <div class="text-2xl font-bold text-gray-800">{{ number_format($summary['total_eggs_produced']) }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Total Eggs Sold</div>
                <div class="text-2xl font-bold text-gray-800">{{ number_format($summary['total_eggs_sold']) }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Total Revenue</div>
                <div class="text-2xl font-bold text-[#4CAF50]">₱{{ number_format($summary['total_revenue'], 0) }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Avg Production Rate</div>
                <div class="text-2xl font-bold text-gray-800">{{ $summary['avg_production_rate'] }}%</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Avg Sales Rate</div>
                <div class="text-2xl font-bold text-gray-800">{{ $summary['avg_sales_rate'] }}%</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Remaining Eggs</div>
                <div class="text-2xl font-bold {{ $summary['remaining_eggs'] < 0 ? 'text-red-600' : 'text-gray-800' }}">
                    {{ number_format($summary['remaining_eggs']) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Trend Charts --}}
    @if($dailyData->isNotEmpty())
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Production Trend</h2>
            <canvas id="prodTrendChart" height="120"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Revenue Trend</h2>
            <canvas id="revTrendChart" height="120"></canvas>
        </div>
    </div>
    @endif

    {{-- Daily Breakdown Table --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Daily Breakdown</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Date</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Eggs Produced</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Eggs Sold</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Revenue</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Prod Rate</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dailyData as $row)
                        <tr class="border-b last:border-0 hover:bg-gray-50">
                            <td class="py-3 text-sm">{{ $row['date'] }}</td>
                            <td class="text-right py-3 text-sm">{{ number_format($row['eggs']) }}</td>
                            <td class="text-right py-3 text-sm">{{ number_format($row['sold']) }}</td>
                            <td class="text-right py-3 text-sm font-semibold text-[#4CAF50]">₱{{ number_format($row['revenue'], 2) }}</td>
                            <td class="text-right py-3 text-sm">{{ $row['prod_rate'] }}%</td>
                            <td class="text-right py-3 text-sm {{ ($row['eggs'] - $row['sold']) < 0 ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                                {{ number_format($row['eggs'] - $row['sold']) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No data for selected date range.</td></tr>
                    @endforelse
                </tbody>
                @if($dailyData->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 bg-gray-50">
                        <td class="py-3 text-sm font-bold text-gray-700">Total</td>
                        <td class="text-right py-3 text-sm font-bold">{{ number_format($summary['total_eggs_produced']) }}</td>
                        <td class="text-right py-3 text-sm font-bold">{{ number_format($summary['total_eggs_sold']) }}</td>
                        <td class="text-right py-3 text-sm font-bold text-[#4CAF50]">₱{{ number_format($summary['total_revenue'], 2) }}</td>
                        <td class="text-right py-3 text-sm font-bold">{{ $summary['avg_production_rate'] }}%</td>
                        <td class="text-right py-3 text-sm font-bold {{ $summary['remaining_eggs'] < 0 ? 'text-red-600' : '' }}">
                            {{ number_format($summary['remaining_eggs']) }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- === SECTION 2: BATCH TRACEABILITY REPORT === --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Batch Traceability Report</h2>
                <p class="text-sm text-gray-500 mt-0.5">Track eggs from collection through sale or disposal</p>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="alert('PDF export — coming soon.')"
                    class="flex items-center gap-1 bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 text-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    PDF
                </button>
                <button type="button" onclick="alert('Excel export — coming soon.')"
                    class="flex items-center gap-1 bg-green-700 text-white px-3 py-2 rounded-lg hover:bg-green-800 text-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Excel
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Date</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Size</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Qty Collected</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Qty Sold</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Remaining</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Sell-Through</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dailyData as $row)
                        @php
                            $remaining  = $row['eggs'] - $row['sold'];
                            $sellThrough = $row['eggs'] > 0 ? round(($row['sold'] / $row['eggs']) * 100, 1) : 0;
                            $status = $remaining <= 0 ? 'Fully Sold' : ($row['sold'] > 0 ? 'Partially Sold' : 'Active');
                            $statusColor = match($status) {
                                'Fully Sold'     => 'bg-green-100 text-green-700',
                                'Partially Sold' => 'bg-blue-100 text-blue-700',
                                default          => 'bg-yellow-100 text-yellow-700',
                            };
                        @endphp
                        <tr class="border-b last:border-0 hover:bg-gray-50">
                            <td class="py-3 px-2 text-sm">{{ $row['date'] }}</td>
                            <td class="py-3 px-2 text-sm text-gray-600">—</td>
                            <td class="text-right py-3 px-2 text-sm">{{ number_format($row['eggs']) }}</td>
                            <td class="text-right py-3 px-2 text-sm">{{ number_format($row['sold']) }}</td>
                            <td class="text-right py-3 px-2 text-sm font-semibold">{{ number_format($remaining) }}</td>
                            <td class="text-right py-3 px-2 text-sm text-[#4CAF50] font-semibold">{{ $sellThrough }}%</td>
                            <td class="py-3 px-2 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-semibold {{ $statusColor }}">{{ $status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-gray-400 text-sm">No data for selected date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-3 italic">
            Sell-Through Rate = (Qty Sold ÷ Qty Collected) × 100. Remaining Stock = Qty Collected − Qty Sold.
        </p>
    </div>

    {{-- === SECTION 3: AUDIT INCONSISTENCY REPORT === --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Audit Inconsistency Report</h2>
                <p class="text-sm text-gray-500 mt-0.5">All flagged data integrity violations across production and sales records</p>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="alert('PDF export — coming soon.')"
                    class="flex items-center gap-1 bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 text-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    PDF
                </button>
                <button type="button" onclick="alert('Excel export — coming soon.')"
                    class="flex items-center gap-1 bg-green-700 text-white px-3 py-2 rounded-lg hover:bg-green-800 text-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Excel
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Entry Date</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Entry Type</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Rule Violated / Note</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Derive audit flags from actual data --}}
                    @php
                        $auditRows = [];
                        foreach ($dailyData as $row) {
                            // Check: production entry exists but no hen count (prod_rate = 0 and eggs > 0)
                            if ($row['prod_rate'] == 0 && $row['eggs'] > 0) {
                                $auditRows[] = [
                                    'date'   => $row['date'],
                                    'type'   => 'Production',
                                    'rule'   => 'Production rate is 0% — active hen count may be missing or incorrect',
                                    'status' => 'Flagged',
                                ];
                            }
                            // Check: sold exceeds produced (should be blocked but just in case)
                            if ($row['sold'] > $row['eggs'] && $row['eggs'] > 0) {
                                $auditRows[] = [
                                    'date'   => $row['date'],
                                    'type'   => 'Sales',
                                    'rule'   => "Eggs sold ({$row['sold']}) exceeds eggs produced ({$row['eggs']}) — audit violation",
                                    'status' => 'Flagged',
                                ];
                            }
                        }
                    @endphp

                    @forelse($auditRows as $a)
                        <tr class="{{ $a['status'] === 'Flagged' ? 'bg-red-50' : '' }} border-b last:border-0">
                            <td class="py-3 px-2 text-sm">{{ $a['date'] }}</td>
                            <td class="py-3 px-2 text-sm">{{ $a['type'] }}</td>
                            <td class="py-3 px-2 text-sm text-gray-700 max-w-xs">{{ $a['rule'] }}</td>
                            <td class="py-3 px-2 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-semibold {{ $a['status'] === 'Flagged' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                    {{ $a['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-gray-400 text-sm">No audit inconsistencies found for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-3 italic">
            Flagged rows indicate unresolved inconsistencies. Admin can edit flagged records directly. Manager can flag for Admin review.
        </p>
    </div>

    {{-- === SECTION 4: FORECAST EVALUATION HISTORY === --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Forecast Evaluation History</h2>
                <p class="text-sm text-gray-500 mt-0.5">PHP-ML LeastSquares model — last 10 evaluation cycles</p>
            </div>
            <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded font-medium">PHP-ML</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Date Evaluated</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Records Used</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">MAPE (%)</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">7-Day Forecast Total</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">30-Day Revenue Forecast</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($forecastEvaluations as $eval)
                        @php
                            $mapeVal = (float) $eval->mape;
                            $mapeColor = $mapeVal < 5
                                ? 'bg-green-100 text-green-700'
                                : ($mapeVal <= 15 ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700');
                        @endphp
                        <tr class="border-b last:border-0 hover:bg-gray-50">
                            <td class="py-3 px-2 text-sm">{{ $eval->evaluated_at->format('M d, Y H:i') }}</td>
                            <td class="text-right py-3 px-2 text-sm">{{ number_format($eval->trained_on) }}</td>
                            <td class="text-right py-3 px-2 text-sm">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $mapeColor }}">
                                    {{ number_format($mapeVal, 2) }}%
                                </span>
                            </td>
                            <td class="text-right py-3 px-2 text-sm font-semibold text-gray-700">
                                {{ number_format($eval->forecast_7day_total) }} eggs
                            </td>
                            <td class="text-right py-3 px-2 text-sm font-semibold text-[#4CAF50]">
                                ₱{{ number_format($eval->forecast_30day_total, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-400 text-sm">
                                No evaluation records yet. Run <code class="bg-gray-100 px-1 rounded">php artisan forecast:retrain</code> to generate the first evaluation.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-3 italic">
            MAPE (Mean Absolute Percentage Error): &lt;5% excellent, 5–15% acceptable, &gt;15% review model data quality.
            Evaluations are saved automatically on the weekly retrain schedule.
        </p>
    </div>
</div>

@if($dailyData->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const data   = @json($dailyData->values());
    const labels = data.map(d => d.date);
    const eggs   = data.map(d => d.eggs);
    const rev    = data.map(d => d.revenue);

    // Production Trend
    const prodCtx = document.getElementById('prodTrendChart');
    if (prodCtx) {
        new Chart(prodCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Eggs',
                    data: eggs,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76,175,80,0.1)',
                    borderWidth: 2,
                    pointRadius: 3,
                    tension: 0.3,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } },
                    y: { ticks: { font: { size: 11 } }, beginAtZero: false }
                }
            }
        });
    }

    // Revenue Trend
    const revCtx = document.getElementById('revTrendChart');
    if (revCtx) {
        new Chart(revCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Revenue',
                    data: rev,
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33,150,243,0.1)',
                    borderWidth: 2,
                    pointRadius: 3,
                    tension: 0.3,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => '₱' + ctx.parsed.y.toLocaleString() } }
                },
                scales: {
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } },
                    y: { ticks: { callback: v => '₱' + v.toLocaleString(), font: { size: 11 } }, beginAtZero: true }
                }
            }
        });
    }
})();
</script>
@endif
</x-app-layout>
