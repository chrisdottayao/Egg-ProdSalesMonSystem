{{-- Complete report section "batch" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Batch Traceability</h2>
<table>
    <tr><th class="l">Batch</th><th class="l">Collection date</th><th>Collected</th><th>Sold</th><th>Remaining</th><th>Spoilage</th><th>Sell-through</th><th class="l">Status</th></tr>
    @forelse($batches as $b)
        <tr><td class="l">HB-{{ $b->id }}</td><td class="l">{{ $b->date->format('Y-m-d') }}</td><td>{{ number_format($b->eggs_collected) }}</td><td>{{ number_format($b->quantity_sold) }}</td><td>{{ number_format($b->remaining_stock) }}</td><td>{{ $b->spoilage_count ?? 0 }}</td><td>{{ $b->sell_through_rate }}%</td><td class="l">{{ $b->batch_status }}</td></tr>
    @empty
        <tr><td class="l" colspan="8">No batches in this range.</td></tr>
    @endforelse
</table>
