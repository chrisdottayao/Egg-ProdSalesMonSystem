{{-- Complete report section "forecast" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Forecast &amp; Forecast Evaluation</h2>
@if($forecast['current']['active'] ?? false)
    <p class="muted">Model MAPE {{ number_format((float) $forecast['current']['mape'], 2) }}% (trained on {{ $forecast['current']['trained_on'] }} days). 7-day forecast total: {{ number_format(collect($forecast['current']['forecast_7day'])->sum('predicted')) }} eggs.</p>
    <table>
        <tr><th class="l">Day</th><th>Forecast eggs</th></tr>
        @foreach($forecast['current']['forecast_7day'] as $f)<tr><td class="l">{{ $f['day'] }}</td><td>{{ number_format($f['predicted']) }}</td></tr>@endforeach
    </table>
@else
    <p class="muted">{{ $forecast['current']['message'] ?? 'Forecast not active.' }}</p>
@endif
<table>
    <tr><th class="l">Evaluated (Asia/Manila)</th><th>Records used</th><th>MAPE</th><th>7-day forecast total</th><th>30-day forecast total</th></tr>
    @forelse($forecast['evaluations'] as $e)
        <tr><td class="l">{{ $e->evaluated_at->copy()->setTimezone(\App\Support\ManilaDate::TZ)->format('Y-m-d H:i') }}</td><td>{{ number_format($e->trained_on) }}</td><td>{{ number_format((float) $e->mape, 2) }}%</td><td>{{ number_format((float) $e->forecast_7day_total) }}</td><td>{{ number_format((float) $e->forecast_30day_total) }}</td></tr>
    @empty
        <tr><td class="l" colspan="5">No evaluation records yet.</td></tr>
    @endforelse
</table>
<p class="muted"><strong>AI insight (today):</strong> {{ $forecast['ai_insight'] ?: 'None generated today.' }}</p>
