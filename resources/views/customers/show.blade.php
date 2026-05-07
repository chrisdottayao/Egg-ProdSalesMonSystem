<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }}</h2>
            <div class="flex gap-3">
                <a href="{{ route('customers.edit', $customer) }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">Edit</a>
                <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 hover:underline self-center">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Phone</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $customer->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Address</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $customer->address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Notes</dt>
                        <dd class="mt-1 text-sm text-gray-600">{{ $customer->notes ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-sm font-semibold text-gray-700">Recent Sales</h3>
                    <a href="{{ route('sales.create') }}" class="text-sm text-green-600 hover:underline">+ New Sale</a>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paid</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($customer->sales as $sale)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $sale->sale_date->format('M d, Y') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-900">₱{{ number_format($sale->total_amount, 2) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-900">₱{{ number_format($sale->amount_paid, 2) }}</td>
                                <td class="px-6 py-3">
                                    @php
                                        $badge = match($sale->payment_status) {
                                            'paid'    => 'bg-green-100 text-green-800',
                                            'partial' => 'bg-yellow-100 text-yellow-800',
                                            'unpaid'  => 'bg-red-100 text-red-800',
                                        };
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $badge }}">{{ ucfirst($sale->payment_status) }}</span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-sm text-blue-600 hover:underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">No sales yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
