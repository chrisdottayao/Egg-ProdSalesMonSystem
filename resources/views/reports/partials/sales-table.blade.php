{{-- Sales & Revenue table (Phase 3 item 7) — used by the PDF/Excel export
     and the complete report. Plain HTML so dompdf and Excel can both read it. --}}
<h2>Totals by period</h2>
<table>
    <thead>
        <tr>
            <th class="l">Period</th>
            <th>Eggs sold (pcs)</th>
            <th>Trays</th>
            <th>Cases</th>
            <th>Revenue (PHP)</th>
            <th>Avg price / pc (PHP)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($sales['rows'] as $r)
            <tr>
                <td class="l">{{ $r['label'] }}</td>
                <td>{{ number_format($r['pcs']) }}</td>
                <td>{{ number_format($r['trays'], 1) }}</td>
                <td>{{ number_format($r['cases'], 2) }}</td>
                <td>{{ number_format($r['revenue'], 2) }}</td>
                <td>{{ $r['avg_price'] !== null ? number_format($r['avg_price'], 2) : '—' }}</td>
            </tr>
        @empty
            <tr><td class="l" colspan="6">No approved sales in this range.</td></tr>
        @endforelse
        <tr class="total">
            <td class="l">Total</td>
            <td>{{ number_format($sales['totals']['pcs']) }}</td>
            <td>{{ number_format($sales['totals']['trays'], 1) }}</td>
            <td>{{ number_format($sales['totals']['cases'], 2) }}</td>
            <td>{{ number_format($sales['totals']['revenue'], 2) }}</td>
            <td>{{ $sales['totals']['avg_price'] !== null ? number_format($sales['totals']['avg_price'], 2) : '—' }}</td>
        </tr>
    </tbody>
</table>

<h2>Eggs sold by size (pcs)</h2>
<table>
    <thead>
        <tr>
            <th class="l">Period</th>
            @foreach($sizes as $size)<th>{{ $size }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($sales['rows'] as $r)
            <tr>
                <td class="l">{{ $r['label'] }}</td>
                @foreach($sizes as $size)<td>{{ number_format($r['by_size'][$size]) }}</td>@endforeach
            </tr>
        @endforeach
        <tr class="total">
            <td class="l">Total</td>
            @foreach($sizes as $size)<td>{{ number_format($sales['totals']['by_size'][$size]) }}</td>@endforeach
        </tr>
    </tbody>
</table>
<p style="font-size:8px;color:#777;">1 tray = 30 pcs, 1 case = 360 pcs. Average price per piece = revenue ÷ pieces sold.</p>
