<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs text-gray-500 uppercase font-medium">Active Flocks</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['active_flocks'] }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ number_format($stats['total_birds']) }} birds</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs text-gray-500 uppercase font-medium">Eggs Today</p>
                    <p class="mt-1 text-3xl font-bold text-green-600">{{ number_format($stats['eggs_today']) }}</p>
                    <p class="text-xs text-gray-400 mt-1">collected</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs text-gray-500 uppercase font-medium">Eggs This Month</p>
                    <p class="mt-1 text-3xl font-bold text-green-700">{{ number_format($stats['eggs_this_month']) }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ now()->format('F Y') }}</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs text-gray-500 uppercase font-medium">Sales This Month</p>
                    <p class="mt-1 text-3xl font-bold text-blue-600">₱{{ number_format($stats['sales_this_month'], 2) }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ now()->format('F Y') }}</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs text-gray-500 uppercase font-medium">Unpaid Balance</p>
                    <p class="mt-1 text-3xl font-bold {{ $stats['unpaid_balance'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                        ₱{{ number_format($stats['unpaid_balance'], 2) }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">outstanding</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs text-gray-500 uppercase font-medium">Customers</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['total_customers'] }}</p>
                    <p class="text-xs text-gray-400 mt-1">registered</p>
                </div>

            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h3>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('productions.create') }}" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">+ Log Production</a>
                    <a href="{{ route('sales.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">+ Record Sale</a>
                    <a href="{{ route('flocks.create') }}" class="bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium px-4 py-2 rounded-md">+ Add Flock</a>
                    <a href="{{ route('customers.create') }}" class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium px-4 py-2 rounded-md">+ Add Customer</a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Recent Productions -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-sm font-semibold text-gray-700">Recent Production</h3>
                        <a href="{{ route('productions.index') }}" class="text-xs text-green-600 hover:underline">View all</a>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100">
                        <tbody>
                            @forelse($recentProductions as $record)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 text-sm text-gray-600">{{ $record->date->format('M d') }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-900">{{ $record->flock->name }}</td>
                                    <td class="px-5 py-3 text-sm font-medium text-green-700 text-right">{{ number_format($record->total_eggs) }} eggs</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-6 text-center text-gray-400 text-sm">No records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Recent Sales -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-sm font-semibold text-gray-700">Recent Sales</h3>
                        <a href="{{ route('sales.index') }}" class="text-xs text-blue-600 hover:underline">View all</a>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100">
                        <tbody>
                            @forelse($recentSales as $sale)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 text-sm text-gray-600">{{ $sale->sale_date->format('M d') }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-900">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                                    <td class="px-5 py-3 text-sm font-medium text-blue-700 text-right">₱{{ number_format($sale->total_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-6 text-center text-gray-400 text-sm">No sales yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
