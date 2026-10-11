<x-app-layout>
<div class="space-y-6" x-data="{ editHen: null }">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Flock Records</h1>
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

    <div class="space-y-6">
        {{-- S17: Add Building (admin/manager) — creates a tracked building that
             shows up in Daily Entry right away. Replaces the old "Add Hen Batch"
             form, whose batches never reached Daily Entry (no building number). --}}
        @if(auth()->user()->canManageRecords())
        <div class="bg-white rounded-lg shadow-md p-6" x-data="{ no: '{{ old('building_no') }}', name: '{{ old('batch_id') }}', nameTouched: {{ old('batch_id') ? 'true' : 'false' }} }">
            <h2 class="text-base font-bold text-gray-800 mb-1">Add Building</h2>
            <p class="text-sm text-gray-500 mb-4">The new building appears in Daily Entry immediately; its first day's population starts at the starting hen count.</p>
            <form method="POST" action="{{ route('flock-records.buildings.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Building Number <span class="text-red-500">*</span></label>
                        <input type="number" name="building_no" min="1" x-model="no" @input="if (!nameTouched) name = no ? 'Building ' + no : ''" placeholder="e.g. 7"
                            class="w-full px-3 py-2 border {{ $errors->has('building_no') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                        @error('building_no')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Flock / Batch Name</label>
                        <input type="text" name="batch_id" x-model="name" @input="nameTouched = true" placeholder="Building N"
                            class="w-full px-3 py-2 border {{ $errors->has('batch_id') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                        @error('batch_id')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Breed</label>
                        <input type="text" name="breed" value="{{ old('breed', 'Dekalb White') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Starting Hen Count <span class="text-red-500">*</span></label>
                        <input type="number" name="batch_size" min="1" value="{{ old('batch_size') }}" placeholder="0"
                            class="w-full px-3 py-2 border {{ $errors->has('batch_size') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                        @error('batch_size')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Placement Date <span class="text-red-500">*</span></label>
                        <input type="date" name="placement_date" value="{{ old('placement_date', \App\Support\ManilaDate::todayString()) }}" max="{{ \App\Support\ManilaDate::todayString() }}"
                            class="w-full px-3 py-2 border {{ $errors->has('placement_date') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                        @error('placement_date')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Optional"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Add Building</button>
                </div>
            </form>
        </div>
        @endif

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
                            <th class="text-left py-3 text-sm font-semibold text-gray-700">Location</th>
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
                                    @if($batch->isEnded())
                                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">Culled — {{ $batch->ended_at->format('M d, Y') }}</span>
                                    @endif
                                </td>
                                <td class="py-3 text-sm">{{ $batch->entry_date->format('Y-m-d') }}</td>
                                <td class="py-3 text-sm text-gray-600">
                                    @if($batch->pen_number || $batch->building)
                                        {{ implode(' — ', array_filter([$batch->pen_number, $batch->building])) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 text-sm text-gray-600">{{ $batch->notes ?? '—' }}</td>
                                <td class="text-right py-3 text-sm space-x-2">
                                    @if($batch->isEnded() && auth()->user()->role === 'admin')
                                        <form method="POST" action="{{ route('flock-records.hens.reopen', $batch) }}" class="inline"
                                              onsubmit="return confirm('Reopen {{ $batch->display_label }}? It will appear in Daily Entry again.')">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="text-green-700 hover:underline">Reopen building</button>
                                        </form>
                                    @endif
                                    @if(auth()->user()->canManageRecords())
                                        <button @click="editHen = {{ $batch->id }}" class="text-blue-600 hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('flock-records.hens.destroy', $batch) }}" class="inline" onsubmit="return confirm('Delete this batch?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            {{-- Inline Edit Row --}}
                            <tr class="border-b bg-blue-50" x-show="editHen === {{ $batch->id }}" x-cloak>
                                <td colspan="7" class="py-4 px-2">
                                    <form method="POST" action="{{ route('flock-records.hens.update', $batch) }}" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 items-end">
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
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Pen Number</label>
                                            <input type="text" name="pen_number" value="{{ $batch->pen_number }}" placeholder="e.g. Pen A"
                                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Building</label>
                                            <input type="text" name="building" value="{{ $batch->building }}" placeholder="e.g. Building 1"
                                                class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
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
                            <tr><td colspan="7" class="py-8 text-center text-gray-400 text-sm">No hen batches recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
