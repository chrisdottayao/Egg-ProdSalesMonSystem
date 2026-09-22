<x-app-layout>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Farm Expenses</h1>
        <a href="{{ route('expenses.summary') }}" class="flex items-center gap-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            View Summary
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Add Expense Form --}}
    <div class="bg-white rounded-lg shadow-md p-6" x-data="{ category: '{{ old('category', 'feed') }}', isRecurring: {{ old('is_recurring') ? 'true' : 'false' }} }">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Add Expense</h2>
        <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('date')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Building</label>
                    <select name="building_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                        <option value="">Farm-wide</option>
                        @foreach($buildings as $b)
                            <option value="{{ $b->id }}" {{ (string) old('building_id') === (string) $b->id ? 'selected' : '' }}>
                                {{ $b->display_label }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Farm-wide expenses are allocated to buildings by population share.</p>
                    @error('building_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                    <select name="category" x-model="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required>
                        @foreach(\App\Models\Expense::CATEGORIES as $key => $meta)
                            <option value="{{ $key }}" {{ old('category', 'feed') === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <input type="text" name="description" value="{{ old('description', $feedDefaults['brand'] . ' layer mash') }}" placeholder="e.g. Trovite 5kg"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('description')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Feed fields — kg is mandatory for this category --}}
            <div x-show="category === 'feed'" x-cloak class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-green-50 rounded-lg">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Number of Bags</label>
                    <input type="number" name="bags" x-ref="bags" value="{{ old('bags') }}" step="0.01" min="0" placeholder="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('bags')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Kg per Bag</label>
                    <input type="number" name="kg_per_bag" x-ref="kgPerBag" value="{{ old('kg_per_bag', $feedDefaults['kg_per_bag']) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('kg_per_bag')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Price per Bag (₱)</label>
                    <input type="number" name="price_per_bag" x-ref="pricePerBag" value="{{ old('price_per_bag', $feedDefaults['price_per_bag']) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('price_per_bag')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col justify-center">
                    <span class="text-sm text-gray-600">Total kg / Amount</span>
                    <span class="text-lg font-bold text-green-700" id="feedTotalsDisplay">— kg · ₱—</span>
                </div>
            </div>

            {{-- Generic fields — everything except feed --}}
            <div x-show="category !== 'feed'" x-cloak class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity</label>
                    <input type="number" name="quantity" x-ref="quantity" value="{{ old('quantity') }}" step="0.01" min="0" placeholder="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('quantity')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit</label>
                    <input type="text" name="unit" value="{{ old('unit') }}" placeholder="e.g. bottle, sack, kWh, dose"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('unit')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Unit Price (₱)</label>
                    <input type="number" name="unit_price" x-ref="unitPrice" value="{{ old('unit_price') }}" step="0.01" min="0" placeholder="0.00"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    @error('unit_price')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Amount (₱)</label>
                    <input type="number" name="amount" x-ref="amount" value="{{ old('amount') }}" step="0.01" min="0" placeholder="0.00"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    <p class="text-xs text-gray-500 mt-1">Auto-filled from qty × unit price; editable.</p>
                    @error('amount')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier</label>
                    <input type="text" name="supplier" value="{{ old('supplier') }}" placeholder="e.g. RMY Marketing"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                </div>

                <div class="flex items-center gap-2 mt-7">
                    <input type="checkbox" id="is_estimated" name="is_estimated" value="1" {{ old('is_estimated') ? 'checked' : '' }}
                        class="rounded border-gray-300 text-[#4CAF50] focus:ring-[#4CAF50]" />
                    <label for="is_estimated" class="text-sm text-gray-700">Estimated price/value (e.g. kasosyo estimate)</label>
                </div>

                <div class="flex items-center gap-2 mt-7">
                    <input type="checkbox" id="is_recurring" name="is_recurring" value="1" x-model="isRecurring"
                        class="rounded border-gray-300 text-[#4CAF50] focus:ring-[#4CAF50]" />
                    <label for="is_recurring" class="text-sm text-gray-700">Ongoing regimen (recurring)</label>
                </div>

                <div x-show="isRecurring" x-cloak>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Recurrence</label>
                    <input type="text" name="recurrence" value="{{ old('recurrence') }}" placeholder="e.g. weekly-Mon"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Receipt Photo (optional)</label>
                    <input type="file" name="receipt" accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-green-50 file:text-green-700 hover:file:bg-green-100" />
                    @error('receipt')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Optional"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                </div>
            </div>

            <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">
                Save Expense
            </button>
        </form>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="GET" action="{{ route('expenses.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Building</label>
                <select name="building_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All</option>
                    <option value="farm" {{ $buildingFilter === 'farm' ? 'selected' : '' }}>Farm-wide only</option>
                    @foreach($buildings as $b)
                        <option value="{{ $b->id }}" {{ (string) $buildingFilter === (string) $b->id ? 'selected' : '' }}>
                            {{ $b->display_label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">All</option>
                    @foreach(\App\Models\Expense::CATEGORIES as $key => $meta)
                        <option value="{{ $key }}" {{ $category === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 text-sm font-medium">Filter</button>
                <a href="{{ route('expenses.index') }}" class="flex-1 text-center bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm font-medium">Reset</a>
            </div>
        </form>
    </div>

    {{-- Expense List --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Expense Records</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Date</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Building</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Category</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Description</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Qty · Unit</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Unit Price</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Amount</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Supplier</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $catBadgeColors = [
                            'green' => 'bg-green-100 text-green-700', 'blue' => 'bg-blue-100 text-blue-700',
                            'purple' => 'bg-purple-100 text-purple-700', 'pink' => 'bg-pink-100 text-pink-700',
                            'orange' => 'bg-orange-100 text-orange-700', 'yellow' => 'bg-yellow-100 text-yellow-700',
                            'indigo' => 'bg-indigo-100 text-indigo-700', 'gray' => 'bg-gray-100 text-gray-700',
                        ];
                    @endphp
                    @forelse($expenses as $expense)
                        <tr class="border-b last:border-0 hover:bg-gray-50 align-top">
                            <td class="py-3 text-sm">{{ $expense->date->format('Y-m-d') }}</td>
                            <td class="py-3 text-sm">{{ $expense->building ? $expense->building->display_label : 'Farm-wide' }}</td>
                            <td class="py-3 text-sm">
                                <span class="text-xs px-2 py-1 rounded-full font-semibold {{ $catBadgeColors[$expense->category_color] }}">{{ $expense->category_label }}</span>
                            </td>
                            <td class="py-3 text-sm text-gray-700">
                                {{ $expense->description }}
                                @if($expense->receipt_path)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($expense->receipt_path) }}" target="_blank" class="block mt-1">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($expense->receipt_path) }}" class="w-12 h-12 object-cover rounded border border-gray-200" alt="Receipt" />
                                    </a>
                                @endif
                            </td>
                            <td class="text-right py-3 text-sm">
                                @if($expense->category === 'feed')
                                    {{ number_format($expense->quantity, 0) }} bags · {{ number_format($expense->kg_total, 0) }} kg
                                @else
                                    {{ $expense->quantity !== null ? number_format($expense->quantity, 2) : '—' }} {{ $expense->unit }}
                                @endif
                            </td>
                            <td class="text-right py-3 text-sm">{{ $expense->unit_price !== null ? '₱' . number_format($expense->unit_price, 2) : '—' }}</td>
                            <td class="text-right py-3 text-sm font-semibold text-[#4CAF50]">
                                ₱{{ number_format($expense->amount, 2) }}
                                @if($expense->is_estimated)
                                    <span class="block text-xs font-normal text-orange-600">Estimated</span>
                                @endif
                                @if($expense->is_recurring)
                                    <span class="block text-xs font-normal text-blue-600">Recurring ({{ $expense->recurrence }})</span>
                                @endif
                            </td>
                            <td class="py-3 text-sm text-gray-600">{{ $expense->supplier ?? '—' }}</td>
                            <td class="text-right py-3 text-sm space-x-2 whitespace-nowrap">
                                <a href="{{ route('expenses.edit', $expense) }}" class="text-blue-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="inline" onsubmit="return confirm('Delete this expense?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-8 text-center text-gray-400 text-sm">No expense records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $expenses->links() }}</div>
    </div>
</div>

<script>
    (function () {
        const bags        = document.querySelector('input[name="bags"]');
        const kgPerBag    = document.querySelector('input[name="kg_per_bag"]');
        const pricePerBag = document.querySelector('input[name="price_per_bag"]');
        const feedTotals  = document.getElementById('feedTotalsDisplay');

        function updateFeedTotals() {
            const b  = parseFloat(bags?.value) || 0;
            const kg = parseFloat(kgPerBag?.value) || 0;
            const pr = parseFloat(pricePerBag?.value) || 0;
            if (feedTotals) {
                feedTotals.textContent = (b * kg).toLocaleString() + ' kg · ₱' + (b * pr).toLocaleString(undefined, {minimumFractionDigits: 2});
            }
        }
        [bags, kgPerBag, pricePerBag].forEach(el => el?.addEventListener('input', updateFeedTotals));
        updateFeedTotals();

        const quantity  = document.querySelector('input[name="quantity"]');
        const unitPrice = document.querySelector('input[name="unit_price"]');
        const amount    = document.querySelector('input[name="amount"]');

        function updateAmount() {
            const q = parseFloat(quantity?.value) || 0;
            const p = parseFloat(unitPrice?.value) || 0;
            if (amount && q > 0 && p > 0) {
                amount.value = (q * p).toFixed(2);
            }
        }
        [quantity, unitPrice].forEach(el => el?.addEventListener('input', updateAmount));
    })();
</script>
</x-app-layout>
