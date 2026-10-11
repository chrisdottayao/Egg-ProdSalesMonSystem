<x-app-layout>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Pending Approvals</h1>
        <p class="text-sm text-gray-500 mt-1">Everything staff record: daily building entries, egg grading, farm production, sales, utility expenses and culls. Nothing here counts in totals, reports, alerts or forecasts until approved. Rejecting needs a note; the staff member sees it and can correct and resubmit.</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 px-4 py-3 rounded-lg text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @php
        $manila = fn ($t) => $t?->copy()->setTimezone(\App\Support\ManilaDate::TZ)->format('M d, Y g:i A') ?? '—';
        $filtered = $date || $userId;
    @endphp

    {{-- Filter by date / person; "Approve all" acts on exactly what is shown. --}}
    <div class="bg-white rounded-lg shadow-md p-4">
        <form method="GET" action="{{ route('approvals.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="filter_date" class="block text-xs font-semibold text-gray-600 mb-1">Date</label>
                <input type="date" id="filter_date" name="date" value="{{ $date }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label for="filter_user" class="block text-xs font-semibold text-gray-600 mb-1">Entered by</label>
                <select id="filter_user" name="user_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Everyone</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected($userId === $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold hover:bg-gray-700">Show</button>
            @if($filtered)
                <a href="{{ route('approvals.index') }}" class="text-sm text-gray-500 hover:underline py-2">Clear</a>
            @endif

            <div class="ml-auto">
                @if($filtered && $items->isNotEmpty())
                    <button type="submit" form="approve-all-form"
                            onclick="return confirm('Approve all {{ $items->count() }} entries shown?')"
                            class="px-4 py-2 rounded-lg bg-[#4CAF50] text-white text-sm font-semibold hover:bg-green-600">Approve all {{ $items->count() }} shown</button>
                @else
                    <span class="text-xs text-gray-400">Pick a date or a person to approve their entries all at once.</span>
                @endif
            </div>
        </form>
        <form id="approve-all-form" method="POST" action="{{ route('approvals.approve-all') }}" class="hidden">
            @csrf @method('PATCH')
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="user_id" value="{{ $userId }}">
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Waiting for review ({{ $items->count() }})</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 font-semibold text-gray-700">Type</th>
                        <th class="text-left py-2 font-semibold text-gray-700">Date</th>
                        <th class="text-left py-2 font-semibold text-gray-700">Building</th>
                        <th class="text-left py-2 font-semibold text-gray-700">Values</th>
                        <th class="text-left py-2 pl-4 font-semibold text-gray-700">Entered by</th>
                        <th class="text-left py-2 font-semibold text-gray-700">Entered at</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        @php $record = $item['record']; @endphp
                        <tr class="border-b last:border-0 align-top" data-approval-type="{{ $item['type'] }}">
                            <td class="py-3 font-medium text-gray-800">{{ $item['label'] }}</td>
                            <td class="py-3">{{ $record->date->format('Y-m-d') }}</td>
                            <td class="py-3">{{ \App\Support\ApprovalQueue::building($record) }}</td>
                            <td class="py-3 text-gray-700">{{ \App\Support\ApprovalQueue::summary($record) }}</td>
                            <td class="py-3 pl-4">{{ \App\Support\ApprovalQueue::entrant($record)?->name ?? '—' }}</td>
                            <td class="py-3 text-gray-600">{{ $manila($record->updated_at) }}</td>
                            <td class="py-3"><x-approval-actions :type="$item['type']" :record="$record" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-gray-400">Nothing waiting{{ $filtered ? ' for this filter' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</x-app-layout>
