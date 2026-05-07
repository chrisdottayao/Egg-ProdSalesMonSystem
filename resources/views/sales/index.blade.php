<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sales</h2>
            <a href="{{ route('sales.create') }}" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">+ New Sale</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-100 text-green-800 px-4 py-3 rounded-md text-sm">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paid</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($sales as $sale)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $sale->sale_date->format('M d, Y') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    @if($sale->customer)
                                        <a href="{{ route('customers.show', $sale->customer) }}" class="text-green-600 hover:underline">{{ $sale->customer->name }}</a>
                                    @else
                                        <span class="text-gray-400">Walk-in</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">₱{{ number_format($sale->total_amount, 2) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">₱{{ number_format($sale->amount_paid, 2) }}</td>
                                <td class="px-6 py-4 text-sm {{ $sale->balance > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                                    ₱{{ number_format($sale->balance, 2) }}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $badge = match($sale->payment_status) {
                                            'paid'    => 'bg-green-100 text-green-800',
                                            'partial' => 'bg-yellow-100 text-yellow-800',
                                            'unpaid'  => 'bg-red-100 text-red-800',
                                        };
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $badge }}">{{ ucfirst($sale->payment_status) }}</span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm space-x-3">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-gray-600 hover:underline">View</a>
                                    <a href="{{ route('sales.edit', $sale) }}" class="text-blue-600 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('sales.destroy', $sale) }}" class="inline" onsubmit="return confirm('Delete this sale?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-10 text-center text-gray-400">No sales recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $sales->links() }}</div>
        </div>
    </div>
</x-app-layout>
