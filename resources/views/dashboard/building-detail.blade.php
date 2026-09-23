{{--
    Per-building detail panel — fetched via AJAX and injected into the
    accordion row on the main dashboard (see resources/views/dashboard.blade.php).
    Ported from Prototype 3H's standalone per-building page; the data/logic
    lives in App\Services\BuildingPerformanceService::buildingDetail(), not here.
--}}
@php
    $prodBandClasses = [
        'green' => ['badge' => 'bg-green-100 text-green-700', 'border' => 'border-green-500'],
        'amber' => ['badge' => 'bg-amber-100 text-amber-700', 'border' => 'border-amber-500'],
        'red'   => ['badge' => 'bg-red-100 text-red-700',     'border' => 'border-red-500'],
        // Culled/ended badge (3Q) — neutral, not a health signal.
        'gray'  => ['badge' => 'bg-gray-200 text-gray-600',   'border' => 'border-gray-400'],
    ];
@endphp

<div class="space-y-6">
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

    {{-- Trend chart --}}
    <div class="bg-gray-50 rounded-lg p-4"
         data-trend-chart='{{ $trendData->values()->toJson() }}'
         data-healthy-min="{{ config('dashboard.prod_rate_healthy_min') }}"
         data-cull-max="{{ config('dashboard.prod_rate_cull_max') }}">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Production-Rate Trend</h3>
                <p class="text-xs text-gray-500">Dashed lines mark the {{ config('dashboard.prod_rate_healthy_min') }}% healthy and {{ config('dashboard.prod_rate_cull_max') }}% cull-consideration thresholds.</p>
            </div>
            <label class="flex items-center gap-2 text-xs text-gray-600 whitespace-nowrap">
                <input type="checkbox" id="thiToggle-{{ $henBatch->id }}" class="rounded border-gray-300 text-[#4CAF50] focus:ring-[#4CAF50]" />
                Overlay THI
            </label>
        </div>
        <canvas id="prodRateTrendChart-{{ $henBatch->id }}" height="100"></canvas>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Alerts panel --}}
        <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-bold text-gray-800 mb-3">Alerts</h3>
            @if($alerts->count() > 0)
                <div class="space-y-2">
                    @foreach($alerts as $alert)
                        @php $isCritical = $alert->severity === 'critical'; @endphp
                        <div class="p-2 rounded-lg border-l-4 {{ $isCritical ? 'bg-red-50 border-red-500' : 'bg-orange-50 border-orange-400' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold {{ $isCritical ? 'text-red-800' : 'text-orange-800' }}">{{ $alert->condition }}</span>
                                <span class="text-xs text-gray-500">{{ $alert->status === 'open' ? 'Open' : 'Resolved' }}</span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1">{{ $alert->recommendation }}</p>
                            <p class="text-xs text-gray-400 mt-1">Since {{ $alert->triggered_since->format('M d, Y') }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-gray-400 text-center py-6">No alerts for this building in the selected window.</p>
            @endif
        </div>

        {{-- Estimated Revenue Contribution --}}
        <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-bold text-gray-800 mb-1">Estimated Revenue Contribution</h3>
            <p class="text-xs text-orange-600 mb-3">Estimated — eggs are mixed farm-wide before grading/sale; attributed by this building's {{ number_format($eggShare * 100, 1) }}% share of eggs produced in the window.</p>
            <div class="text-2xl font-bold text-gray-800">₱{{ number_format($estimatedRevenue, 2) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Expenses --}}
        <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-bold text-gray-800 mb-1">Expenses (window)</h3>
            <p class="text-xs text-gray-500 mb-3">
                Direct: ₱{{ number_format($expenses['direct_total'], 2) }}
                @if($expenses['has_estimated_portion'])
                    <span class="text-orange-600">+ Estimated allocated (population share {{ number_format($expenses['population_share'] * 100, 1) }}%): ₱{{ number_format($expenses['allocated_total'], 2) }}</span>
                @endif
            </p>
            <div class="text-2xl font-bold text-gray-800 mb-2">₱{{ number_format($expenses['total'], 2) }}</div>
            <div class="space-y-1">
                @foreach($expenses['by_category'] as $cat => $amount)
                    @if($amount > 0)
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-600">{{ \App\Models\Expense::CATEGORIES[$cat]['label'] }}</span>
                            <span class="font-medium text-gray-800">₱{{ number_format($amount, 2) }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Net Contribution --}}
        <div class="bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-bold text-gray-800 mb-1">Estimated Net Contribution</h3>
            <p class="text-xs text-orange-600 mb-3">Estimated Revenue &minus; Total Expenses (both sides carry estimates).</p>
            <div class="text-3xl font-bold {{ $netContribution >= 0 ? 'text-[#4CAF50]' : 'text-red-600' }}">₱{{ number_format($netContribution, 2) }}</div>
        </div>
    </div>

    {{-- Forecast placeholder (disabled) --}}
    <div class="bg-gray-50 rounded-lg p-4 border-2 border-dashed border-gray-300 opacity-70">
        <div class="flex items-center gap-2 mb-1">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <h3 class="text-sm font-bold text-gray-500">Forward Projection (Survival &middot; Production &middot; Earnings)</h3>
        </div>
        <p class="text-xs text-gray-500">Pending Animal Science model &mdash; coming in a later release. Nothing above is fabricated or predicted.</p>
    </div>

    {{-- Descriptive AI Insight card --}}
    <div class="bg-gray-50 rounded-lg p-4">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Insight (Descriptive Only)</h3>
        <ul class="space-y-1.5 text-xs text-gray-700">
            @foreach($insight as $line)
                <li>&bull; {{ $line }}</li>
            @endforeach
            @foreach($insightPlaceholder as $line)
                <li class="text-gray-300">&bull; {{ $line }}</li>
            @endforeach
        </ul>
    </div>
</div>
