{{-- Complete report section "charts" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Production &amp; Revenue charts</h2>
<table class="charts"><tr>
    <td>@if(!empty($charts['production']))<img class="chart" src="{{ $charts['production'] }}" alt="Egg Production Trend" />@endif</td>
    <td>@if(!empty($charts['revenue']))<img class="chart" src="{{ $charts['revenue'] }}" alt="Sales Revenue Trend" />@endif</td>
</tr></table>
@if(empty($charts['production']) && empty($charts['revenue']))<p class="muted">No production or sales data to chart in this range.</p>@endif
