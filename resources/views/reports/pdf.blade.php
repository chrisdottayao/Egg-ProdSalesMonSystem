<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 20px; }
    h1   { font-size: 16px; margin-bottom: 2px; }
    .sub { font-size: 10px; color: #666; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th    { background: #4CAF50; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
    td    { padding: 5px 8px; border-bottom: 1px solid #e5e5e5; }
    tr:nth-child(even) td { background: #f9f9f9; }
    tfoot td { font-weight: bold; border-top: 2px solid #4CAF50; background: #f0f8f0; }
    .green { color: #2e7d32; }
    .section { margin-bottom: 6px; font-weight: bold; font-size: 12px; }
    .summary { display: table; width: 100%; margin-bottom: 16px; }
    .sum-cell { display: table-cell; width: 16.6%; padding: 8px; background: #f4f4f4; border-right: 4px solid #fff; }
    .sum-label { font-size: 9px; color: #777; }
    .sum-val   { font-size: 14px; font-weight: bold; }
</style>
</head>
<body>

<h1>SPC Farm Magalang — Production & Sales Report</h1>
<p class="sub">Period: {{ $startDate }} to {{ $endDate }} &nbsp;|&nbsp; Generated: {{ now()->format('M d, Y h:i A') }}</p>

<p class="section">Summary</p>
<table>
    <tr>
        <th>Total Eggs Produced</th>
        <th>Total Eggs Sold</th>
        <th>Total Revenue</th>
        <th>Avg Production Rate</th>
        <th>Avg Sales Rate</th>
        <th>Remaining Eggs</th>
    </tr>
    <tr>
        <td>{{ number_format($summary['total_eggs_produced']) }}</td>
        <td>{{ number_format($summary['total_eggs_sold']) }}</td>
        <td class="green">&#8369;{{ number_format($summary['total_revenue'], 2) }}</td>
        <td>{{ $summary['avg_production_rate'] }}%</td>
        <td>{{ $summary['avg_sales_rate'] }}%</td>
        <td>{{ number_format($summary['remaining_eggs']) }}</td>
    </tr>
</table>

<p class="section">Daily Breakdown</p>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Eggs Produced</th>
            <th>Eggs Sold</th>
            <th>Revenue</th>
            <th>Prod Rate</th>
            <th>Remaining</th>
        </tr>
    </thead>
    <tbody>
        @forelse($dailyData as $row)
        <tr>
            <td>{{ $row['date'] }}</td>
            <td>{{ number_format($row['eggs']) }}</td>
            <td>{{ number_format($row['sold']) }}</td>
            <td class="green">&#8369;{{ number_format($row['revenue'], 2) }}</td>
            <td>{{ $row['prod_rate'] }}%</td>
            <td>{{ number_format($row['eggs'] - $row['sold']) }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#999;">No data for selected period.</td></tr>
        @endforelse
    </tbody>
    @if($dailyData->isNotEmpty())
    <tfoot>
        <tr>
            <td>Total</td>
            <td>{{ number_format($summary['total_eggs_produced']) }}</td>
            <td>{{ number_format($summary['total_eggs_sold']) }}</td>
            <td class="green">&#8369;{{ number_format($summary['total_revenue'], 2) }}</td>
            <td>{{ $summary['avg_production_rate'] }}%</td>
            <td>{{ number_format($summary['remaining_eggs']) }}</td>
        </tr>
    </tfoot>
    @endif
</table>

<p style="font-size:9px;color:#aaa;margin-top:30px;">
    Data-Driven Egg Production and Sales Monitoring System &mdash; SPC Farm Magalang, Sta. Maria, Magalang, Pampanga
</p>

</body>
</html>
