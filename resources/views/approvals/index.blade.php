<x-app-layout>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Pending Approvals</h1>
        <p class="text-sm text-gray-500 mt-1">Entries recorded by staff. They do not count in totals, reports, alerts or forecasts until approved. Rejecting needs a note; the staff member sees it and can correct and resubmit.</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 px-4 py-3 rounded-lg text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @php
        $manila = fn ($t) => $t?->copy()->setTimezone(\App\Support\ManilaDate::TZ)->format('M d, Y g:i A') ?? '—';
    @endphp

    {{-- Building daily entries --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Daily Building Entries ({{ $buildingRows->count() }})</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 font-semibold text-gray-700">Date</th>
                        <th class="text-left py-2 font-semibold text-gray-700">Building</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Population</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Mortality</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Eggs House</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Prod Rate</th>
                        <th class="text-left py-2 pl-4 font-semibold text-gray-700">Entered by</th>
                        <th class="text-left py-2 font-semibold text-gray-700">Entered at</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($buildingRows as $row)
                        <tr class="border-b last:border-0 align-top">
                            <td class="py-3">{{ $row->date->format('Y-m-d') }}</td>
                            <td class="py-3">{{ $row->henBatch?->display_label ?? '—' }}</td>
                            <td class="py-3 text-right">{{ number_format($row->population) }}</td>
                            <td class="py-3 text-right">{{ $row->mortality }}</td>
                            <td class="py-3 text-right">{{ number_format($row->eggs_house) }}</td>
                            <td class="py-3 text-right">{{ number_format($row->prod_rate, 1) }}%</td>
                            <td class="py-3 pl-4">{{ $row->user?->name ?? '—' }}</td>
                            <td class="py-3 text-gray-600">{{ $manila($row->updated_at) }}</td>
                            <td class="py-3"><x-approval-actions type="building-daily" :record="$row" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-6 text-center text-gray-400">Nothing waiting.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Sales --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Sales ({{ $sales->count() }})</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 font-semibold text-gray-700">Date</th>
                        <th class="text-left py-2 font-semibold text-gray-700">Size</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Qty</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Price/Unit</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Total</th>
                        <th class="text-left py-2 pl-4 font-semibold text-gray-700">Entered by</th>
                        <th class="text-left py-2 font-semibold text-gray-700">Entered at</th>
                        <th class="text-right py-2 font-semibold text-gray-700">Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr class="border-b last:border-0 align-top">
                            <td class="py-3">{{ $sale->date->format('Y-m-d') }}</td>
                            <td class="py-3">{{ $sale->egg_size }}</td>
                            <td class="py-3 text-right">{{ number_format($sale->quantity) }}</td>
                            <td class="py-3 text-right">₱{{ number_format($sale->price_per_unit, 2) }}</td>
                            <td class="py-3 text-right font-semibold">₱{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="py-3 pl-4">{{ $sale->enteredBy?->name ?? '—' }}</td>
                            <td class="py-3 text-gray-600">{{ $manila($sale->updated_at) }}</td>
                            <td class="py-3"><x-approval-actions type="sales" :record="$sale" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-gray-400">Nothing waiting.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</x-app-layout>
