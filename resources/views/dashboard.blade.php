<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>

    {{-- Metric Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50] hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Eggs Today</span>
                <svg class="w-5 h-5 text-[#4CAF50]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
            <div class="text-3xl font-bold text-gray-800">{{ number_format($stats['eggs_today']) }}</div>
            <div class="text-sm text-green-600 mt-1">collected today</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50] hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Revenue Today</span>
                <svg class="w-5 h-5 text-[#4CAF50]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="text-3xl font-bold text-gray-800">₱{{ number_format($stats['revenue_today'], 2) }}</div>
            <div class="text-sm text-gray-600 mt-1">from sales today</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50] hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Production Rate</span>
                <svg class="w-5 h-5 text-[#4CAF50]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div class="text-3xl font-bold text-gray-800">{{ $stats['production_rate'] }}%</div>
            <div class="text-sm text-gray-600 mt-1">{{ number_format($stats['active_hens']) }} active hens</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50] hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Sales This Month</span>
                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="text-3xl font-bold text-gray-800">₱{{ number_format($stats['sales_this_month'], 2) }}</div>
            <div class="text-sm text-gray-600 mt-1">{{ now()->format('F Y') }}</div>
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6"
         data-prod-chart="{{ $productionChartData->values()->toJson() }}"
         data-rev-chart="{{ $revenueChartData->values()->toJson() }}">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-800">Production Trend</h2>
                <p class="text-sm text-gray-500">Last 30 days</p>
            </div>
            <canvas id="productionChart" height="130"></canvas>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-800">Revenue Trend</h2>
                <p class="text-sm text-green-600">Last 10 days of sales</p>
            </div>
            <canvas id="revenueChart" height="130"></canvas>
        </div>
    </div>

    {{-- AI Insights & Recent Activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">AI Insights</h2>
                <div class="flex items-center gap-2">
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">AI — Analytics</span>
                </div>
            </div>
            <div class="flex gap-4">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <p class="text-gray-700 leading-relaxed">{{ $aiInsight }}</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">Recent Activity</h2>
                <a href="{{ route('productions.index') }}" class="text-xs text-[#4CAF50] hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 text-sm font-semibold text-gray-700">Date</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700">Eggs Prod</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700">Sold</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700">Revenue</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700">Remaining</th>
                            <th class="text-left py-2 text-sm font-semibold text-gray-700">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentActivity as $item)
                            <tr class="border-b last:border-0">
                                <td class="py-2 text-sm">{{ $item['date'] }}</td>
                                <td class="text-right py-2 text-sm">{{ number_format($item['eggsProd']) }}</td>
                                <td class="text-right py-2 text-sm">{{ number_format($item['sold']) }}</td>
                                <td class="text-right py-2 text-sm font-semibold text-[#4CAF50]">{{ $item['revenue'] }}</td>
                                <td class="text-right py-2 text-sm text-gray-600">{{ $item['remaining'] }}</td>
                                <td class="py-2 text-sm text-gray-500">{{ $item['notes'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No activity yet. Start by logging production.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Anomaly Alerts --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">Anomaly Alerts</h2>
            @if($anomalyAlerts->where('status','unreviewed')->count() > 0)
                <span class="text-xs bg-red-100 text-red-700 font-semibold px-2 py-1 rounded-full">
                    {{ $anomalyAlerts->where('status','unreviewed')->count() }} unreviewed
                </span>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-3 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">{{ session('success') }}</div>
        @endif

        @if($anomalyAlerts->count() > 0)
            <div class="space-y-3">
                @foreach($anomalyAlerts as $alert)
                    @php
                        $isHigh     = $alert->severity === 'high';
                        $isResolved = $alert->status   === 'resolved';
                        $bgClass    = $isResolved ? 'bg-gray-50 border-gray-300' : ($isHigh ? 'bg-red-50 border-red-500' : 'bg-orange-50 border-orange-400');
                        $textClass  = $isHigh ? 'text-red-900' : 'text-orange-900';
                        $iconClass  = $isHigh ? 'text-red-600' : 'text-orange-500';
                        $devClass   = $isHigh ? 'text-red-700' : 'text-orange-700';
                    @endphp
                    <div class="p-4 rounded-lg border-l-4 {{ $bgClass }} {{ $isResolved ? 'opacity-60' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <svg class="w-4 h-4 {{ $iconClass }} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span class="font-semibold text-sm {{ $textClass }}">{{ $alert->type }}</span>
                                    <span class="text-xs text-gray-500">{{ $alert->alert_date->format('M d, Y') }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold
                                        {{ $alert->status === 'resolved'   ? 'bg-green-100 text-green-700' :
                                           ($alert->status === 'reviewed'  ? 'bg-blue-100 text-blue-700'  :
                                                                             'bg-red-100 text-red-700') }}">
                                        {{ ucfirst($alert->status) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 mb-2">{{ $alert->description }}</p>
                                <div class="flex flex-wrap gap-4 text-xs">
                                    <span class="text-gray-500">Expected: <span class="font-semibold text-gray-700">{{ $alert->expected_value }}</span></span>
                                    <span class="text-gray-500">Actual: <span class="font-semibold text-gray-700">{{ $alert->actual_value }}</span></span>
                                    <span class="font-semibold {{ $devClass }}">{{ $alert->deviation_pct }}%</span>
                                </div>
                                @if($alert->status === 'resolved' && $alert->resolver)
                                    <p class="text-xs text-gray-400 mt-1">Resolved by {{ $alert->resolver->name }} on {{ $alert->resolved_at->format('M d, Y') }}</p>
                                @endif
                            </div>

                            @if(in_array(auth()->user()->role, ['admin','manager']) && $alert->status !== 'resolved')
                                <div class="flex flex-col gap-1 flex-shrink-0">
                                    @if($alert->status === 'unreviewed')
                                        <form method="POST" action="{{ route('alerts.reviewed', $alert) }}">
                                            @csrf @method('PATCH')
                                            <button class="text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 px-2 py-1 rounded font-medium">Mark Reviewed</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('alerts.resolved', $alert) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-xs bg-green-100 text-green-700 hover:bg-green-200 px-2 py-1 rounded font-medium">Resolve</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400 italic">No anomalies detected in the last 14 days.</p>
        @endif
    </div>

    {{-- Farm Recommendations --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Farm Recommendations</h2>
        <div class="space-y-3">
            @foreach($farmRecommendations as $rec)
                <div class="p-4 rounded-lg border-l-4 {{ $rec['active'] ? 'bg-blue-50 border-blue-500' : 'bg-gray-50 border-gray-300' }}">
                    <div class="flex items-start gap-3">
                        @if($rec['active'])
                            <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-800 mb-1">Condition: {{ $rec['condition'] }}</p>
                            <p class="text-sm text-gray-700"><span class="font-semibold">Recommendation:</span> {{ $rec['recommendation'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script type="text/javascript">
(function () {
    const chartContainer = document
    .querySelector('[data-prod-chart]');
    const prodData = JSON.parse(chartContainer?.getAttribute('data-prod-chart') || '[]');
    const revData = JSON.parse(chartContainer?.getAttribute('data-rev-chart') || '[]');

    // Production Trend — Line Chart
    const prodCtx = document.getElementById('productionChart');
    if (prodCtx && prodData.length) {
        new Chart(prodCtx, {
            type: 'line',
            data: {
                labels: prodData.map(d => d.date),
                datasets: [{
                    label: 'Eggs Collected',
                    data: prodData.map(d => d.eggs),
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
                    x: { ticks: { maxTicksLimit: 8, font: { size: 11 } }, grid: { display: false } },
                    y: { ticks: { font: { size: 11 } }, beginAtZero: false }
                }
            }
        });
    } else if (prodCtx) {
        prodCtx.parentElement.innerHTML += '<p class="text-sm text-gray-400 text-center mt-8">No production data yet.</p>';
    }

    // Revenue Trend — Bar Chart
    const revCtx = document.getElementById('revenueChart');
    if (revCtx && revData.length) {
        new Chart(revCtx, {
            type: 'bar',
            data: {
                labels: revData.map(d => d.date),
                datasets: [{
                    label: 'Revenue (₱)',
                    data: revData.map(d => d.revenue),
                    backgroundColor: '#4CAF50',
                    borderRadius: 4,
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
    } else if (revCtx) {
        revCtx.parentElement.innerHTML += '<p class="text-sm text-gray-400 text-center mt-8">No sales data yet.</p>';
    }
})();
</script>
</x-app-layout>
