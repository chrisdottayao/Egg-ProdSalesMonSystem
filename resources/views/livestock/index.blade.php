<x-app-layout>
<div class="space-y-6" x-data="{ tab: 'hens', editHen: null, editCattle: null }">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Livestock Records</h1>
        <div class="bg-green-50 border border-green-200 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
            <span class="text-gray-600">Active Laying Hens:</span>
            <span class="font-bold text-[#4CAF50] text-base">{{ number_format($activeHenCount) }}</span>
            <span class="text-gray-400 text-xs">(synced to Production module)</span>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Tabs --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6">
            <button @click="tab = 'hens'" :class="tab === 'hens' ? 'border-[#4CAF50] text-[#4CAF50]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="border-b-2 pb-3 text-sm font-semibold transition-colors">Hen Batches</button>
            <button @click="tab = 'cattle'" :class="tab === 'cattle' ? 'border-[#4CAF50] text-[#4CAF50]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="border-b-2 pb-3 text-sm font-semibold transition-colors">Cattle Records</button>
        </nav>
    </div>

    {{-- HEN BATCHES TAB --}}
    <div x-show="tab === 'hens'" class="space-y-6">
        {{-- Add Hen Batch Form --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4">Add Hen Batch</h2>
            <form method="POST" action="{{ route('livestock.hens.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Batch ID</label>
                        <input type="text" name="batch_id" value="{{ old('batch_id') }}" placeholder="e.g. BATCH-001"
                            class="w-full px-3 py-2 border {{ $errors->has('batch_id') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                        @error('batch_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Batch Size</label>
                        <input type="number" name="batch_size" value="{{ old('batch_size') }}" placeholder="0" min="1"
                            class="w-full px-3 py-2 border {{ $errors->has('batch_size') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                        @error('batch_size')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                            @foreach(['Active','Culled','Mortality'] as $s)
                                <option value="{{ $s }}" {{ old('status', 'Active') === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Entry Date</label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', date('Y-m-d')) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Optional notes"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Add Batch</button>
                </div>
            </form>
        </div>

        {{-- Hen Batches Table --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4">Hen Batches</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 text-sm font-semibold text-gray-700">Batch ID</th>
                            <th class="text-right py-3 text-sm font-semibold text-gray-700">Size</th>
                            <th class="text-left py-3 text-sm font-semibold text-gray-700">Status</th>
                            <th class="text-left py-3 text-sm font-semibold text-gray-700">Entry Date</th>
                            <th class="text-left py-3 text-sm font-semibold text-gray-700">Notes</th>
                            <th class="text-right py-3 text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($henBatches as $batch)
                            <tr class="border-b last:border-0 hover:bg-gray-50" x-show="editHen !== {{ $batch->id }}">
                                <td class="py-3 text-sm font-medium">{{ $batch->batch_id }}</td>
                                <td class="text-right py-3 text-sm">{{ number_format($batch->batch_size) }}</td>
                                <td class="py-3 text-sm">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $batch->status === 'Active' ? 'bg-green-100 text-green-800' : ($batch->status === 'Culled' ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800') }}">
                                        {{ $batch->status }}
                                    </span>
                                </td>
                                <td class="py-3 text-sm">{{ $batch->entry_date->format('Y-m-d') }}</td>
                                <td class="py-3 text-sm text-gray-600">{{ $batch->notes ?? '—' }}</td>
                                <td class="text-right py-3 text-sm space-x-2">
                                    <button @click="editHen = {{ $batch->id }}" class="text-blue-600 hover:underline">Edit</button>
                                    <form method="POST" action="{{ route('livestock.hens.destroy', $batch) }}" class="inline" onsubmit="return confirm('Delete this batch?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            {{-- Inline Edit Row --}}
                            <tr class="border-b bg-blue-50" x-show="editHen === {{ $batch->id }}" x-cloak>
                                <td colspan="6" class="py-4 px-2">
                                    <form method="POST" action="{{ route('livestock.hens.update', $batch) }}" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
                                        @csrf @method('PATCH')
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Batch ID</label>
                                            <input type="text" name="batch_id" value="{{ $batch->batch_id }}"
                                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" required />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Size</label>
                                            <input type="number" name="batch_size" value="{{ $batch->batch_size }}" min="1"
                                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" required />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                                            <select name="status" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]">
                                                @foreach(['Active','Culled','Mortality'] as $s)
                                                    <option value="{{ $s }}" {{ $batch->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Entry Date</label>
                                            <input type="date" name="entry_date" value="{{ $batch->entry_date->format('Y-m-d') }}"
                                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" required />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                                            <input type="text" name="notes" value="{{ $batch->notes }}"
                                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="bg-[#4CAF50] text-white px-3 py-1.5 rounded text-sm font-medium hover:bg-green-600">Save</button>
                                            <button type="button" @click="editHen = null" class="text-gray-600 px-3 py-1.5 rounded text-sm border border-gray-300 hover:bg-gray-100">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No hen batches recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- CATTLE RECORDS TAB --}}
    <div x-show="tab === 'cattle'" class="space-y-6">
        {{-- Add Cattle Form --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4">Add Cattle Record</h2>
            <form method="POST" action="{{ route('livestock.cattle.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ear Tag</label>
                        <input type="text" name="ear_tag" value="{{ old('ear_tag') }}" placeholder="e.g. CT-001"
                            class="w-full px-3 py-2 border {{ $errors->has('ear_tag') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                        @error('ear_tag')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                            @foreach(['Active','Sold','Deceased'] as $s)
                                <option value="{{ $s }}" {{ old('status', 'Active') === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Entry Date</label>
                        <input type="date" name="entry_date" value="{{ old('entry_date', date('Y-m-d')) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    </div>
                    <div class="lg:col-span-3">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Optional notes"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Add Cattle</button>
                </div>
            </form>
        </div>

        {{-- Cattle Records Table --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4">Cattle Records</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 text-sm font-semibold text-gray-700">Ear Tag</th>
                            <th class="text-left py-3 text-sm font-semibold text-gray-700">Status</th>
                            <th class="text-left py-3 text-sm font-semibold text-gray-700">Entry Date</th>
                            <th class="text-left py-3 text-sm font-semibold text-gray-700">Notes</th>
                            <th class="text-right py-3 text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cattleRecords as $cattle)
                            <tr class="border-b last:border-0 hover:bg-gray-50" x-show="editCattle !== {{ $cattle->id }}">
                                <td class="py-3 text-sm font-medium">{{ $cattle->ear_tag }}</td>
                                <td class="py-3 text-sm">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $cattle->status === 'Active' ? 'bg-green-100 text-green-800' : ($cattle->status === 'Sold' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                                        {{ $cattle->status }}
                                    </span>
                                </td>
                                <td class="py-3 text-sm">{{ $cattle->entry_date->format('Y-m-d') }}</td>
                                <td class="py-3 text-sm text-gray-600">{{ $cattle->notes ?? '—' }}</td>
                                <td class="text-right py-3 text-sm space-x-2">
                                    <button @click="editCattle = {{ $cattle->id }}" class="text-blue-600 hover:underline">Edit</button>
                                    <form method="POST" action="{{ route('livestock.cattle.destroy', $cattle) }}" class="inline" onsubmit="return confirm('Delete this cattle record?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            {{-- Inline Edit Row --}}
                            <tr class="border-b bg-blue-50" x-show="editCattle === {{ $cattle->id }}" x-cloak>
                                <td colspan="5" class="py-4 px-2">
                                    <form method="POST" action="{{ route('livestock.cattle.update', $cattle) }}" class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                                        @csrf @method('PATCH')
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Ear Tag</label>
                                            <input type="text" name="ear_tag" value="{{ $cattle->ear_tag }}"
                                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" required />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                                            <select name="status" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]">
                                                @foreach(['Active','Sold','Deceased'] as $s)
                                                    <option value="{{ $s }}" {{ $cattle->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Entry Date</label>
                                            <input type="date" name="entry_date" value="{{ $cattle->entry_date->format('Y-m-d') }}"
                                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" required />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                                            <input type="text" name="notes" value="{{ $cattle->notes }}"
                                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                        </div>
                                        <div class="col-span-2 md:col-span-4 flex gap-2 justify-end">
                                            <button type="submit" class="bg-[#4CAF50] text-white px-3 py-1.5 rounded text-sm font-medium hover:bg-green-600">Save</button>
                                            <button type="button" @click="editCattle = null" class="text-gray-600 px-3 py-1.5 rounded text-sm border border-gray-300 hover:bg-gray-100">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">No cattle records yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
