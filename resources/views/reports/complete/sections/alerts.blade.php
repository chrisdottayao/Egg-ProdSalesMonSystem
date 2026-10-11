{{-- Complete report section "alerts" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Anomaly Alerts in the period</h2>
<table>
    <tr><th class="l">Date</th><th class="l">Type</th><th class="l">Severity</th><th class="l">Expected</th><th class="l">Actual</th><th>Deviation</th><th class="l">Status</th><th class="l">Description</th></tr>
    @forelse($alerts as $a)
        <tr><td class="l">{{ \Illuminate\Support\Carbon::parse($a->alert_date)->format('Y-m-d') }}</td><td class="l">{{ $a->type }}</td><td class="l">{{ $a->severity }}</td><td class="l">{{ $a->expected_value }}</td><td class="l">{{ $a->actual_value }}</td><td>{{ $a->deviation_pct !== null ? number_format($a->deviation_pct, 1) . '%' : '—' }}</td><td class="l">{{ $a->status }}</td><td class="l">{{ $a->description }}</td></tr>
    @empty
        <tr><td class="l" colspan="8">No anomaly alerts in this range.</td></tr>
    @endforelse
</table>
