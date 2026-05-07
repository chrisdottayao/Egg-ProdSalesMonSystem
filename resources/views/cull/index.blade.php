<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Cull Chicken Management</h1>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Anomaly Alert --}}
    @if($cullingAnomaly)
        <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded-lg flex items-start gap-3">
            <svg class="w-5 h-5 text-orange-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="text-sm text-orange-800 font-semibold">Culling Frequency Alert</p>
                <p class="text-sm text-orange-700 mt-1">
                    Total culled this month ({{ $monthStats['total_culled'] }}) exceeds the historical monthly average ({{ $monthStats['historical_avg'] }}).
                    Review flock age distribution and assess whether culling is affecting production rate.
                </p>
            </div>
        </div>
    @endif

    {{-- Add Cull Record Form --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Add Cull Record</h2>
        <form method="POST" action="{{ route('cull.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('date')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Number of Chickens Culled</label>
                    <input type="number" name="quantity_culled" value="{{ old('quantity_culled') }}" placeholder="0" min="1"
                        class="w-full px-3 py-2 border {{ $errors->has('quantity_culled') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('quantity_culled')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Reason for Culling</label>
                    <select name="reason" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                        @foreach(['Age','Low Productivity','Health Condition','Other'] as $r)
                            <option value="{{ $r }}" {{ old('reason') === $r ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                <textarea name="notes" rows="3" placeholder="Enter any notes..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">{{ old('notes') }}</textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Save</button>
            </div>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Summary</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-sm text-gray-600 mb-1">Total Culled This Month</div>
                <div class="text-3xl font-bold text-gray-800">{{ $monthStats['total_culled'] }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-sm text-gray-600 mb-1">Most Common Reason</div>
                <div class="text-2xl font-bold text-gray-800 break-words">{{ $monthStats['most_common_reason'] }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-sm text-gray-600 mb-1">Cull Events This Month</div>
                <div class="text-3xl font-bold text-gray-800">{{ $monthStats['total_events'] }}</div>
            </div>
            <div class="p-4 rounded-lg {{ $cullingAnomaly ? 'bg-orange-50' : 'bg-gray-50' }}">
                <div class="text-sm text-gray-600 mb-1">vs Historical Avg</div>
                <div class="text-3xl font-bold {{ $cullingAnomaly ? 'text-orange-600' : 'text-green-600' }}">
                    {{ $monthStats['vs_avg'] >= 0 ? '+' . $monthStats['vs_avg'] : $monthStats['vs_avg'] }}
                </div>
                <div class="text-xs text-gray-500 mt-1">Avg: {{ $monthStats['historical_avg'] }}/month</div>
            </div>
        </div>
    </div>

    {{-- Cull Records Table --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Cull Records</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Date</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Chickens Culled</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Reason</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Notes</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cullRecords as $record)
                        <tr class="border-b last:border-0 hover:bg-gray-50">
                            <td class="py-3 text-sm">{{ $record->date->format('Y-m-d') }}</td>
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
                        <tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">No cull records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $cullRecords->links() }}</div>

        <div class="mt-4 p-4 bg-blue-50 border-l-4 border-blue-500 text-sm text-gray-700">
            <strong>Note:</strong> Cull data feeds directly into the predictive analytics module as an input variable. Culling affects active laying hen count and impacts production rate forecasts.
        </div>
    </div>
</div>
</x-app-layout>
