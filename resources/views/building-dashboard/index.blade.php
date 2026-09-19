<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Per-Building Investment Dashboard</h1>
    <p class="text-sm text-gray-500">Descriptive only — what has happened, from existing data. No prediction yet; see the forward-projection placeholder below.</p>

    @php
        $prodBandClasses = [
            'green' => ['badge' => 'bg-green-100 text-green-700', 'border' => 'border-green-500'],
            'amber' => ['badge' => 'bg-amber-100 text-amber-700', 'border' => 'border-amber-500'],
            'red'   => ['badge' => 'bg-red-100 text-red-700',     'border' => 'border-red-500'],
        ];
    @endphp

    {{-- Selector bar --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="GET" action="{{ route('investment.index') }}" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 items-end">
            <div class="lg:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Building</label>
                <select name="building" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">Farm-wide Overview</option>
                    @foreach($buildings as $b)
                        <option value="{{ $b->id }}" {{ $henBatch && $henBatch->id === $b->id ? 'selected' : '' }}>
                            {{ $b->building_no ? 'Building ' . $b->building_no : $b->batch_id }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Window</label>
                <select name="window" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="1" {{ $window === '1' ? 'selected' : '' }}>Last 1 month</option>
                    <option value="2" {{ $window === '2' ? 'selected' : '' }}>Last 2 months</option>
                    <option value="3" {{ $window === '3' ? 'selected' : '' }}>Last 3 months</option>
                    <option value="this_month" {{ $window === 'this_month' ? 'selected' : '' }}>This month</option>
                </select>
            </div>
            <div class="lg:col-span-2 flex gap-2">
                <button type="submit" class="flex-1 bg-[#4CAF50] text-white px-4 py-2 rounded-lg hover:bg-green-600 text-sm font-medium">Apply</button>
                <span class="flex-1 flex items-center text-xs text-gray-500">{{ $start->format('M d, Y') }} &mdash; {{ $end->format('M d, Y') }}</span>
            </div>
        </form>
    </div>

    @if($mode === 'farm')
        {{-- ── Farm-wide overview ─────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 {{ $farmBand ? $prodBandClasses[$farmBand['color']]['border'] : 'border-gray-300' }}">
                <div class="text-gray-600 text-sm mb-1">Farm-wide Prod Rate</div>
                <div class="text-3xl font-bold text-gray-800">{{ $farmProdRate !== null ? $farmProdRate . '%' : '—' }}</div>
                @if($farmBand)
                    <span class="inline-block mt-1 text-xs px-2 py-1 rounded-full font-semibold {{ $prodBandClasses[$farmBand['color']]['badge'] }}">{{ $farmBand['label'] }}</span>
                @endif
                <div class="text-xs text-gray-500 mt-1">Target: &ge; {{ config('dashboard.prod_rate_healthy_min') }}%</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50]">
                <div class="text-gray-600 text-sm mb-1">Revenue (window)</div>
                <div class="text-3xl font-bold text-gray-800">₱{{ number_format($farmRevenue, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 {{ $farmNet >= 0 ? 'border-[#4CAF50]' : 'border-red-500' }}">
                <div class="text-gray-600 text-sm mb-1">Net (Revenue &minus; Expenses)</div>
                <div class="text-3xl font-bold {{ $farmNet >= 0 ? 'text-gray-800' : 'text-red-600' }}">₱{{ number_format($farmNet, 2) }}</div>
                <div class="text-xs text-gray-500 mt-1">Expenses: ₱{{ number_format($farmExpenses, 2) }}</div>
            </div>
        </div>

        {{-- Building Leaderboard / Heatmap --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-1">Building Leaderboard</h2>
            <p class="text-sm text-gray-500 mb-4">Latest production rate per building, band-colored — underperformers stand out at a glance.</p>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 text-sm font-semibold text-gray-700">Building</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700">Population</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700">Prod Rate</th>
                            <th class="text-left py-2 text-sm font-semibold text-gray-700">Status</th>
                            <th class="text-left py-2 text-sm font-semibold text-gray-700">As of</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaderboard as $row)
                            <tr class="border-b last:border-0 border-l-4 {{ $row['band'] ? $prodBandClasses[$row['band']['color']]['border'] : 'border-gray-200' }}">
                                <td class="py-2 text-sm text-gray-700 pl-2">{{ $row['building']->building_no ? 'Building ' . $row['building']->building_no : $row['building']->batch_id }}</td>
                                <td class="text-right py-2 text-sm">{{ $row['population'] !== null ? number_format($row['population']) : '—' }}</td>
                                <td class="text-right py-2 text-sm font-semibold">{{ $row['prod_rate'] !== null ? number_format($row['prod_rate'], 1) . '%' : '—' }}</td>
                                <td class="py-2 text-sm">
                                    @if($row['band'])
                                        <span class="text-xs px-2 py-1 rounded-full font-semibold {{ $prodBandClasses[$row['band']['color']]['badge'] }}">{{ $row['band']['label'] }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">No data</span>
                                    @endif
                                </td>
                                <td class="py-2 text-sm text-gray-500">{{ $row['as_of']?->format('M d, Y') ?? '—' }}</td>
                                <td class="text-right py-2 text-sm">
                                    <a href="{{ route('investment.index', ['building' => $row['building']->id, 'window' => $window]) }}" class="text-blue-600 hover:underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No active buildings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- ── Per-building descriptive view ─────────────────────────────── --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">{{ $henBatch->building_no ? 'Building ' . $henBatch->building_no : $henBatch->batch_id }}</h2>
                @if($openAlertsCount > 0)
                    <span class="text-xs bg-red-100 text-red-700 font-semibold px-2 py-1 rounded-full">{{ $openAlertsCount }} open alert(s)</span>
                @endif
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <div class="text-xs text-gray-500">Population</div>
                    <div class="text-xl font-bold text-gray-800">{{ $currentStatus['population'] !== null ? number_format($currentStatus['population']) : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Flock Age</div>
                    <div class="text-xl font-bold text-gray-800">{{ $currentStatus['age_weeks'] !== null ? $currentStatus['age_weeks'] . ' wk' : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Latest Prod Rate</div>
                    <div class="text-xl font-bold text-gray-800">
                        {{ $currentStatus['prod_rate'] !== null ? number_format($currentStatus['prod_rate'], 1) . '%' : '—' }}
                        @if($currentBand)
                            <span class="ml-1 text-xs px-2 py-1 rounded-full font-semibold {{ $prodBandClasses[$currentBand['color']]['badge'] }}">{{ $currentBand['label'] }}</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-400 mt-1">as of {{ $currentStatus['as_of']?->format('M d, Y') ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Since Last Cull / Restock</div>
                    <div class="text-sm text-gray-700">
                        Cull: {{ $daysSinceCull !== null ? $daysSinceCull . ' days ago' : 'none recorded' }}<br>
                        Restock: {{ $daysSinceRestock !== null ? $daysSinceRestock . ' days ago' : 'none recorded' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Trend chart --}}
        <div class="bg-white rounded-lg shadow-md p-6" data-trend-chart="{{ $trendData->values()->toJson() }}">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Production-Rate Trend</h2>
                    <p class="text-sm text-gray-500">Dashed lines mark the {{ config('dashboard.prod_rate_healthy_min') }}% healthy and {{ config('dashboard.prod_rate_cull_max') }}% cull-consideration thresholds.</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" id="thiToggle" class="rounded border-gray-300 text-[#4CAF50] focus:ring-[#4CAF50]" />
                    Overlay THI (heat stress)
                </label>
            </div>
            <canvas id="prodRateTrendChart" height="110"></canvas>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Alerts panel --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Alerts</h2>
                @if($alerts->count() > 0)
                    <div class="space-y-3">
                        @foreach($alerts as $alert)
                            @php $isCritical = $alert->severity === 'critical'; @endphp
                            <div class="p-3 rounded-lg border-l-4 {{ $isCritical ? 'bg-red-50 border-red-500' : 'bg-orange-50 border-orange-400' }}">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold {{ $isCritical ? 'text-red-800' : 'text-orange-800' }}">{{ $alert->condition }}</span>
                                    <span class="text-xs text-gray-500">{{ $alert->status === 'open' ? 'Open' : 'Resolved' }}</span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">{{ $alert->recommendation }}</p>
                                <p class="text-xs text-gray-400 mt-1">Since {{ $alert->triggered_since->format('M d, Y') }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 text-center py-8">No alerts for this building in the selected window.</p>
                @endif
            </div>

            {{-- Estimated Revenue Contribution --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-1">Estimated Revenue Contribution</h2>
                <p class="text-xs text-orange-600 mb-4">Estimated — eggs are mixed farm-wide before grading/sale; attributed by this building's {{ number_format($eggShare * 100, 1) }}% share of eggs produced in the window.</p>
                <div class="text-3xl font-bold text-gray-800">₱{{ number_format($estimatedRevenue, 2) }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Expenses --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-1">Expenses (window)</h2>
                <p class="text-xs text-gray-500 mb-4">
                    Direct: ₱{{ number_format($expenses['direct_total'], 2) }}
                    @if($expenses['has_estimated_portion'])
                        <span class="text-orange-600">+ Estimated allocated (population share {{ number_format($expenses['population_share'] * 100, 1) }}%): ₱{{ number_format($expenses['allocated_total'], 2) }}</span>
                    @endif
                </p>
                <div class="text-3xl font-bold text-gray-800 mb-3">₱{{ number_format($expenses['total'], 2) }}</div>
                <div class="space-y-1">
                    @foreach($expenses['by_category'] as $cat => $amount)
                        @if($amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ \App\Models\Expense::CATEGORIES[$cat]['label'] }}</span>
                                <span class="font-medium text-gray-800">₱{{ number_format($amount, 2) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Net Contribution --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-1">Estimated Net Contribution</h2>
                <p class="text-xs text-orange-600 mb-4">Estimated Revenue &minus; Total Expenses (both sides carry estimates).</p>
                <div class="text-4xl font-bold {{ $netContribution >= 0 ? 'text-[#4CAF50]' : 'text-red-600' }}">₱{{ number_format($netContribution, 2) }}</div>
                <div class="flex gap-2 mt-4">
                    @foreach(['1' => '1 mo', '2' => '2 mo', '3' => '3 mo'] as $val => $label)
                        <a href="{{ route('investment.index', ['building' => $henBatch->id, 'window' => $val]) }}"
                           class="px-3 py-1 rounded-lg text-xs font-medium {{ $window === $val ? 'bg-[#4CAF50] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Forecast placeholder (disabled) --}}
        <div class="bg-gray-50 rounded-lg shadow-inner p-6 border-2 border-dashed border-gray-300 opacity-70 relative">
            <div class="flex items-center gap-2 mb-2">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <h2 class="text-lg font-bold text-gray-500">Forward Projection (Survival &middot; Production &middot; Earnings)</h2>
            </div>
            <p class="text-sm text-gray-500">Pending Animal Science model &mdash; coming in a later release. Nothing below is fabricated or predicted.</p>
        </div>

        {{-- Descriptive AI Insight card --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Insight (Descriptive Only)</h2>
            <ul class="space-y-2 text-sm text-gray-700">
                <li>&bull; Prod rate today: <strong>{{ $currentStatus['prod_rate'] !== null ? number_format($currentStatus['prod_rate'], 1) . '%' : '—' }}</strong> {{ $currentBand ? '(' . $currentBand['label'] . ')' : '' }}</li>
                <li>&bull; Flock age: <strong>{{ $currentStatus['age_weeks'] !== null ? $currentStatus['age_weeks'] . ' weeks' : '—' }}</strong></li>
                <li>&bull; Estimated revenue ({{ $window === 'this_month' ? 'this month' : $window . '-month window' }}): <strong>₱{{ number_format($estimatedRevenue, 2) }}</strong> &middot; Estimated expenses: <strong>₱{{ number_format($expenses['total'], 2) }}</strong> &middot; Net: <strong>₱{{ number_format($netContribution, 2) }}</strong></li>
                <li>&bull; Status: <strong>{{ $currentBand['label'] ?? 'Unknown' }}</strong></li>
                <li class="text-gray-300">&bull; Projected next 1&ndash;2 months: —</li>
                <li class="text-gray-300">&bull; Projected earnings: —</li>
            </ul>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const container = document.querySelector('[data-trend-chart]');
    if (!container) return;

    const data = JSON.parse(container.getAttribute('data-trend-chart') || '[]');
    const ctx  = document.getElementById('prodRateTrendChart');
    if (!ctx) return;

    if (!data.length) {
        ctx.parentElement.innerHTML += '<p class="text-sm text-gray-400 text-center mt-8">No production data for this building in the selected window.</p>';
        return;
    }

    const labels    = data.map(d => d.date);
    const healthyMin = {{ (float) config('dashboard.prod_rate_healthy_min') }};
    const cullMax    = {{ (float) config('dashboard.prod_rate_cull_max') }};

    const thiDataset = {
        label: 'THI',
        data: data.map(d => d.thi),
        borderColor: '#F59E0B',
        backgroundColor: 'rgba(245,158,11,0.05)',
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.3,
        yAxisID: 'y1',
        hidden: true,
        spanGaps: true,
    };

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Prod Rate (%)',
                    data: data.map(d => d.prod_rate),
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76,175,80,0.1)',
                    borderWidth: 2,
                    pointRadius: 2,
                    tension: 0.3,
                    fill: true,
                    yAxisID: 'y',
                },
                {
                    label: 'Healthy (' + healthyMin + '%)',
                    data: labels.map(() => healthyMin),
                    borderColor: '#16A34A',
                    borderDash: [6, 4],
                    borderWidth: 1,
                    pointRadius: 0,
                    yAxisID: 'y',
                },
                {
                    label: 'Cull-consideration (' + cullMax + '%)',
                    data: labels.map(() => cullMax),
                    borderColor: '#DC2626',
                    borderDash: [6, 4],
                    borderWidth: 1,
                    pointRadius: 0,
                    yAxisID: 'y',
                },
                thiDataset,
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true, labels: { font: { size: 10 } } } },
            scales: {
                x: { ticks: { maxTicksLimit: 10, font: { size: 11 } }, grid: { display: false } },
                y:  { position: 'left', min: 0, max: 100, ticks: { font: { size: 11 }, callback: v => v + '%' } },
                y1: { position: 'right', min: 60, max: 95, grid: { drawOnChartArea: false }, ticks: { font: { size: 11 } } },
            }
        }
    });

    document.getElementById('thiToggle')?.addEventListener('change', function (e) {
        chart.setDatasetVisibility(3, e.target.checked);
        chart.update();
    });
})();
</script>
</x-app-layout>
