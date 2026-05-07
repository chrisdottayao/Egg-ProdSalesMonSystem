<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Sale #{{ $sale->id }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('sales.update', $sale) }}" id="saleForm">
                    @csrf @method('PATCH')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <x-input-label for="customer_id" value="Customer" />
                            <select id="customer_id" name="customer_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">— Walk-in / No Customer —</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" {{ old('customer_id', $sale->customer_id) == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="sale_date" value="Sale Date *" />
                            <x-text-input id="sale_date" name="sale_date" type="date" class="mt-1 block w-full" :value="old('sale_date', $sale->sale_date->format('Y-m-d'))" required />
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-sm font-semibold text-gray-700">Items</h3>
                            <button type="button" onclick="addItem()" class="text-sm text-green-600 hover:underline font-medium">+ Add Item</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200 rounded-md">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Egg Type</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Qty</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Unit</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Unit Price</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Subtotal</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex justify-end mb-4">
                        <div class="text-right">
                            <span class="text-sm text-gray-500">Total Amount:</span>
                            <span id="totalDisplay" class="ml-2 text-lg font-bold text-gray-900">₱0.00</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <x-input-label for="amount_paid" value="Amount Paid *" />
                            <x-text-input id="amount_paid" name="amount_paid" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('amount_paid', $sale->amount_paid)" required />
                        </div>
                    </div>

                    <div class="mb-6">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('notes', $sale->notes) }}</textarea>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Update Sale</x-primary-button>
                        <a href="{{ route('sales.show', $sale) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let itemIndex = 0;

        function addItem(data = {}) {
            const body = document.getElementById('itemsBody');
            const idx = itemIndex++;
            const row = document.createElement('tr');
            row.className = 'border-t border-gray-200';
            row.innerHTML = `
                <td class="px-2 py-2">
                    <select name="items[${idx}][egg_type]" class="w-full border-gray-300 rounded text-sm" required>
                        <option value="Large" ${data.egg_type==='Large'?'selected':''}>Large</option>
                        <option value="Medium" ${data.egg_type==='Medium'?'selected':''}>Medium</option>
                        <option value="Small" ${data.egg_type==='Small'?'selected':''}>Small</option>
                        <option value="Cracked" ${data.egg_type==='Cracked'?'selected':''}>Cracked</option>
                        <option value="Mixed" ${data.egg_type==='Mixed'?'selected':''}>Mixed</option>
                    </select>
                </td>
                <td class="px-2 py-2">
                    <input type="number" name="items[${idx}][quantity]" min="1" value="${data.quantity||1}" class="w-24 border-gray-300 rounded text-sm" oninput="recalcRow(this)" required />
                </td>
                <td class="px-2 py-2">
                    <select name="items[${idx}][unit]" class="border-gray-300 rounded text-sm">
                        <option value="tray" ${data.unit==='tray'||!data.unit?'selected':''}>Tray</option>
                        <option value="piece" ${data.unit==='piece'?'selected':''}>Piece</option>
                    </select>
                </td>
                <td class="px-2 py-2">
                    <input type="number" name="items[${idx}][unit_price]" min="0" step="0.01" value="${data.unit_price||''}" class="w-28 border-gray-300 rounded text-sm" oninput="recalcRow(this)" required placeholder="0.00" />
                </td>
                <td class="px-2 py-2 text-sm font-medium text-gray-900 subtotal-cell">₱${data.subtotal ? parseFloat(data.subtotal).toFixed(2) : '0.00'}</td>
                <td class="px-2 py-2">
                    <button type="button" onclick="removeRow(this)" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
                </td>
            `;
            body.appendChild(row);
            recalcTotal();
        }

        function recalcRow(el) {
            const row = el.closest('tr');
            const qty = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
            row.querySelector('.subtotal-cell').textContent = '₱' + (qty * price).toFixed(2);
            recalcTotal();
        }

        function recalcTotal() {
            let total = 0;
            document.querySelectorAll('.subtotal-cell').forEach(cell => {
                total += parseFloat(cell.textContent.replace('₱','')) || 0;
            });
            document.getElementById('totalDisplay').textContent = '₱' + total.toFixed(2);
        }

        function removeRow(btn) {
            btn.closest('tr').remove();
            recalcTotal();
        }

        // Seed existing items
        const existingItems = @json($sale->items);
        existingItems.forEach(item => addItem(item));
    </script>
</x-app-layout>
