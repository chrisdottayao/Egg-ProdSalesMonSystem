<x-app-layout>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Edit Sale Record</h1>
        <a href="{{ route('sales.index') }}" class="text-sm text-gray-600 hover:underline">← Back</a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="{{ route('sales.update', $sale) }}" class="space-y-4">
            @csrf @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="{{ old('date', $sale->date->format('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('date')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Egg Size</label>
                    <select name="egg_size" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                        @foreach(['Peewee','Small','Medium','Large','XL','Jumbo'] as $size)
                            <option value="{{ $size }}" {{ old('egg_size', $sale->egg_size) === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity Sold</label>
                    <input type="number" name="quantity" id="quantity" value="{{ old('quantity', $sale->quantity) }}" min="1"
                        class="w-full px-3 py-2 border {{ $errors->has('quantity') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('quantity')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Price per Unit (₱)</label>
                    <input type="number" name="price_per_unit" id="pricePerUnit" value="{{ old('price_per_unit', $sale->price_per_unit) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Total Amount (₱)</label>
                    <input type="text" id="totalAmount" value="₱{{ number_format($sale->total_amount, 2) }}" readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700 font-semibold" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                <textarea name="notes" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">{{ old('notes', $sale->notes) }}</textarea>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Update</button>
                <a href="{{ route('sales.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    const qtyInput   = document.getElementById('quantity');
    const priceInput = document.getElementById('pricePerUnit');
    const totalEl    = document.getElementById('totalAmount');

    function recalc() {
        const qty   = parseFloat(qtyInput.value)   || 0;
        const price = parseFloat(priceInput.value) || 0;
        totalEl.value = '₱' + (qty * price).toFixed(2);
    }

    qtyInput?.addEventListener('input', recalc);
    priceInput?.addEventListener('input', recalc);
</script>
</x-app-layout>
