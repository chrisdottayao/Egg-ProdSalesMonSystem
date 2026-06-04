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
    <div class="bg-white rounded-lg shadow-md p-6"
         x-data="{
             showImport: false,
             showSummary: {{ session('import_summary') ? 'true' : 'false' }},
             uploading: false
         }">

        {{-- Import Modal --}}
        <div x-show="showImport" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 max-w-lg w-full shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Import Sales History</h3>
                    <button @click="showImport = false; uploading = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-xs text-blue-800">
                    <p class="font-semibold mb-1">Expected CSV columns:</p>
                    <p class="font-mono">date, egg_size, quantity_sold, price_per_unit, notes</p>
                    <p class="mt-1">Valid egg sizes: Peewee, Small, Medium, Large, XL, Jumbo</p>
                </div>

                <form method="POST" action="{{ route('sales.import') }}"
                      enctype="multipart/form-data"
                      @submit="uploading = true">
                    @csrf

                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center mb-4 hover:border-blue-400 transition-colors">
                        <svg class="w-10 h-10 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <label class="cursor-pointer">
                            <span class="text-sm text-gray-600">Click to choose a CSV file</span>
                            <input type="file" name="csv_file" accept=".csv,.txt" required
                                   class="block mt-2 mx-auto text-sm text-gray-500 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                        </label>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <a href="{{ route('sales.import.template') }}"
                           class="flex items-center gap-1 text-sm text-blue-600 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download CSV Template (5 sample rows)
                        </a>
                    </div>

                    <div class="text-xs text-gray-500 mb-4 space-y-1">
                        <p>• Rows where date + egg_size already exists are skipped.</p>
                        <p>• Rows where quantity_sold exceeds eggs produced that day are rejected.</p>
                        <p>• <code class="bg-gray-100 px-1 rounded">total_amount</code> is auto-computed from quantity × price.</p>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" @click="showImport = false; uploading = false"
                            class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300 text-sm">
                            Cancel
                        </button>
                        <button type="submit" :disabled="uploading"
                            class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 text-sm font-medium disabled:opacity-60 flex items-center justify-center gap-2">
                            <svg x-show="uploading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="uploading ? 'Importing…' : 'Import CSV'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Summary Modal (auto-opens after import redirect) --}}
        <div x-show="showSummary" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 max-w-sm w-full shadow-xl">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">Import Complete</h3>
                </div>

                @if(session('import_summary'))
                    @php $s = session('import_summary'); @endphp
                    <ul class="space-y-2 text-sm mb-4">
                        <li class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            <span><strong class="text-green-700">{{ $s['imported'] }} record{{ $s['imported'] !== 1 ? 's' : '' }}</strong> imported successfully</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-yellow-500 rounded-full"></span>
                            <span><strong class="text-yellow-700">{{ $s['skipped'] }} record{{ $s['skipped'] !== 1 ? 's' : '' }}</strong> skipped (duplicates)</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-2 h-2 {{ $s['failed'] > 0 ? 'bg-red-500' : 'bg-gray-300' }} rounded-full"></span>
                            <span class="{{ $s['failed'] > 0 ? 'text-red-700' : 'text-gray-500' }}">
                                <strong>{{ $s['failed'] }} record{{ $s['failed'] !== 1 ? 's' : '' }}</strong> failed (validation errors)
                            </span>
                        </li>
                    </ul>

                    @if(session('import_error_token'))
                        <a href="{{ route('sales.import.errors', session('import_error_token')) }}"
                           class="flex items-center gap-1 text-sm text-red-600 hover:underline mb-4">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download error log ({{ $s['failed'] }} row{{ $s['failed'] !== 1 ? 's' : '' }})
                        </a>
                    @endif
                @endif

                <button @click="showSummary = false"
                    class="w-full bg-[#4CAF50] text-white py-2 rounded-lg hover:bg-green-600 text-sm font-medium">
                    Done
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">Sales History</h2>
            <button @click="showImport = true"
                class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import Sales History
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Date</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Size</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Qty Sold</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Price/Unit</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Total</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Sales Rate</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Remaining</th>
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
                            @php
                                $dateKey = $sale->date->format('Y-m-d');
                                $produced = $producedByDate[$dateKey] ?? null;
                                $salesRate = $produced > 0 ? round(($sale->quantity / $produced) * 100, 1) : null;
                                $remaining = $produced !== null ? $produced - $sale->quantity : null;
                            @endphp
                            <td class="text-right py-3 text-sm font-semibold text-[#4CAF50]">{{ $salesRate !== null ? $salesRate . '%' : '—' }}</td>
                            <td class="text-right py-3 text-sm text-gray-600">{{ $remaining !== null ? number_format($remaining) : '—' }}</td>
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
                        <tr><td colspan="9" class="py-8 text-center text-gray-400 text-sm">No sales records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $sales->links() }}</div>
    </div> {{-- end x-data (Sales History card) --}}
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
