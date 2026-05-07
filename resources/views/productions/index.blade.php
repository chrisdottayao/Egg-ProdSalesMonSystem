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
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">Production History</h2>
        </div>

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
    </div>
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
