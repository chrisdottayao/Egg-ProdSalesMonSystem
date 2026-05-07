<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Cull Chickens</h1>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Entry Form --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-base font-bold text-gray-800 mb-4">Record Culling</h2>
        <form method="POST" action="{{ route('cull.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('date')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Hen Batch</label>
                    <select name="hen_batch_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                        <option value="">— Select batch (optional) —</option>
                        @foreach($henBatches as $batch)
                            <option value="{{ $batch->id }}" {{ old('hen_batch_id') == $batch->id ? 'selected' : '' }}>
                                {{ $batch->batch_id }} ({{ number_format($batch->batch_size) }} hens)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity Culled</label>
                    <input type="number" name="quantity_culled" value="{{ old('quantity_culled') }}" placeholder="0" min="1"
                        class="w-full px-3 py-2 border {{ $errors->has('quantity_culled') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('quantity_culled')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="lg:col-span-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Reason</label>
                    <input type="text" name="reason" value="{{ old('reason') }}" placeholder="e.g. Disease, Low productivity, Age"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                </div>

                <div class="lg:col-span-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Additional notes..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-orange-500 text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition-colors font-medium">Save Cull Record</button>
            </div>
        </form>
    </div>

    {{-- Cull History --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-base font-bold text-gray-800 mb-4">Cull History</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Date</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Batch</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Qty Culled</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Reason</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Notes</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cullRecords as $record)
                        <tr class="border-b last:border-0 hover:bg-gray-50">
                            <td class="py-3 text-sm">{{ $record->date->format('Y-m-d') }}</td>
                            <td class="py-3 text-sm">{{ $record->henBatch?->batch_id ?? '—' }}</td>
                            <td class="text-right py-3 text-sm font-semibold text-orange-600">{{ number_format($record->quantity_culled) }}</td>
                            <td class="py-3 text-sm text-gray-700">{{ $record->reason ?? '—' }}</td>
                            <td class="py-3 text-sm text-gray-600">{{ $record->notes ?? '—' }}</td>
                            <td class="text-right py-3 text-sm">
                                <form method="POST" action="{{ route('cull.destroy', $record) }}" class="inline" onsubmit="return confirm('Delete this cull record?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No cull records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $cullRecords->links() }}</div>
    </div>
</div>
</x-app-layout>
