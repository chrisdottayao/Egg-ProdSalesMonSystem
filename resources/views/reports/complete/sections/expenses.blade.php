{{-- Complete report section "expenses" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Feed &amp; Electricity Expenses</h2>
<table>
    <tr><th class="l">Category</th><th>Amount (PHP)</th></tr>
    <tr><td class="l">Feed</td><td>{{ number_format($expenses['feed'], 2) }}</td></tr>
    <tr><td class="l">Electricity</td><td>{{ number_format($expenses['electricity'], 2) }}</td></tr>
    @if($expenses['older'] > 0)<tr><td class="l">Older categories (no longer recorded)</td><td>{{ number_format($expenses['older'], 2) }}</td></tr>@endif
    <tr class="total"><td class="l">Total</td><td>{{ number_format($expenses['total'], 2) }}</td></tr>
</table>
<table>
    <tr><th class="l">Date</th><th class="l">Category</th><th class="l">Description</th><th class="l">Building</th><th>Amount (PHP)</th><th class="l">Estimated</th></tr>
    @forelse($expenses['rows'] as $e)
        <tr><td class="l">{{ $e->date->format('Y-m-d') }}</td><td class="l">{{ $e->category_label }}</td><td class="l">{{ $e->description }}</td><td class="l">{{ $e->building?->display_label ?? 'Farm-wide' }}</td><td>{{ number_format($e->amount, 2) }}</td><td class="l">{{ $e->is_estimated ? 'Yes' : 'No' }}</td></tr>
    @empty
        <tr><td class="l" colspan="6">No expenses recorded in this range.</td></tr>
    @endforelse
</table>
