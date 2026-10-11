{{-- Complete report section "definitions" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Basis &amp; definitions</h2>
<table>
    @foreach($definitions as $term => $text)<tr><td class="l"><strong>{{ $term }}</strong></td><td class="l">{{ $text }}</td></tr>@endforeach
</table>
