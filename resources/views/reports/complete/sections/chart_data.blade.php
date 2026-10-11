{{-- Complete report section "chart_data" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Production &amp; Revenue chart data</h2>
<table>
    <tr><th class="l">Date</th><th>Eggs collected</th><th>Revenue (PHP)</th></tr>
    @foreach($dailyData as $d)
        <tr><td class="l">{{ $d['iso_date'] }}</td><td>{{ $d['eggs'] }}</td><td>{{ number_format($d['revenue'], 2, '.', '') }}</td></tr>
    @endforeach
</table>
