<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Edit Expense</h1>

    <div class="bg-white rounded-lg shadow-md p-6" x-data="{ category: '{{ old('category', $expense->category) }}', isRecurring: {{ old('is_recurring', $expense->is_recurring) ? 'true' : 'false' }} }">
        <form method="POST" action="{{ route('expenses.update', $expense) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="{{ old('date', $expense->date->format('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('date')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Building</label>
                    <select name="building_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                        <option value="">Farm-wide</option>
                        @foreach($buildings as $b)
                            <option value="{{ $b->id }}" {{ (string) old('building_id', $expense->building_id) === (string) $b->id ? 'selected' : '' }}>
                                {{ $b->building_no ? 'Building ' . $b->building_no : $b->batch_id }}
                            </option>
                        @endforeach
                    </select>
                    @error('building_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                    <select name="category" x-model="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required>
                        @foreach(\App\Models\Expense::CATEGORIES as $key => $meta)
                            <option value="{{ $key }}" {{ old('category', $expense->category) === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <input type="text" name="description" value="{{ old('description', $expense->description) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('description')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div x-show="category === 'feed'" x-cloak class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-green-50 rounded-lg">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Number of Bags</label>
                    <input type="number" name="bags" value="{{ old('bags', $expense->quantity) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('bags')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Kg per Bag</label>
                    <input type="number" name="kg_per_bag" value="{{ old('kg_per_bag', $expense->quantity ? round($expense->kg_total / $expense->quantity, 2) : $feedDefaults['kg_per_bag']) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('kg_per_bag')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Price per Bag (₱)</label>
                    <input type="number" name="price_per_bag" value="{{ old('price_per_bag', $expense->unit_price) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('price_per_bag')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col justify-center text-sm text-gray-500">
                    Current kg total: <strong>{{ $expense->kg_total !== null ? number_format($expense->kg_total, 2) : '—' }} kg</strong>
                </div>
            </div>

            <div x-show="category !== 'feed'" x-cloak class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity</label>
                    <input type="number" name="quantity" value="{{ old('quantity', $expense->category !== 'feed' ? $expense->quantity : null) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('quantity')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit</label>
                    <input type="text" name="unit" value="{{ old('unit', $expense->unit) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('unit')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit Price (₱)</label>
                    <input type="number" name="unit_price" value="{{ old('unit_price', $expense->category !== 'feed' ? $expense->unit_price : null) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('unit_price')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Amount (₱)</label>
                    <input type="number" name="amount" value="{{ old('amount', $expense->amount) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('amount')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier</label>
                    <input type="text" name="supplier" value="{{ old('supplier', $expense->supplier) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                </div>

                <div class="flex items-center gap-2 mt-7">
                    <input type="checkbox" id="is_estimated" name="is_estimated" value="1" {{ old('is_estimated', $expense->is_estimated) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-[#4CAF50] focus:ring-[#4CAF50]" />
                    <label for="is_estimated" class="text-sm text-gray-700">Estimated price/value</label>
                </div>

                <div class="flex items-center gap-2 mt-7">
                    <input type="checkbox" id="is_recurring" name="is_recurring" value="1" x-model="isRecurring"
                        class="rounded border-gray-300 text-[#4CAF50] focus:ring-[#4CAF50]" />
                    <label for="is_recurring" class="text-sm text-gray-700">Ongoing regimen (recurring)</label>
                </div>

                <div x-show="isRecurring" x-cloak>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Recurrence</label>
                    <input type="text" name="recurrence" value="{{ old('recurrence', $expense->recurrence) }}" placeholder="e.g. weekly-Mon"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Replace Receipt Photo (optional)</label>
                    @if($expense->receipt_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($expense->receipt_path) }}" class="w-16 h-16 object-cover rounded border border-gray-200 mb-2" alt="Current receipt" />
                    @endif
                    <input type="file" name="receipt" accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-green-50 file:text-green-700 hover:file:bg-green-100" />
                    @error('receipt')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes', $expense->notes) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">
                    Update Expense
                </button>
                <a href="{{ route('expenses.index') }}" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
