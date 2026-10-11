{{-- Complete report section "summary" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Summary</h2>
<table>
    <tr><th class="l">Measure</th><th>Value</th></tr>
    <tr><td class="l">Total eggs produced</td><td>{{ number_format($summary['total_eggs_produced']) }}</td></tr>
    <tr><td class="l">Total eggs sold</td><td>{{ number_format($summary['total_eggs_sold']) }}</td></tr>
    <tr><td class="l">Remaining eggs</td><td>{{ number_format($summary['remaining_eggs']) }}</td></tr>
    <tr><td class="l">Total mortality</td><td>{{ number_format($summary['total_mortality']) }}</td></tr>
    <tr><td class="l">Total revenue (PHP)</td><td>{{ number_format($summary['total_revenue'], 2) }}</td></tr>
    <tr><td class="l">Total Feed &amp; Electricity Expenses (PHP)</td><td>{{ number_format($summary['total_expenses'], 2) }}</td></tr>
    <tr><td class="l">Net income (PHP)</td><td>{{ number_format($summary['net_income'], 2) }}</td></tr>
    <tr><td class="l">Average production rate</td><td>{{ $summary['avg_production_rate'] }}%</td></tr>
    <tr><td class="l">Average sales rate (sell-through)</td><td>{{ $summary['avg_sales_rate'] }}%</td></tr>
</table>
