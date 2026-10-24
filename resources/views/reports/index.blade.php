<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Reports</h1>
    @php $exportParams = http_build_query(['start_date' => $startDate, 'end_date' => $endDate, 'period' => $period]); @endphp

    {{-- === SECTION 1: DATE FILTER + EXPORTS === --}}
    {{-- Changing Period fills the dates relative to today in Manila (server clock,
         not the browser's) and submits. Page load keeps the request's own values. --}}
    @php
        $periodToday = \App\Support\ManilaDate::today();
        $periodStarts = [
            'Daily'   => $periodToday->toDateString(),
            'Weekly'  => $periodToday->copy()->subDays(7)->toDateString(),
            'Monthly' => $periodToday->copy()->subMonthNoOverflow()->toDateString(),
        ];
    @endphp
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-4"
            x-data="{ starts: { Daily: '{{ $periodStarts['Daily'] }}', Weekly: '{{ $periodStarts['Weekly'] }}', Monthly: '{{ $periodStarts['Monthly'] }}' }, end: '{{ $periodToday->toDateString() }}' }">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                <input type="date" name="start_date" value="{{ $startDate }}" x-ref="start"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </div>
            <div class="flex-1 min-w-[160px]">
                <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                <input type="date" name="end_date" value="{{ $endDate }}" x-ref="end"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Period</label>
                <select name="period" @change="$refs.start.value = starts[$event.target.value]; $refs.end.value = end; $event.target.form.submit()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                    @foreach(['Daily','Weekly','Monthly'] as $p)
                        <option value="{{ $p }}" {{ $period === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button type="submit" class="bg-[#4CAF50] text-white px-5 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Apply</button>
                <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:underline self-center">Reset</a>
            </div>
        </form>

        {{-- Phase 3 item 11: one complete report per format. Tick what to include;
             all sections are ticked by default. The full Audit Log is a separate
             download in its own section below. --}}
        <form method="GET" action="{{ route('reports.export.pdf') }}" class="mt-5 pt-4 border-t border-gray-200" x-data>
            <input type="hidden" name="start_date" value="{{ $startDate }}" />
            <input type="hidden" name="end_date" value="{{ $endDate }}" />
            <input type="hidden" name="period" value="{{ $period }}" />
            <fieldset>
                <legend class="text-sm font-semibold text-gray-700 mb-2">Report contents</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-1.5">
                    @foreach(\App\Support\FarmReport::SECTIONS as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="sections[]" value="{{ $key }}" checked class="rounded border-gray-300 text-[#4CAF50]" />
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="flex items-center gap-2 flex-wrap mt-3">
                <span class="text-sm text-gray-600 mr-1">Download the complete report ({{ $startDate }} to {{ $endDate }}):</span>
                <button type="submit" formaction="{{ route('reports.export.pdf') }}" class="flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">PDF</button>
                <button type="submit" formaction="{{ route('reports.export.csv') }}" class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">CSV</button>
                <button type="submit" formaction="{{ route('reports.export.excel') }}" class="flex items-center gap-2 bg-emerald-700 text-white px-4 py-2 rounded-lg hover:bg-emerald-800 transition-colors text-sm font-medium">Excel</button>
                <button type="button" class="text-xs text-gray-500 hover:underline ml-2" @click="$el.closest('form').querySelectorAll('input[name=\'sections[]\']').forEach(c => c.checked = true)">Tick all</button>
                <button type="button" class="text-xs text-gray-500 hover:underline" @click="$el.closest('form').querySelectorAll('input[name=\'sections[]\']').forEach(c => c.checked = false)">Untick all</button>
            </div>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Summary</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-9 gap-4">
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Total Eggs Produced</div>
                <div class="text-2xl font-bold text-gray-800">{{ number_format($summary['total_eggs_produced']) }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Total Eggs Sold</div>
                <div class="text-2xl font-bold text-gray-800">{{ number_format($summary['total_eggs_sold']) }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Total Mortality</div>
                <div class="text-2xl font-bold text-gray-800">{{ number_format($summary['total_mortality']) }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Total Revenue</div>
                <div class="text-2xl font-bold text-[#4CAF50]">₱{{ number_format($summary['total_revenue'], 0) }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Total Utility Expenses</div>
                <div class="text-2xl font-bold text-gray-800">₱{{ number_format($summary['total_expenses'], 0) }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 mb-1">Net Income</div>
                <div class="text-2xl font-bold {{ $summary['net_income'] < 0 ? 'text-red-600' : 'text-[#4CAF50]' }}">₱{{ number_format($summary['net_income'], 0) }}</div>
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
            <div class="relative h-64 sm:h-72"><canvas id="prodTrendChart"></canvas></div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Revenue Trend</h2>
            <div class="relative h-64 sm:h-72"><canvas id="revTrendChart"></canvas></div>
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
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Mortality</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Revenue</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Utility Expenses</th>
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
                            <td class="text-right py-3 text-sm">{{ number_format($row['mortality']) }}</td>
                            <td class="text-right py-3 text-sm font-semibold text-[#4CAF50]">₱{{ number_format($row['revenue'], 2) }}</td>
                            <td class="text-right py-3 text-sm">₱{{ number_format($row['expenses'], 2) }}</td>
                            <td class="text-right py-3 text-sm">{{ $row['prod_rate'] }}%</td>
                            <td class="text-right py-3 text-sm {{ ($row['eggs'] - $row['sold'] - $row['spoiled']) < 0 ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                                {{ number_format($row['eggs'] - $row['sold'] - $row['spoiled']) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-gray-400 text-sm">No data for selected date range.</td></tr>
                    @endforelse
                </tbody>
                @if($dailyData->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 bg-gray-50">
                        <td class="py-3 text-sm font-bold text-gray-700">Total</td>
                        <td class="text-right py-3 text-sm font-bold">{{ number_format($summary['total_eggs_produced']) }}</td>
                        <td class="text-right py-3 text-sm font-bold">{{ number_format($summary['total_eggs_sold']) }}</td>
                        <td class="text-right py-3 text-sm font-bold">{{ number_format($summary['total_mortality']) }}</td>
                        <td class="text-right py-3 text-sm font-bold text-[#4CAF50]">₱{{ number_format($summary['total_revenue'], 2) }}</td>
                        <td class="text-right py-3 text-sm font-bold">₱{{ number_format($summary['total_expenses'], 2) }}</td>
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

    {{-- === SALES & REVENUE (Phase 3 item 7, S25) === --}}
    <div class="bg-white rounded-lg shadow-md p-6" id="sales-revenue">
        @php $salesExportParams = http_build_query(['start_date' => $startDate, 'end_date' => $endDate, 'period' => $period, 'sales_group' => $salesReport->group]); @endphp
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Sales &amp; Revenue</h2>
                <p class="text-sm text-gray-500 mt-0.5">Approved sales from {{ $startDate }} to {{ $endDate }}. 1 tray = 30 pcs, 1 case = 360 pcs.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <form method="GET" action="{{ route('reports.index') }}#sales-revenue" class="flex items-center gap-2">
                    <input type="hidden" name="start_date" value="{{ $startDate }}" />
                    <input type="hidden" name="end_date" value="{{ $endDate }}" />
                    <input type="hidden" name="period" value="{{ $period }}" />
                    <label for="sales_group" class="text-sm text-gray-600">Group by</label>
                    <select id="sales_group" name="sales_group" onchange="this.form.submit()" class="px-2 py-1.5 border border-gray-300 rounded text-sm">
                        @foreach(\App\Support\SalesReport::GROUPS as $value => $label)
                            <option value="{{ $value }}" @selected($salesReport->group === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('reports.export.sales.pdf') . '?' . $salesExportParams }}" class="bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 text-sm">PDF</a>
                <a href="{{ route('reports.export.sales.csv') . '?' . $salesExportParams }}" class="bg-green-700 text-white px-3 py-2 rounded-lg hover:bg-green-800 text-sm">CSV</a>
                <a href="{{ route('reports.export.sales.excel') . '?' . $salesExportParams }}" class="bg-emerald-700 text-white px-3 py-2 rounded-lg hover:bg-emerald-800 text-sm">Excel</a>
            </div>
        </div>

        @if($salesData['rows']->isNotEmpty())
            <div class="relative h-64 sm:h-72 mb-4"><canvas id="salesRevenueChart"></canvas></div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="text-left py-2 px-2 font-semibold text-gray-700">Period</th>
                        <th class="text-right py-2 px-2 font-semibold text-gray-700">Eggs sold (pcs)</th>
                        <th class="text-right py-2 px-2 font-semibold text-gray-700">Trays</th>
                        <th class="text-right py-2 px-2 font-semibold text-gray-700">Cases</th>
                        <th class="text-right py-2 px-2 font-semibold text-gray-700">Revenue</th>
                        <th class="text-right py-2 px-2 font-semibold text-gray-700">Avg price / pc</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($salesData['rows'] as $r)
                        <tr class="border-b">
                            <td class="py-2 px-2">{{ $r['label'] }}</td>
                            <td class="py-2 px-2 text-right">{{ number_format($r['pcs']) }}</td>
                            <td class="py-2 px-2 text-right">{{ number_format($r['trays'], 1) }}</td>
                            <td class="py-2 px-2 text-right">{{ number_format($r['cases'], 2) }}</td>
                            <td class="py-2 px-2 text-right text-[#4CAF50] font-semibold">₱{{ number_format($r['revenue'], 2) }}</td>
                            <td class="py-2 px-2 text-right">{{ $r['avg_price'] !== null ? '₱' . number_format($r['avg_price'], 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-400">No approved sales in this range.</td></tr>
                    @endforelse
                    <tr class="bg-gray-50 font-bold border-t-2 border-gray-300" data-sales-total>
                        <td class="py-2 px-2">Total</td>
                        <td class="py-2 px-2 text-right">{{ number_format($salesData['totals']['pcs']) }}</td>
                        <td class="py-2 px-2 text-right">{{ number_format($salesData['totals']['trays'], 1) }}</td>
                        <td class="py-2 px-2 text-right">{{ number_format($salesData['totals']['cases'], 2) }}</td>
                        <td class="py-2 px-2 text-right">₱{{ number_format($salesData['totals']['revenue'], 2) }}</td>
                        <td class="py-2 px-2 text-right">{{ $salesData['totals']['avg_price'] !== null ? '₱' . number_format($salesData['totals']['avg_price'], 2) : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-semibold text-gray-700">Eggs sold by size (pcs)</summary>
            <div class="overflow-x-auto mt-2">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b bg-gray-50">
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Period</th>
                            @foreach($salesData['sizes'] as $size)<th class="text-right py-2 px-2 font-semibold text-gray-700">{{ $size }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salesData['rows'] as $r)
                            <tr class="border-b">
                                <td class="py-1.5 px-2">{{ $r['label'] }}</td>
                                @foreach($salesData['sizes'] as $size)<td class="py-1.5 px-2 text-right">{{ number_format($r['by_size'][$size]) }}</td>@endforeach
                            </tr>
                        @endforeach
                        <tr class="bg-gray-50 font-bold">
                            <td class="py-1.5 px-2">Total</td>
                            @foreach($salesData['sizes'] as $size)<td class="py-1.5 px-2 text-right">{{ number_format($salesData['totals']['by_size'][$size]) }}</td>@endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    {{-- === SECTION 2: BATCH TRACEABILITY REPORT === --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Batch Traceability Report</h2>
                <p class="text-sm text-gray-500 mt-0.5">Track eggs from collection through sale or disposal</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('reports.export.batch.pdf') . '?' . $exportParams }}"
                    class="flex items-center gap-1 bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 text-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    PDF
                </a>
                <a href="{{ route('reports.export.batch.csv') . '?' . $exportParams }}"
                    class="flex items-center gap-1 bg-green-700 text-white px-3 py-2 rounded-lg hover:bg-green-800 text-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    CSV
                </a>
                <a href="{{ route('reports.export.batch.excel') . '?' . $exportParams }}"
                    class="flex items-center gap-1 bg-emerald-700 text-white px-3 py-2 rounded-lg hover:bg-emerald-800 text-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Excel
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Date</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Qty Collected</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Qty Sold</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Remaining</th>
                        <th class="text-right py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Sell-Through</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $b)
                        @php
                            $statusColor = match($b->batch_status) {
                                'Fully Sold'     => 'bg-green-100 text-green-700',
                                'Partially Sold' => 'bg-blue-100 text-blue-700',
                                'Spoiled'        => 'bg-red-100 text-red-700',
                                default          => 'bg-yellow-100 text-yellow-700',
                            };
                        @endphp
                        <tr class="border-b last:border-0 hover:bg-gray-50">
                            <td class="py-3 px-2 text-sm">{{ $b->date->format('M d, Y') }}</td>
                            <td class="text-right py-3 px-2 text-sm">{{ number_format($b->eggs_collected) }}</td>
                            <td class="text-right py-3 px-2 text-sm">{{ number_format($b->quantity_sold) }}</td>
                            <td class="text-right py-3 px-2 text-sm font-semibold">{{ number_format($b->remaining_stock) }}</td>
                            <td class="text-right py-3 px-2 text-sm text-[#4CAF50] font-semibold">{{ $b->sell_through_rate }}%</td>
                            <td class="py-3 px-2 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-semibold {{ $statusColor }}">{{ $b->batch_status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No batches traced for selected range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-3 italic">
            Sell-Through Rate = (Qty Sold ÷ Qty Collected) × 100. Remaining Stock = Qty Collected − Qty Sold − Spoiled.
        </p>
    </div>

    {{-- === SECTION 3: AUDIT LOG (Phase 3 item 2) ===
         Replaces the Audit Inconsistency Report: every recorded action, with
         the old inconsistency flags kept as a badge and a filter. Read-only —
         audit rows are never edited or deleted. --}}
    <div class="bg-white rounded-lg shadow-md p-6" id="audit-log">
        @php $auditExportParams = http_build_query(['start_date' => $startDate, 'end_date' => $endDate, 'period' => $period] + $auditFilter->params()); @endphp
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Audit Log</h2>
                <p class="text-sm text-gray-500 mt-0.5">Every recorded action in the system: sign-ins, entries, edits, approvals, imports, exports and settings changes. Times are Asia/Manila.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('reports.export.audit.pdf') . '?' . $auditExportParams }}" class="flex items-center gap-1 bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 text-sm">PDF</a>
                <a href="{{ route('reports.export.audit.csv') . '?' . $auditExportParams }}" class="flex items-center gap-1 bg-green-700 text-white px-3 py-2 rounded-lg hover:bg-green-800 text-sm">CSV</a>
                <a href="{{ route('reports.export.audit.excel') . '?' . $auditExportParams }}" class="flex items-center gap-1 bg-emerald-700 text-white px-3 py-2 rounded-lg hover:bg-emerald-800 text-sm">Excel</a>
            </div>
        </div>

        <form method="GET" action="{{ route('reports.index') }}#audit-log" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3 items-end mb-4">
            <input type="hidden" name="start_date" value="{{ $startDate }}" />
            <input type="hidden" name="end_date" value="{{ $endDate }}" />
            <input type="hidden" name="period" value="{{ $period }}" />
            <div>
                <label for="audit_from" class="block text-xs font-semibold text-gray-600 mb-1">From</label>
                <input type="date" id="audit_from" name="audit_from" value="{{ $auditFilter->from }}" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm" />
            </div>
            <div>
                <label for="audit_to" class="block text-xs font-semibold text-gray-600 mb-1">To</label>
                <input type="date" id="audit_to" name="audit_to" value="{{ $auditFilter->to }}" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm" />
            </div>
            <div>
                <label for="audit_user" class="block text-xs font-semibold text-gray-600 mb-1">User</label>
                <select id="audit_user" name="audit_user" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                    <option value="">Everyone</option>
                    @foreach($auditOptions['users'] as $u)
                        <option value="{{ $u->id }}" @selected($auditFilter->userId === $u->id)>{{ $u->name }} ({{ $u->role }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="audit_action" class="block text-xs font-semibold text-gray-600 mb-1">Action</label>
                <select id="audit_action" name="audit_action" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                    <option value="">All actions</option>
                    @foreach($auditOptions['actions'] as $a)
                        <option value="{{ $a }}" @selected($auditFilter->action === $a)>{{ \Illuminate\Support\Str::headline($a) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="audit_module" class="block text-xs font-semibold text-gray-600 mb-1">Record / Module</label>
                <select id="audit_module" name="audit_module" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                    <option value="">All records</option>
                    @foreach($auditOptions['modules'] as $m)
                        <option value="{{ $m }}" @selected($auditFilter->module === $m)>{{ \Illuminate\Support\Str::headline($m) }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 pb-1.5">
                <input type="checkbox" name="audit_flagged" value="1" @checked($auditFilter->flaggedOnly) class="rounded border-gray-300" />
                Flagged only
            </label>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-gray-700 text-white px-3 py-1.5 rounded text-sm hover:bg-gray-800">Filter</button>
                <a href="{{ route('reports.index', ['start_date' => $startDate, 'end_date' => $endDate, 'period' => $period]) }}#audit-log" class="flex-1 text-center bg-gray-100 text-gray-700 px-3 py-1.5 rounded text-sm hover:bg-gray-200">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">When</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">User</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Action</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Record / Module</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Details</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">IP Address</th>
                        <th class="text-left py-3 px-2 text-xs font-semibold text-gray-600 uppercase tracking-wide">Flag</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($auditLogs as $a)
                        <tr @class(['border-b last:border-0 align-top', 'bg-red-50' => $a->inconsistency_flagged && ! $a->resolved])>
                            <td class="py-2 px-2 text-sm whitespace-nowrap">{{ $a->created_at->copy()->setTimezone(\App\Support\ManilaDate::TZ)->format('M d, Y g:i:s A') }}</td>
                            <td class="py-2 px-2 text-sm">
                                {{ $a->user?->name ?? 'Not signed in' }}
                                @if($a->user)<span class="block text-xs text-gray-500">{{ ucfirst($a->user->role) }}</span>@endif
                            </td>
                            <td class="py-2 px-2 text-sm font-medium text-gray-800">{{ $a->action_label }}</td>
                            <td class="py-2 px-2 text-sm text-gray-700">{{ $a->module_label }}@if($a->model_id) <span class="text-gray-400">#{{ $a->model_id }}</span>@endif</td>
                            <td class="py-2 px-2 text-xs text-gray-700 max-w-md break-words">{{ \App\Support\AuditLogFilter::detailsText($a) }}</td>
                            <td class="py-2 px-2 text-xs text-gray-500 whitespace-nowrap">{{ $a->ip_address ?? '—' }}</td>
                            <td class="py-2 px-2 text-sm">
                                @if($a->inconsistency_flagged)
                                    <x-audit-flag :log="$a" />
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-gray-400 text-sm">No audit log entries match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $auditLogs->links() }}</div>
        <p class="text-xs text-gray-400 mt-3 italic">The audit log is append-only: entries cannot be edited or deleted. Flagged entries (for example, sold more than produced) can be marked resolved with a note by a manager or admin.</p>
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
                    label: 'Eggs Collected',
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
                maintainAspectRatio: false,
                plugins: {
                    title:  { display: true, text: 'Egg Production Trend' },
                    legend: { display: true },
                },
                scales: {
                    x: { title: { display: true, text: 'Date' }, ticks: { font: { size: 11 } }, grid: { display: false } },
                    y: { title: { display: true, text: 'Eggs collected' }, ticks: { font: { size: 11 } }, beginAtZero: false }
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
                    label: 'Revenue (₱)',
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
                maintainAspectRatio: false,
                plugins: {
                    title:   { display: true, text: 'Sales Revenue Trend' },
                    legend:  { display: true },
                    tooltip: { callbacks: { label: ctx => '₱' + ctx.parsed.y.toLocaleString() } }
                },
                scales: {
                    x: { title: { display: true, text: 'Date' }, ticks: { font: { size: 11 } }, grid: { display: false } },
                    y: { title: { display: true, text: 'Revenue (₱)' }, ticks: { callback: v => '₱' + v.toLocaleString(), font: { size: 11 } }, beginAtZero: true }
                }
            }
        });
    }
})();
</script>
@endif

{{-- Sales & Revenue chart (Phase 3 item 7). --}}
@if($salesData['rows']->isNotEmpty())
@unless($dailyData->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endunless
<script>
(function () {
    const rows = @json($salesData['rows']->values());
    const ctx  = document.getElementById('salesRevenueChart');
    if (!ctx) return;
    new Chart(ctx, {
        data: {
            labels: rows.map(r => r.label),
            datasets: [
                { type: 'bar',  label: 'Revenue (₱)',      data: rows.map(r => r.revenue), backgroundColor: '#4CAF50', borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: 'Eggs sold (pcs)', data: rows.map(r => r.pcs), borderColor: '#2196F3', backgroundColor: 'rgba(33,150,243,0.1)', borderWidth: 2, pointRadius: 3, tension: 0.3, yAxisID: 'y1' },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title:   { display: true, text: 'Sales & Revenue by ' + @json(strtolower(\App\Support\SalesReport::GROUPS[$salesReport->group])) },
                legend:  { display: true },
                tooltip: { callbacks: { label: c => c.dataset.yAxisID === 'y' ? '₱' + c.parsed.y.toLocaleString() : c.parsed.y.toLocaleString() + ' pcs' } }
            },
            scales: {
                x:  { title: { display: true, text: 'Period' }, ticks: { font: { size: 11 } }, grid: { display: false } },
                y:  { title: { display: true, text: 'Revenue (₱)' }, beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString(), font: { size: 11 } } },
                y1: { title: { display: true, text: 'Eggs sold (pcs)' }, beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { size: 11 } } }
            }
        }
    });
})();
</script>
@endif
</x-app-layout>
