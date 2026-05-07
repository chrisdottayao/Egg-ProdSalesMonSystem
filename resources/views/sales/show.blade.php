<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sale #{{ $sale->id }}</h2>
            <div class="flex gap-3">
                <a href="{{ route('sales.edit', $sale) }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">Edit</a>
                <a href="{{ route('sales.index') }}" class="text-sm text-gray-600 hover:underline self-center">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-6">
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Date</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $sale->sale_date->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Customer</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">
                            @if($sale->customer)
                                <a href="{{ route('customers.show', $sale->customer) }}" class="text-green-600 hover:underline">{{ $sale->customer->name }}</a>
                            @else
                                Walk-in
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Payment</dt>
                        <dd class="mt-1">
                            @php
                                $badge = match($sale->payment_status) {
                                    'paid'    => 'bg-green-100 text-green-800',
                                    'partial' => 'bg-yellow-100 text-yellow-800',
                                    'unpaid'  => 'bg-red-100 text-red-800',
                                };
                            @endphp
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $badge }}">{{ ucfirst($sale->payment_status) }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Balance</dt>
                        <dd class="mt-1 text-sm font-bold {{ $sale->balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                            ₱{{ number_format($sale->balance, 2) }}
                        </dd>
                    </div>
                </dl>
                @if($sale->notes)
                    <p class="mt-4 text-sm text-gray-600">{{ $sale->notes }}</p>
                @endif
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Items</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Egg Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($sale->items as $item)
                            <tr>
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $item->egg_type }}</td>
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $item->quantity }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ ucfirst($item->unit) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-900">₱{{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900 text-right">₱{{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-3 text-sm font-semibold text-gray-700 text-right">Total</td>
                            <td class="px-6 py-3 text-sm font-bold text-gray-900 text-right">₱{{ number_format($sale->total_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-6 py-2 text-sm text-gray-500 text-right">Amount Paid</td>
                            <td class="px-6 py-2 text-sm text-gray-900 text-right">₱{{ number_format($sale->amount_paid, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
