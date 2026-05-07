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
            <div class="text-sm text-gray-500 mt-1">collected today</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50] hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Revenue Today</span>
                <svg class="w-5 h-5 text-[#4CAF50]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="text-3xl font-bold text-gray-800">₱{{ number_format($stats['revenue_today'], 2) }}</div>
            <div class="text-sm text-gray-500 mt-1">from sales today</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50] hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Production Rate</span>
                <svg class="w-5 h-5 text-[#4CAF50]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div class="text-3xl font-bold text-gray-800">{{ $stats['production_rate'] }}%</div>
            <div class="text-sm text-gray-500 mt-1">{{ number_format($stats['active_hens']) }} active hens</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-[#4CAF50] hover:shadow-lg transition-shadow">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-600">Sales This Month</span>
                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="text-3xl font-bold text-gray-800">₱{{ number_format($stats['sales_this_month'], 2) }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ now()->format('F Y') }}</div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-lg shadow-md p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h3>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('productions.index') }}" class="bg-[#4CAF50] hover:bg-green-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">+ Log Production</a>
            <a href="{{ route('sales.index') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">+ Record Sale</a>
            <a href="{{ route('livestock.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">+ Add Livestock</a>
            <a href="{{ route('cull.index') }}" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">+ Cull Record</a>
        </div>
    </div>

    {{-- Recent Activity --}}
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
                        <th class="text-right py-2 text-sm font-semibold text-gray-700">Eggs Prod.</th>
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
</x-app-layout>
