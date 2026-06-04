<x-app-layout>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Egg Production Entry</h1>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Entry Form --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="{{ route('productions.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('date')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Eggs Collected</label>
                    <input type="number" name="eggs_collected" value="{{ old('eggs_collected') }}" placeholder="0" min="0" max="10000"
                        class="w-full px-3 py-2 border {{ $errors->has('eggs_collected') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('eggs_collected')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Active Hens <span class="text-red-500">*</span></label>
                    <input type="number" name="active_hens" value="{{ old('active_hens', $activeHens ?: '') }}" placeholder="0" min="1"
                        class="w-full px-3 py-2 border {{ $errors->has('active_hens') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('active_hens')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-gray-500 mt-1">Required for every entry</p>
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
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Egg Weight (avg in grams)</label>
                    <input type="number" name="egg_weight" value="{{ old('egg_weight') }}" placeholder="0" step="0.1" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Mortality Count</label>
                    <input type="number" name="mortality" value="{{ old('mortality', 0) }}" placeholder="0" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Observational Notes</label>
                <textarea name="notes" rows="3" placeholder="Enter any observations..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-between">
                <div class="bg-gray-50 px-4 py-2 rounded-lg" id="rateDisplay">
                    <span class="text-sm text-gray-700">Production Rate: </span>
                    <span class="text-lg font-bold text-[#4CAF50]" id="rateValue">0%</span>
                </div>
                <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">
                    Save
                </button>
            </div>
        </form>
    </div>

    {{-- Production History --}}
    <div class="bg-white rounded-lg shadow-md p-6" x-data="{ showHistoricalImport: false, uploading: false }">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">Production History</h2>
            <button @click="showHistoricalImport = true"
                class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import Historical Logbooks
            </button>
        </div>

        {{-- Historical Logbook Import Modal --}}
        <div x-show="showHistoricalImport" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg p-6 max-w-lg w-full shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Import Historical Logbooks</h3>
                    <button @click="showHistoricalImport = false; uploading = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- CSV Format Reference --}}
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-xs text-blue-800">
                    <p class="font-semibold mb-1">Expected CSV columns:</p>
                    <p class="font-mono">date, eggs_collected, active_hens, egg_size, egg_weight, mortality_count, eggs_sold, price_per_unit, culled_count, cull_reason, notes</p>
                </div>

                <form method="POST" action="{{ route('productions.import.historical') }}"
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
                        <a href="{{ route('productions.import.template') }}"
                           class="flex items-center gap-1 text-sm text-blue-600 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download CSV Template (5 sample rows)
                        </a>
                    </div>

                    <div class="text-xs text-gray-500 mb-4 space-y-1">
                        <p>• Each row can insert into up to 3 tables simultaneously.</p>
                        <p>• Rows with existing dates are skipped (no duplicates).</p>
                        <p>• <strong>eggs_sold</strong> and <strong>culled_count</strong> are optional — leave 0 or blank.</p>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" @click="showHistoricalImport = false; uploading = false"
                            class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300 text-sm">
                            Cancel
                        </button>
                        <button type="submit" :disabled="uploading"
                            class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 text-sm font-medium disabled:opacity-60 flex items-center justify-center gap-2">
                            <svg x-show="uploading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="uploading ? 'Importing…' : 'Import Logbook'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Import Summary (shown after redirect) --}}
        @if(session('import_summary'))
            @php $s = session('import_summary'); @endphp
            <div class="mb-4 p-4 bg-green-50 border border-green-300 rounded-lg text-sm">
                <p class="font-bold text-green-800 mb-2">Historical Import Complete</p>
                <ul class="space-y-1 text-green-700">
                    <li>Production records: <strong>{{ $s['prod_imported'] }} imported</strong>, {{ $s['prod_skipped'] }} skipped (duplicates)</li>
                    <li>Sales records: <strong>{{ $s['sales_imported'] }} imported</strong>, {{ $s['sales_skipped'] }} skipped</li>
                    <li>Cull records: <strong>{{ $s['cull_imported'] }} imported</strong>, {{ $s['cull_skipped'] }} skipped</li>
                    @if($s['errors'] > 0)
                        <li class="text-orange-700">Rows with errors: <strong>{{ $s['errors'] }}</strong>
                            @if(session('import_error_token'))
                                &mdash; <a href="{{ route('productions.import.errors', session('import_error_token')) }}"
                                           class="underline font-semibold">Download error log</a>
                            @endif
                        </li>
                    @else
                        <li class="text-green-600">No errors detected.</li>
                    @endif
                </ul>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Date</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Eggs</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Size</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Weight</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Active Hens</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Mortality</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Prod Rate</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Notes</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productions as $record)
                        <tr class="border-b last:border-0 hover:bg-gray-50">
                            <td class="py-3 text-sm">{{ $record->date->format('Y-m-d') }}</td>
                            <td class="text-right py-3 text-sm">{{ number_format($record->eggs_collected) }}</td>
                            <td class="py-3 text-sm">{{ $record->egg_size }}</td>
                            <td class="text-right py-3 text-sm">{{ $record->egg_weight ? $record->egg_weight . 'g' : '—' }}</td>
                            <td class="text-right py-3 text-sm">{{ number_format($record->active_hens) }}</td>
                            <td class="text-right py-3 text-sm">{{ $record->mortality }}</td>
                            <td class="text-right py-3 text-sm font-semibold text-[#4CAF50]">{{ $record->production_rate }}%</td>
                            <td class="py-3 text-sm text-gray-600">{{ $record->notes ?? '—' }}</td>
                            <td class="text-right py-3 text-sm space-x-2">
                                <a href="{{ route('productions.edit', $record) }}" class="text-blue-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('productions.destroy', $record) }}" class="inline" onsubmit="return confirm('Delete this record?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-8 text-center text-gray-400 text-sm">No production records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-sm italic text-gray-500 mt-4">Active hen count syncs automatically with the Livestock Records module.</p>
        <div class="mt-3">{{ $productions->links() }}</div>
    </div> {{-- end x-data="{ showImport }" --}}
</div>

<script>
    const eggsInput = document.querySelector('[name="eggs_collected"]');
    const hensInput = document.querySelector('[name="active_hens"]');
    const rateEl = document.getElementById('rateValue');

    function updateRate() {
        const eggs = parseFloat(eggsInput.value) || 0;
        const hens = parseFloat(hensInput.value) || 0;
        rateEl.textContent = hens > 0 ? ((eggs / hens) * 100).toFixed(1) + '%' : '0%';
    }

    eggsInput?.addEventListener('input', updateRate);
    hensInput?.addEventListener('input', updateRate);
</script>
</x-app-layout>
