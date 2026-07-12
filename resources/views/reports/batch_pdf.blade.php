<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 20px; }
    h1   { font-size: 16px; margin-bottom: 2px; }
    .sub { font-size: 10px; color: #666; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th    { background: #2196F3; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
    td    { padding: 5px 8px; border-bottom: 1px solid #e5e5e5; }
    tr:nth-child(even) td { background: #f9f9f9; }
    .green { color: #2e7d32; font-weight: bold; }
    .status-badge { padding: 2px 4px; border-radius: 3px; font-size: 9px; font-weight: bold; }
    .status-active { background: #fff59d; color: #f57f17; }
    .status-partial { background: #90caf9; color: #0d47a1; }
    .status-full { background: #a5d6a7; color: #1b5e20; }
    .status-spoiled { background: #ef9a9a; color: #b71c1c; }
    .section { margin-bottom: 6px; font-weight: bold; font-size: 12px; }
</style>
</head>
<body>

<h1>SPC Farm Magalang — Batch Traceability Report</h1>
<p class="sub">Period: {{ $startDate }} to {{ $endDate }} &nbsp;|&nbsp; Generated: {{ now()->format('M d, Y h:i A') }}</p>

<p class="section">Batches Traced (Collection to Sale/Disposal)</p>
<table>
    <thead>
        <tr>
            <th>Batch ID</th>
            <th>Collection Date</th>
            <th>Egg Size</th>
            <th style="text-align:right;">Qty Collected</th>
            <th style="text-align:right;">Qty Sold</th>
            <th style="text-align:right;">Remaining</th>
            <th style="text-align:right;">Spoilage</th>
            <th style="text-align:right;">Sell-Through</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($batches as $b)
        @php
            $statusClass = match($b->batch_status) {
                'Fully Sold'     => 'status-full',
                'Partially Sold' => 'status-partial',
                'Spoiled'        => 'status-spoiled',
                default          => 'status-active',
            };
        @endphp
        <tr>
            <td>HB-{{ $b->id }}</td>
            <td>{{ $b->date->format('Y-m-d') }}</td>
            <td>{{ $b->egg_size }}</td>
            <td style="text-align:right;">{{ number_format($b->eggs_collected) }}</td>
            <td style="text-align:right;">{{ number_format($b->quantity_sold) }}</td>
            <td style="text-align:right;">{{ number_format($b->remaining_stock) }}</td>
            <td style="text-align:right;">{{ number_format($b->spoilage_count) }}</td>
            <td style="text-align:right;" class="green">{{ $b->sell_through_rate }}%</td>
            <td>
                <span class="status-badge {{ $statusClass }}">{{ $b->batch_status }}</span>
            </td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;color:#999;">No batches for selected period.</td></tr>
        @endforelse
    </tbody>
</table>

<p style="font-size:9px;color:#aaa;margin-top:30px;">
    Data-Driven Egg Production and Sales Monitoring System &mdash; SPC Farm Magalang, Sta. Maria, Magalang, Pampanga
</p>

</body>
</html>
