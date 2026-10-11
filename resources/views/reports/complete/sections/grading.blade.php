{{-- Complete report section "grading" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Egg Size (grading) Breakdown</h2>
<table>
    <tr><th class="l">Category</th><th>Pieces</th><th>Trays</th><th>Cases</th><th>Share</th></tr>
    @foreach($grading['rows'] as $g)
        <tr><td class="l">{{ $g['category'] }}</td><td>{{ number_format($g['pcs']) }}</td><td>{{ number_format($g['pcs'] / 30, 1) }}</td><td>{{ number_format($g['pcs'] / 360, 2) }}</td><td>{{ $g['share'] }}%</td></tr>
    @endforeach
    <tr class="total"><td class="l">Total</td><td>{{ number_format($grading['total']) }}</td><td>{{ number_format($grading['total'] / 30, 1) }}</td><td>{{ number_format($grading['total'] / 360, 2) }}</td><td>{{ $grading['total'] > 0 ? '100%' : '—' }}</td></tr>
</table>
