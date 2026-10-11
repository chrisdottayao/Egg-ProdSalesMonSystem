{{-- Phase 3 item 5: the thresholds currently in force, read-only, for every
     role (admins edit them in Settings > Thresholds & Alert Rules). --}}
@php
    $t = \App\Support\Thresholds::all();
    $fmt = fn ($v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
@endphp
<details class="bg-white rounded-lg shadow-md p-4 text-sm text-gray-700">
    <summary class="cursor-pointer font-semibold text-gray-800">Thresholds in use</summary>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
        <div>
            <p class="font-semibold text-gray-800 mb-1">Production rate bands</p>
            <ul class="space-y-1">
                <li><span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>Healthy: &ge; {{ $fmt($t['prod_rate_healthy_min']) }}%</li>
                <li><span class="inline-block w-2 h-2 rounded-full bg-amber-500 mr-1"></span>Watch: between {{ $fmt($t['prod_rate_cull_max']) }}% and {{ $fmt($t['prod_rate_healthy_min']) }}%</li>
                <li><span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span>Cull-consideration: &le; {{ $fmt($t['prod_rate_cull_max']) }}%</li>
            </ul>
            <p class="text-xs text-gray-500 mt-1">Healthy: {{ \App\Support\Thresholds::basis('prod_rate_healthy_min') }} Cull: {{ \App\Support\Thresholds::basis('prod_rate_cull_max') }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-800 mb-1">Weekly mortality bands (% of flock in 7 days)</p>
            <ul class="space-y-1">
                <li>Elevated: above {{ $fmt($t['mortality_band_elevated']) }}%</li>
                <li>Abnormal: above {{ $fmt($t['mortality_band_abnormal']) }}%</li>
                <li>Highly abnormal: above {{ $fmt($t['mortality_band_high']) }}%</li>
                <li>Severe: above {{ $fmt($t['mortality_band_severe']) }}%</li>
                <li>Extreme: {{ $fmt($t['mortality_band_extreme']) }}% or more</li>
            </ul>
        </div>
        <div>
            <p class="font-semibold text-gray-800 mb-1">Anomaly alerts (vs. the previous 7 days)</p>
            <ul class="space-y-1">
                <li>Production Drop: more than {{ $fmt($t['anomaly_prod_drop_pct']) }}% below average</li>
                <li>Production Spike: more than {{ $fmt($t['anomaly_prod_spike_pct']) }}% above average</li>
                <li>Mortality Spike: more than {{ $fmt($t['anomaly_mortality_spike_pct']) }}% above average and at least {{ $fmt($t['anomaly_mortality_spike_min']) }} deaths</li>
            </ul>
        </div>
    </div>
</details>
