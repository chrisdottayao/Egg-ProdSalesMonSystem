{{-- Complete report section "daily" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Daily Breakdown</h2>
<table>
    <tr><th class="l">Date</th><th>Eggs produced</th><th>Eggs sold</th><th>Mortality</th><th>Revenue (PHP)</th><th>Feed &amp; Electricity Expenses (PHP)</th><th>Prod rate</th><th>Remaining</th></tr>
    @forelse($dailyData as $d)
        <tr>
            <td class="l">{{ $d['iso_date'] }}</td><td>{{ number_format($d['eggs']) }}</td><td>{{ number_format($d['sold']) }}</td><td>{{ $d['mortality'] }}</td>
            <td>{{ number_format($d['revenue'], 2) }}</td><td>{{ number_format($d['expenses'], 2) }}</td><td>{{ $d['prod_rate'] }}%</td><td>{{ number_format($d['eggs'] - $d['sold'] - $d['spoiled']) }}</td>
        </tr>
    @empty
        <tr><td class="l" colspan="8">No approved production in this range.</td></tr>
    @endforelse
    <tr class="total">
        <td class="l">Total</td><td>{{ number_format($summary['total_eggs_produced']) }}</td><td>{{ number_format($summary['total_eggs_sold']) }}</td><td>{{ $summary['total_mortality'] }}</td>
        <td>{{ number_format($summary['total_revenue'], 2) }}</td><td>{{ number_format($summary['total_expenses'], 2) }}</td><td>{{ $summary['avg_production_rate'] }}%</td><td>{{ number_format($summary['remaining_eggs']) }}</td>
    </tr>
</table>
