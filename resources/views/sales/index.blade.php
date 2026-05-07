<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Egg Sales Entry</h1>

    {{-- Hard block error --}}
    @if(session('hard_block'))
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <p class="text-sm text-red-800 font-semibold">Entry Blocked — Audit Violation</p>
                <p class="text-sm text-red-700 mt-1">{{ session('hard_block') }}</p>
            </div>
        </div>
    @endif

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Entry Form --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="{{ route('sales.store') }}" class="space-y-4" id="salesForm">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" id="saleDate" value="{{ old('date', date('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    <p class="text-xs text-gray-500 mt-1" id="producedHint"></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Egg Size</label>
                    <select name="egg_size" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                        @foreach(['Peewee','Small','Medium','Large','XL','Jumbo'] as $size)
                            <option value="{{ $size }}" {{ old('egg_size', 'Large') === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity Sold</label>
                    <input type="number" name="quantity" id="quantity" value="{{ old('quantity') }}" placeholder="0" min="1"
                        class="w-full px-3 py-2 border {{ $errors->has('quantity') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('quantity')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Price per Unit (₱)</label>
                    <input type="number" name="price_per_unit" id="pricePerUnit" value="{{ old('price_per_unit', '9.00') }}" step="0.01" min="0" placeholder="0.00"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Total Amount (₱)</label>
                    <input type="text" id="totalAmount" value="₱0.00" readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700 font-semibold" />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Remaining Eggs</label>
                    <input type="text" id="remainingEggs" value="—" readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-semibold text-gray-700" />
                    <p class="text-xs text-gray-500 mt-1">Produced − Sold</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                <textarea name="notes" rows="3" placeholder="Enter any notes..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-between">
                <div class="bg-gray-50 px-4 py-2 rounded-lg">
                    <span class="text-sm text-gray-700">Sales Rate: </span>
                    <span class="text-lg font-bold text-[#4CAF50]" id="salesRate">0.0%</span>
                    <span class="text-xs text-gray-500 ml-2">(Qty Sold ÷ Eggs Produced × 100)</span>
                </div>
                <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Save</button>
            </div>
        </form>
    </div>

    {{-- Sales History --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Sales History</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Date</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Size</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Qty Sold</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Price/Unit</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Total</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Notes</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr class="border-b last:border-0 hover:bg-gray-50">
                            <td class="py-3 text-sm">{{ $sale->date->format('Y-m-d') }}</td>
                            <td class="py-3 text-sm">{{ $sale->egg_size }}</td>
                            <td class="text-right py-3 text-sm">{{ number_format($sale->quantity) }}</td>
                            <td class="text-right py-3 text-sm">₱{{ number_format($sale->price_per_unit, 2) }}</td>
                            <td class="text-right py-3 text-sm font-semibold text-[#4CAF50]">₱{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="py-3 text-sm text-gray-600">{{ $sale->notes ?? '—' }}</td>
                            <td class="text-right py-3 text-sm space-x-2">
                                <a href="{{ route('sales.edit', $sale) }}" class="text-blue-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('sales.destroy', $sale) }}" class="inline" onsubmit="return confirm('Delete this sale?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-gray-400 text-sm">No sales records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $sales->links() }}</div>
    </div>
</div>

<script>
    const qtyInput     = document.getElementById('quantity');
    const priceInput   = document.getElementById('pricePerUnit');
    const totalEl      = document.getElementById('totalAmount');
    const remainingEl  = document.getElementById('remainingEggs');
    const rateEl       = document.getElementById('salesRate');
    let produced       = null;

    function recalc() {
        const qty   = parseFloat(qtyInput.value)   || 0;
        const price = parseFloat(priceInput.value) || 0;
        totalEl.value = '₱' + (qty * price).toFixed(2);

        if (produced !== null) {
            const rem = produced - qty;
            remainingEl.value = rem;
            remainingEl.className = remainingEl.className.replace(/text-(red|gray)-\d+/g, '');
            remainingEl.classList.add(rem < 0 ? 'text-red-600' : 'text-gray-700');
            rateEl.textContent = produced > 0 ? ((qty / produced) * 100).toFixed(1) + '%' : '0.0%';
        } else {
            remainingEl.value = '—';
            rateEl.textContent = '0.0%';
        }
    }

    qtyInput?.addEventListener('input', recalc);
    priceInput?.addEventListener('input', recalc);
    recalc();
</script>
</x-app-layout>
