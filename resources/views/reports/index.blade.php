<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Reports & Analytics</h1>

    {{-- Date Filter --}}
    <div class="bg-white rounded-lg shadow-md p-5">
        <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                <input type="date" name="start_date" value="{{ $startDate }}"
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                <input type="date" name="end_date" value="{{ $endDate }}"
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </div>
            <button type="submit" class="bg-[#4CAF50] text-white px-5 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Apply Filter</button>
            <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:underline self-center">Reset</a>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-[#4CAF50]">
            <div class="text-xs text-gray-500 mb-1">Eggs Produced</div>
            <div class="text-2xl font-bold text-gray-800">{{ number_format($summary['total_eggs_produced']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-blue-500">
            <div class="text-xs text-gray-500 mb-1">Eggs Sold</div>
            <div class="text-2xl font-bold text-gray-800">{{ number_format($summary['total_eggs_sold']) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-yellow-500">
            <div class="text-xs text-gray-500 mb-1">Total Revenue</div>
            <div class="text-2xl font-bold text-gray-800">₱{{ number_format($summary['total_revenue'], 2) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-purple-500">
            <div class="text-xs text-gray-500 mb-1">Avg Production Rate</div>
            <div class="text-2xl font-bold text-gray-800">{{ $summary['avg_production_rate'] }}%</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-orange-400">
            <div class="text-xs text-gray-500 mb-1">Remaining Eggs</div>
            <div class="text-2xl font-bold {{ $summary['remaining_eggs'] < 0 ? 'text-red-600' : 'text-gray-800' }}">
                {{ number_format($summary['remaining_eggs']) }}
            </div>
        </div>
    </div>

    {{-- Daily Chart (canvas bar chart using vanilla JS) --}}
    @if($dailyData->isNotEmpty())
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-base font-bold text-gray-800 mb-4">Daily Production vs Sales</h2>
        <div class="overflow-x-auto">
            <canvas id="dailyChart" height="100"></canvas>
        </div>
    </div>
    @endif

    {{-- Daily Breakdown Table --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-base font-bold text-gray-800 mb-4">Daily Breakdown</h2>
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
</div>

@if($dailyData->isNotEmpty())
<script>
(function () {
    const data   = @json($dailyData->values());
    const labels = data.map(d => d.date);
    const eggs   = data.map(d => d.eggs);
    const sold   = data.map(d => d.sold);

    const canvas = document.getElementById('dailyChart');
    if (!canvas) return;
    const ctx    = canvas.getContext('2d');

    const BAR_W   = Math.max(20, Math.min(50, Math.floor((canvas.offsetWidth || 600) / (labels.length * 2 + 2))));
    const GAP     = BAR_W * 0.4;
    const GROUP_W = BAR_W * 2 + GAP;
    const PADDING = { top: 30, right: 20, bottom: 50, left: 50 };
    const W       = Math.max(labels.length * (GROUP_W + 10) + PADDING.left + PADDING.right, 400);
    const H       = 260;
    canvas.width  = W;
    canvas.height = H;

    const maxVal  = Math.max(...eggs, ...sold, 1);
    const chartH  = H - PADDING.top - PADDING.bottom;
    const chartW  = W - PADDING.left - PADDING.right;

    // Y axis
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth   = 1;
    for (let i = 0; i <= 5; i++) {
        const y = PADDING.top + chartH - (i / 5) * chartH;
        ctx.beginPath(); ctx.moveTo(PADDING.left, y); ctx.lineTo(W - PADDING.right, y); ctx.stroke();
        ctx.fillStyle = '#9ca3af'; ctx.font = '10px sans-serif'; ctx.textAlign = 'right';
        ctx.fillText(Math.round((i / 5) * maxVal), PADDING.left - 5, y + 4);
    }

    labels.forEach((label, i) => {
        const x = PADDING.left + i * (GROUP_W + 10) + 5;

        // Eggs bar (green)
        const eggsH = (eggs[i] / maxVal) * chartH;
        ctx.fillStyle = '#4CAF50';
        ctx.fillRect(x, PADDING.top + chartH - eggsH, BAR_W, eggsH);

        // Sold bar (blue)
        const soldH = (sold[i] / maxVal) * chartH;
        ctx.fillStyle = '#3b82f6';
        ctx.fillRect(x + BAR_W + GAP, PADDING.top + chartH - soldH, BAR_W, soldH);

        // Label
        ctx.fillStyle = '#6b7280'; ctx.font = '10px sans-serif'; ctx.textAlign = 'center';
        ctx.fillText(label, x + BAR_W + GAP / 2, H - PADDING.bottom + 15);
    });

    // Legend
    ctx.fillStyle = '#4CAF50'; ctx.fillRect(PADDING.left, H - 18, 12, 10);
    ctx.fillStyle = '#374151'; ctx.font = '11px sans-serif'; ctx.textAlign = 'left';
    ctx.fillText('Produced', PADDING.left + 15, H - 9);
    ctx.fillStyle = '#3b82f6'; ctx.fillRect(PADDING.left + 80, H - 18, 12, 10);
    ctx.fillStyle = '#374151';
    ctx.fillText('Sold', PADDING.left + 95, H - 9);
})();
</script>
@endif
</x-app-layout>
