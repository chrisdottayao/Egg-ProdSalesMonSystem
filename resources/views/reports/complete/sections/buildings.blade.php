{{-- Complete report section "buildings" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Per-Building Performance</h2>
<p class="muted">Farm-wide prod rate {{ $buildings['farmProdRate'] !== null ? $buildings['farmProdRate'] . '%' : '—' }}@if($buildings['farmBand']) ({{ $buildings['farmBand']['label'] }})@endif; revenue PHP {{ number_format($buildings['farmRevenue'], 2) }}; Feed &amp; Electricity Expenses PHP {{ number_format($buildings['farmExpenses'], 2) }}; net PHP {{ number_format($buildings['farmNet'], 2) }}.</p>
<table>
    <tr><th class="l">Building</th><th>Population</th><th>Prod rate</th><th class="l">Status</th><th>Open alerts</th><th class="l">As of</th></tr>
    @forelse($buildings['leaderboard'] as $row)
        <tr>
            <td class="l">{{ $row['building']->display_label }}</td>
            <td>{{ $row['population'] !== null ? number_format($row['population']) : '—' }}</td>
            <td>{{ $row['prod_rate'] !== null ? number_format($row['prod_rate'], 1) . '%' : '—' }}</td>
            <td class="l">{{ $row['band']['label'] ?? 'No data' }}</td>
            <td>{{ $row['open_alerts_count'] }}</td>
            <td class="l">{{ $row['as_of']?->format('Y-m-d') ?? '—' }}</td>
        </tr>
    @empty
        <tr><td class="l" colspan="6">No tracked buildings.</td></tr>
    @endforelse
</table>
