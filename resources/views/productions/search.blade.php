<x-app-layout>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Search Production Records</h1>
        <a href="{{ route('productions.index') }}" class="text-sm text-gray-600 hover:underline">← Back to Production</a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="GET" action="{{ route('productions.search') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                <input type="date" name="start_date" value="{{ $startDate }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                <input type="date" name="end_date" value="{{ $endDate }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Egg Size</label>
                <select name="egg_size" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                    <option value="">Any size</option>
                    @foreach($eggSizes as $size)
                        <option value="{{ $size }}" {{ $eggSize === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Contributor</label>
                <select name="contributor" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                    <option value="">Any contributor</option>
                    <option value="none" {{ $contributor === 'none' ? 'selected' : '' }}>— System / Bulk Import (no contributor) —</option>
                    @foreach($contributors as $user)
                        <option value="{{ $user->id }}" {{ (string) $contributor === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Or type a name below to free-search instead of picking from the list.</p>
                <input type="text" name="contributor" value="{{ is_numeric($contributor) || $contributor === 'none' ? '' : $contributor }}"
                    placeholder="Free-text contributor name…"
                    class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </div>
            <div>
                <button type="submit" class="w-full bg-[#4CAF50] text-white px-5 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Search</button>
            </div>
        </form>
    </div>

    @if(!$hasFilters)
        <div class="bg-white rounded-lg shadow-md p-6 text-center text-sm text-gray-400">
            Set at least one filter above to search production and building-level records.
        </div>
    @else
        {{-- Production Records --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Production Records ({{ $productions->total() }})</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Date</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Egg Size</th>
                            <th class="text-right py-2 px-2 font-semibold text-gray-700">Eggs Collected</th>
                            <th class="text-right py-2 px-2 font-semibold text-gray-700">Active Hens</th>
                            <th class="text-right py-2 px-2 font-semibold text-gray-700">Prod Rate</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Contributor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productions as $p)
                            <tr class="border-b last:border-0 hover:bg-gray-50">
                                <td class="py-2 px-2">{{ $p->date->format('Y-m-d') }}</td>
                                <td class="py-2 px-2">{{ $p->egg_size }}</td>
                                <td class="text-right py-2 px-2">{{ number_format($p->eggs_collected) }}</td>
                                <td class="text-right py-2 px-2">{{ number_format($p->active_hens) }}</td>
                                <td class="text-right py-2 px-2 font-semibold text-[#4CAF50]">{{ $p->production_rate }}%</td>
                                <td class="py-2 px-2 text-gray-600">{{ $p->user->name ?? 'System / Bulk Import' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No production records match this search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $productions->appends(request()->query())->links() }}</div>
        </div>

        {{-- Building-Level Entries --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Building-Level Entries ({{ $buildings->total() }})</h2>
            <p class="text-xs text-gray-400 mb-3">Per-building daily records — egg size isn't tracked at this level, so the size filter above doesn't narrow this table.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Date</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Building</th>
                            <th class="text-right py-2 px-2 font-semibold text-gray-700">Population</th>
                            <th class="text-right py-2 px-2 font-semibold text-gray-700">Eggs House</th>
                            <th class="text-right py-2 px-2 font-semibold text-gray-700">Prod Rate</th>
                            <th class="text-left py-2 px-2 font-semibold text-gray-700">Contributor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($buildings as $b)
                            <tr class="border-b last:border-0 hover:bg-gray-50">
                                <td class="py-2 px-2">{{ $b->date->format('Y-m-d') }}</td>
                                <td class="py-2 px-2">{{ $b->henBatch->building_no ?? $b->henBatch->building ?? '—' }}</td>
                                <td class="text-right py-2 px-2">{{ number_format($b->population) }}</td>
                                <td class="text-right py-2 px-2">{{ number_format($b->eggs_house) }}</td>
                                <td class="text-right py-2 px-2 font-semibold text-[#4CAF50]">{{ $b->prod_rate }}%</td>
                                <td class="py-2 px-2 text-gray-600">{{ $b->user->name ?? 'System / Bulk Import' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No building-level records match this search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $buildings->appends(request()->query())->links() }}</div>
        </div>
    @endif
</div>
</x-app-layout>
