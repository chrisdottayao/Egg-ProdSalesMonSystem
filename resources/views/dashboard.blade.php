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

        {{-- Production Rate was removed from here (3N) — it duplicated the
        Per-Building Performance section's "Farm-wide Prod Rate" below, computed
        a different way (today's single-day snapshot vs. a windowed average of
        daily building rates), which read as a bug once both were individually
        correct. Active Hens, previously just a subtitle here, is promoted to
        its own card since the farm actually references that figure. --}}
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50] hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Active Hens</span>
                <svg class="w-5 h-5 text-[#4CAF50]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div class="text-3xl font-bold text-gray-800">{{ number_format($stats['active_hens']) }}</div>
            <div class="text-sm text-gray-600 mt-1">across tracked buildings</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-400 hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Forecast Alert</span>
                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            @if($forecast['active'])
                @php $total7day = collect($forecast['forecast_7day'])->sum('predicted'); @endphp
                <div class="text-3xl font-bold text-gray-800">{{ number_format($total7day) }}</div>
                <div class="text-sm text-gray-600 mt-1">eggs next 7 days</div>
            @else
                <div class="text-3xl font-bold text-gray-400">&mdash;</div>
                <div class="text-sm text-gray-500 mt-1">Needs 30 days of data</div>
            @endif
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6"
         data-prod-chart="{{ $productionChartData->values()->toJson() }}"
         data-rev-chart="{{ $revenueChartData->values()->toJson() }}"
         data-forecast-active="{{ $forecast['active'] ? 'true' : 'false' }}"
         @if($forecast['active'])
         data-forecast-prod="{{ json_encode($forecast['forecast_7day']) }}"
         data-forecast-rev="{{ json_encode($forecast['forecast_30day']) }}"
         @endif>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-800">Production Trend</h2>
                <p class="text-sm text-gray-500">Last 30 days{{ $forecast['active'] ? ' + 7-day forecast' : '' }}</p>
            </div>
            <canvas id="productionChart" height="130"></canvas>
            @if($forecast['active'])
                <div class="mt-3 flex items-center gap-2">
                    <span class="text-xs px-2 py-1 rounded-full font-semibold
                        {{ $forecast['mape'] < 5 ? 'bg-green-100 text-green-700' : ($forecast['mape'] <= 15 ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700') }}">
                        PHP-ML &mdash; MAPE: {{ $forecast['mape'] }}% &mdash; retrained weekly
                    </span>
                </div>
            @else
                <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm text-blue-700">Predictive analytics will activate after 30 days of recorded production data.</p>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-800">Revenue Trend</h2>
                <p class="text-sm text-green-600">{{ $forecast['active'] ? 'Last 10 days + 30-day forecast' : 'Last 10 days of sales' }}</p>
            </div>
            <canvas id="revenueChart" height="130"></canvas>
        </div>
    </div>

    {{-- Weather / Environmental Conditions (read-only — never calls Open-Meteo live) --}}
    @php
        $thiBandColors = [
            'green'  => ['border' => 'border-[#4CAF50]', 'badge' => 'bg-green-100 text-green-700'],
            'yellow' => ['border' => 'border-yellow-400', 'badge' => 'bg-yellow-100 text-yellow-700'],
            'orange' => ['border' => 'border-orange-400', 'badge' => 'bg-orange-100 text-orange-700'],
            'red'    => ['border' => 'border-red-500',    'badge' => 'bg-red-100 text-red-700'],
        ];
        $latestBand = $latestWeather ? \App\Models\WeatherDaily::band($latestWeather->thi) : null;
        $bandColors = $thiBandColors[$latestBand['color'] ?? 'green'] ?? $thiBandColors['green'];
    @endphp
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" data-thi-chart="{{ $weatherTrend->values()->toJson() }}">
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 {{ $bandColors['border'] }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Latest THI</span>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97l-6.93-12a2 2 0 00-3.5 0l-6.93 12A2 2 0 005.07 19z"/></svg>
            </div>
            @if($latestWeather && $latestWeather->thi !== null)
                <div class="text-3xl font-bold text-gray-800">{{ number_format($latestWeather->thi, 1) }}</div>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-xs px-2 py-1 rounded-full font-semibold {{ $bandColors['badge'] }}">{{ $latestBand['label'] }}</span>
                    <span class="text-xs text-gray-500">{{ $latestWeather->date->format('M d, Y') }}</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">Temperature-Humidity Index &mdash; SPC Farm Magalang</p>
            @else
                <div class="text-lg font-medium text-gray-400 mt-1">No weather data yet</div>
                <p class="text-xs text-gray-400 mt-1">Run <code class="bg-gray-100 px-1 rounded">php artisan weather:backfill</code> to populate history.</p>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-2">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-800">THI Trend</h2>
                <p class="text-sm text-gray-500">Last 14 days &mdash; heat-stress context, not a prediction</p>
            </div>
            <canvas id="thiChart" height="90"></canvas>
        </div>
    </div>

    {{-- Per-Building Performance (3J — merged from the old standalone investment
    dashboard; farm-wide summary/leaderboard admin+manager only, same access
    level 3H used. The Anomaly Alerts banner below is farm-wide data with no
    role restriction of its own — kept visible to every role exactly as
    before 3L, just repositioned and compacted; see 3L constraints.) ─────── --}}
    @php
        $prodBandClasses = [
            'green' => ['badge' => 'bg-green-100 text-green-700', 'border' => 'border-green-500'],
            'amber' => ['badge' => 'bg-amber-100 text-amber-700', 'border' => 'border-amber-500'],
            'red'   => ['badge' => 'bg-red-100 text-red-700',     'border' => 'border-red-500'],
        ];
    @endphp
    <div class="bg-white rounded-lg shadow-md p-6">
        @if(in_array(Auth::user()->role, ['admin', 'manager']))
            <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Per-Building Performance</h2>
                    <p class="text-sm text-gray-500">Descriptive only — what has happened, from existing data. Click a building to expand its detail.</p>
                </div>
                <div class="flex gap-2">
                    @foreach(['1' => '1 mo', '2' => '2 mo', '3' => '3 mo', 'this_month' => 'This month'] as $val => $label)
                        <a href="{{ route('dashboard', ['window' => $val]) }}"
                           class="px-3 py-1 rounded-lg text-xs font-medium {{ $window === $val ? 'bg-[#4CAF50] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
            <p class="text-xs text-gray-400 mb-4">{{ $perfStart->format('M d, Y') }} &mdash; {{ $perfEnd->format('M d, Y') }}</p>

            {{-- Farm-wide summary strip --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="p-4 rounded-lg border-l-4 {{ $farmOverview['farmBand'] ? $prodBandClasses[$farmOverview['farmBand']['color']]['border'] : 'border-gray-300' }} bg-gray-50">
                    <div class="text-gray-600 text-sm mb-1">Farm-wide Prod Rate</div>
                    <div class="text-2xl font-bold text-gray-800">{{ $farmOverview['farmProdRate'] !== null ? $farmOverview['farmProdRate'] . '%' : '—' }}</div>
                    @if($farmOverview['farmBand'])
                        <span class="inline-block mt-1 text-xs px-2 py-1 rounded-full font-semibold {{ $prodBandClasses[$farmOverview['farmBand']['color']]['badge'] }}">{{ $farmOverview['farmBand']['label'] }}</span>
                    @endif
                    <div class="text-xs text-gray-500 mt-1">Target: &ge; {{ config('dashboard.prod_rate_healthy_min') }}%</div>
                </div>
                <div class="p-4 rounded-lg border-l-4 border-[#4CAF50] bg-gray-50">
                    <div class="text-gray-600 text-sm mb-1">Revenue (window)</div>
                    <div class="text-2xl font-bold text-gray-800">₱{{ number_format($farmOverview['farmRevenue'], 2) }}</div>
                </div>
                <div class="p-4 rounded-lg border-l-4 {{ $farmOverview['farmNet'] >= 0 ? 'border-[#4CAF50]' : 'border-red-500' }} bg-gray-50">
                    <div class="text-gray-600 text-sm mb-1">Net (Revenue &minus; Expenses)</div>
                    <div class="text-2xl font-bold {{ $farmOverview['farmNet'] >= 0 ? 'text-gray-800' : 'text-red-600' }}">₱{{ number_format($farmOverview['farmNet'], 2) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Expenses: ₱{{ number_format($farmOverview['farmExpenses'], 2) }}</div>
                </div>
            </div>
        @endif

        {{-- Anomaly Alerts — farm-wide (AnomalyAlert has no per-building
        attribution; computed from farm-wide totals, see DashboardController's
        detection methods). Visible to every role, unchanged from before 3L. --}}
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-gray-800">Anomaly Alerts</h3>
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
                <div class="space-y-2">
                    @foreach($anomalyAlerts as $alert)
                        @php
                            $isHigh     = $alert->severity === 'high';
                            $isResolved = $alert->status   === 'resolved';
                            $bgClass    = $isResolved ? 'bg-gray-50 border-gray-300' : ($isHigh ? 'bg-red-50 border-red-500' : 'bg-orange-50 border-orange-400');
                            $textClass  = $isHigh ? 'text-red-900' : 'text-orange-900';
                            $iconClass  = $isHigh ? 'text-red-600' : 'text-orange-500';
                            $devClass   = $isHigh ? 'text-red-700' : 'text-orange-700';
                        @endphp
                        <div class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-4 {{ $bgClass }} {{ $isResolved ? 'opacity-60' : '' }}">
                            <svg class="w-4 h-4 {{ $iconClass }} flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div class="flex-1 min-w-0 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                <span class="font-semibold {{ $textClass }}">{{ $alert->type }}</span>
                                <span class="text-gray-400">{{ $alert->alert_date->format('M d, Y') }}</span>
                                <span class="text-gray-600">{{ $alert->description }}</span>
                                <span class="font-semibold {{ $devClass }}">{{ $alert->deviation_pct }}%</span>
                                <span class="px-2 py-0.5 rounded-full font-semibold
                                    {{ $alert->status === 'resolved'   ? 'bg-green-100 text-green-700' :
                                       ($alert->status === 'reviewed'  ? 'bg-blue-100 text-blue-700'  :
                                                                         'bg-red-100 text-red-700') }}">
                                    {{ ucfirst($alert->status) }}
                                </span>
                                @if($isResolved && $alert->resolver)
                                    <span class="text-gray-400">Resolved by {{ $alert->resolver->name }} on {{ $alert->resolved_at->format('M d, Y') }}</span>
                                @endif
                            </div>

                            @if(in_array(auth()->user()->role, ['admin','manager']) && $alert->status !== 'resolved')
                                <div class="flex gap-1 flex-shrink-0">
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
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 italic">No anomalies detected in the last 14 days.</p>
            @endif
        </div>

        @if(in_array(Auth::user()->role, ['admin', 'manager']))
            {{-- Building list / leaderboard — click a row to expand in place --}}
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 text-sm font-semibold text-gray-700">Building</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700">Population</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700">Prod Rate</th>
                            <th class="text-left py-2 text-sm font-semibold text-gray-700">Status</th>
                            <th class="text-right py-2 text-sm font-semibold text-gray-700">Alerts</th>
                            <th class="text-left py-2 text-sm font-semibold text-gray-700">As of</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    @forelse($farmOverview['leaderboard'] as $row)
                        @php $rowLabel = $row['building']->display_label; @endphp
                        {{-- x-data lives on the <tbody> (a real ancestor of both rows below)
                        so the detail row can see `expanded`/`toggle()` — putting it on the
                        first <tr> left the second <tr> as a sibling, out of Alpine's scope,
                        which is why the accordion never opened (3L Fix 1). --}}
                        <tbody x-data="buildingRow({{ $row['building']->id }}, {{ \Illuminate\Support\Js::from($window) }})">
                            <tr @click="toggle()"
                                class="border-b cursor-pointer hover:bg-gray-50 border-l-4 {{ $row['band'] ? $prodBandClasses[$row['band']['color']]['border'] : 'border-gray-200' }}">
                                <td class="py-2 text-sm text-gray-700 pl-2">{{ $rowLabel }}</td>
                                <td class="text-right py-2 text-sm">{{ $row['population'] !== null ? number_format($row['population']) : '—' }}</td>
                                <td class="text-right py-2 text-sm font-semibold">{{ $row['prod_rate'] !== null ? number_format($row['prod_rate'], 1) . '%' : '—' }}</td>
                                <td class="py-2 text-sm">
                                    @if($row['band'])
                                        <span class="text-xs px-2 py-1 rounded-full font-semibold {{ $prodBandClasses[$row['band']['color']]['badge'] }}">{{ $row['band']['label'] }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">No data</span>
                                    @endif
                                </td>
                                <td class="text-right py-2 text-sm">
                                    @if($row['open_alerts_count'] > 0)
                                        <span class="text-xs bg-red-100 text-red-700 font-semibold px-2 py-0.5 rounded-full">{{ $row['open_alerts_count'] }}</span>
                                    @else
                                        <span class="text-gray-300 text-xs">0</span>
                                    @endif
                                </td>
                                <td class="py-2 text-sm text-gray-500">{{ $row['as_of']?->format('M d, Y') ?? '—' }}</td>
                                <td class="text-right py-2">
                                    <svg :class="expanded ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </td>
                            </tr>
                            <tr x-show="expanded" x-cloak style="display:none" @click.stop>
                                <td colspan="7" class="bg-gray-50 border-b p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="text-sm font-bold text-gray-800">{{ $rowLabel }}</h3>
                                        <button type="button" @click.stop="toggle()" class="text-xs text-gray-400 hover:text-gray-600">Collapse</button>
                                    </div>
                                    <div x-show="loading" class="text-sm text-gray-400 py-6 text-center">Loading&hellip;</div>
                                    <div x-show="error" x-text="error" class="text-sm text-red-600 py-4"></div>
                                    <div x-ref="content"></div>
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="7" class="py-8 text-center text-gray-400 text-sm">No active buildings yet.</td></tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        @endif
    </div>

    {{-- AI Insights (farm-wide, all roles) --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">AI Insights</h2>
            <div class="flex items-center gap-2">
                <span id="ai-model-badge" class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded font-medium">&nbsp;</span>
                <button type="button" id="ai-insight-refresh" title="Regenerate today's insight"
                        class="text-gray-400 hover:text-purple-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>
        </div>
        <div class="flex gap-4 items-start">
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </div>
            <div id="ai-insight-container" data-url="{{ route('dashboard.ai-insight') }}" class="flex-1 min-w-0">
                <div class="flex items-center gap-2 text-gray-400 text-sm">
                    <svg class="animate-spin w-4 h-4 text-purple-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span>Fetching AI insight&hellip;</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script type="text/javascript">
// Per-building accordion — one fetch per building per window, cached in the
// row's own Alpine state so re-collapsing/re-expanding within the same page
// session never re-fetches (changing the window navigates the page, which
// naturally resets every row's cache).
function buildingRow(buildingId, windowParam) {
    return {
        expanded: false,
        loading: false,
        loaded: false,
        error: null,
        toggle() {
            this.expanded = !this.expanded;
            if (this.expanded && !this.loaded) this.load();
        },
        async load() {
            this.loading = true;
            this.error = null;
            try {
                const res = await fetch(`/dashboard/buildings/${buildingId}/detail?window=${encodeURIComponent(windowParam)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) throw new Error('request failed');
                const html = await res.text();
                this.$refs.content.innerHTML = html;
                this.loaded = true;
                if (window.initBuildingChart) window.initBuildingChart(this.$refs.content, buildingId);
            } catch (e) {
                this.error = "Could not load this building's details.";
            } finally {
                this.loading = false;
            }
        },
    };
}

// Builds the Prod-Rate Trend chart inside an injected building-detail
// fragment. Separate from the IIFE below because it must run on-demand
// (after AJAX injection), not just once on page load — injecting HTML via
// innerHTML does not execute embedded <script> tags, so this lives here
// instead of inside the fetched partial.
window.initBuildingChart = function (container, buildingId) {
    const chartContainer = container.querySelector('[data-trend-chart]');
    if (!chartContainer) return;

    const data = JSON.parse(chartContainer.getAttribute('data-trend-chart') || '[]');
    const ctx  = container.querySelector('#prodRateTrendChart-' + buildingId);
    if (!ctx) return;

    if (!data.length) {
        ctx.parentElement.insertAdjacentHTML('beforeend', '<p class="text-sm text-gray-400 text-center mt-4">No production data for this building in the selected window.</p>');
        return;
    }

    const labels     = data.map(d => d.date);
    const healthyMin = parseFloat(chartContainer.dataset.healthyMin);
    const cullMax    = parseFloat(chartContainer.dataset.cullMax);

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

    container.querySelector('#thiToggle-' + buildingId)?.addEventListener('change', function (e) {
        chart.setDatasetVisibility(3, e.target.checked);
        chart.update();
    });
};

(function () {
    const chartContainer  = document.querySelector('[data-prod-chart]');
    const prodData        = JSON.parse(chartContainer?.getAttribute('data-prod-chart') || '[]');
    const revData         = JSON.parse(chartContainer?.getAttribute('data-rev-chart')  || '[]');
    const forecastActive  = chartContainer?.getAttribute('data-forecast-active') === 'true';
    const forecastProd    = forecastActive ? JSON.parse(chartContainer.getAttribute('data-forecast-prod') || '[]') : [];
    const forecastRev     = forecastActive ? JSON.parse(chartContainer.getAttribute('data-forecast-rev')  || '[]') : [];

    // Production Trend — Line Chart (+ 7-day dashed forecast)
    const prodCtx = document.getElementById('productionChart');
    if (prodCtx && prodData.length) {
        const fcstLabels   = forecastProd.map(d => d.day);
        const allLabels    = [...prodData.map(d => d.date), ...fcstLabels];
        const histEggs     = [...prodData.map(d => d.eggs), ...Array(fcstLabels.length).fill(null)];
        const fcstEggs     = forecastActive
            ? [...Array(prodData.length).fill(null), ...forecastProd.map(d => d.predicted)]
            : [];

        const prodDatasets = [{
            label:           'Eggs Collected',
            data:            histEggs,
            borderColor:     '#4CAF50',
            backgroundColor: 'rgba(76,175,80,0.1)',
            borderWidth:     2,
            pointRadius:     3,
            tension:         0.3,
            fill:            true,
            spanGaps:        false,
        }];

        if (forecastActive) {
            prodDatasets.push({
                label:           'Forecast',
                data:            fcstEggs,
                borderColor:     '#EF4444',
                backgroundColor: 'rgba(239,68,68,0.05)',
                borderWidth:     2,
                borderDash:      [6, 3],
                pointRadius:     3,
                tension:         0.3,
                fill:            false,
                spanGaps:        false,
            });
        }

        new Chart(prodCtx, {
            type: 'line',
            data: { labels: allLabels, datasets: prodDatasets },
            options: {
                responsive: true,
                plugins: { legend: { display: forecastActive } },
                scales: {
                    x: { ticks: { maxTicksLimit: 10, font: { size: 11 } }, grid: { display: false } },
                    y: { ticks: { font: { size: 11 } }, beginAtZero: false }
                }
            }
        });
    } else if (prodCtx) {
        prodCtx.parentElement.innerHTML += '<p class="text-sm text-gray-400 text-center mt-8">No production data yet.</p>';
    }

    // Revenue Trend — Bar Chart (+ 30-day light-green forecast bars)
    const revCtx = document.getElementById('revenueChart');
    if (revCtx && revData.length) {
        const fcstRevLabels = forecastRev.map(d => d.day);
        const allRevLabels  = [...revData.map(d => d.date), ...fcstRevLabels];
        const histRev       = [...revData.map(d => d.revenue), ...Array(fcstRevLabels.length).fill(null)];
        const fcstRevData   = forecastActive
            ? [...Array(revData.length).fill(null), ...forecastRev.map(d => d.predicted_revenue)]
            : [];

        const revDatasets = [{
            label:           'Revenue (₱)',
            data:            histRev,
            backgroundColor: '#4CAF50',
            borderRadius:    4,
        }];

        if (forecastActive) {
            revDatasets.push({
                label:           'Forecast Revenue',
                data:            fcstRevData,
                backgroundColor: 'rgba(134,239,172,0.7)',
                borderRadius:    4,
            });
        }

        new Chart(revCtx, {
            type: 'bar',
            data: { labels: allRevLabels, datasets: revDatasets },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: forecastActive },
                    tooltip: { callbacks: { label: ctx => '₱' + (ctx.parsed.y ?? 0).toLocaleString() } }
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

    // THI Trend — small line chart, context only (no prediction)
    const thiContainer = document.querySelector('[data-thi-chart]');
    const thiData       = JSON.parse(thiContainer?.getAttribute('data-thi-chart') || '[]').filter(d => d.thi !== null);
    const thiCtx        = document.getElementById('thiChart');
    if (thiCtx && thiData.length) {
        new Chart(thiCtx, {
            type: 'line',
            data: {
                labels: thiData.map(d => d.date),
                datasets: [{
                    label:           'THI',
                    data:            thiData.map(d => d.thi),
                    borderColor:     '#F59E0B',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    borderWidth:     2,
                    pointRadius:     3,
                    tension:         0.3,
                    fill:            true,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { maxTicksLimit: 10, font: { size: 11 } }, grid: { display: false } },
                    y: { ticks: { font: { size: 11 } }, beginAtZero: false }
                }
            }
        });
    } else if (thiCtx) {
        thiCtx.parentElement.innerHTML += '<p class="text-sm text-gray-400 text-center mt-8">No weather data yet &mdash; run <code class="bg-gray-100 px-1 rounded">php artisan weather:backfill</code>.</p>';
    }
})();

// AI Insight — async fetch, cached server-side per calendar day.
// Reload never forces regeneration; only the refresh button does (?refresh=1).
(function () {
    const container = document.getElementById('ai-insight-container');
    const badge     = document.getElementById('ai-model-badge');
    const refreshBtn = document.getElementById('ai-insight-refresh');
    if (!container) return;

    function loadInsight(forceRefresh) {
        container.innerHTML = '<div class="flex items-center gap-2 text-gray-400 text-sm">'
            + '<svg class="animate-spin w-4 h-4 text-purple-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">'
            + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
            + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>'
            + '<span>Fetching AI insight&hellip;</span></div>';

        const url = container.dataset.url + (forceRefresh ? '?refresh=1' : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                const text = (data.insight || '').trim();

                // The model returns bullets marked with "•", but they may or may not
                // be separated by real newlines in the raw string — and even when
                // they are, a plain <p> collapses whitespace so they'd still run
                // together visually. Split on either signal, then render real <li>s.
                const points = text
                    .split(/\r?\n|(?=•)/)
                    .map(s => s.replace(/^[•\-\*]\s*/, '').trim())
                    .filter(s => s.length > 0);

                container.innerHTML = '';

                if (points.length > 1) {
                    const ul = document.createElement('ul');
                    ul.className = 'list-disc list-inside space-y-1.5 text-gray-700 text-sm leading-relaxed';
                    points.forEach(point => {
                        const li = document.createElement('li');
                        li.textContent = point;
                        ul.appendChild(li);
                    });
                    container.appendChild(ul);
                } else {
                    const p = document.createElement('p');
                    p.className = 'text-gray-700 leading-relaxed text-sm';
                    p.textContent = text;
                    container.appendChild(p);
                }

                if (badge && data.model) {
                    badge.textContent = data.model;
                }
            })
            .catch(() => {
                container.innerHTML = '<p class="text-gray-400 italic text-sm">AI insights temporarily unavailable.</p>';
            });
    }

    loadInsight(false);

    refreshBtn?.addEventListener('click', () => loadInsight(true));
})();
</script>
</x-app-layout>
