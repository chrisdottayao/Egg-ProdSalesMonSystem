{{-- Complete report section "culls" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Culls &amp; Mortality</h2>
<p class="muted">Culled: {{ number_format($culls['total_culled']) }} birds. Mortality (approved daily entries): {{ number_format($culls['total_mortality']) }} birds.</p>
<table>
    <tr><th class="l">Date</th><th class="l">Building</th><th>Culled</th><th class="l">Reason</th><th>Heads sold</th><th class="l">Buyer</th></tr>
    @forelse($culls['rows'] as $c)
        <tr><td class="l">{{ $c->date->format('Y-m-d') }}</td><td class="l">{{ $c->henBatch?->display_label ?? '—' }}</td><td>{{ number_format($c->quantity_culled) }}</td><td class="l">{{ $c->reason ?? '—' }}</td><td>{{ $c->heads_sold ?? '—' }}</td><td class="l">{{ $c->buyer ?? '—' }}</td></tr>
    @empty
        <tr><td class="l" colspan="6">No culls recorded in this range.</td></tr>
    @endforelse
</table>
<table>
    <tr><th class="l">Building</th><th>Mortality</th></tr>
    @forelse($culls['mortality_by_building'] as $m)
        <tr><td class="l">{{ $m['building'] }}</td><td>{{ number_format($m['mortality']) }}</td></tr>
    @empty
        <tr><td class="l" colspan="2">No building mortality recorded in this range.</td></tr>
    @endforelse
</table>
