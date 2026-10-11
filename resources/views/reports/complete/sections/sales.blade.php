{{-- Complete report section "sales" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
@include('reports.partials.sales-table', ['sales' => $salesData, 'sizes' => $salesData['sizes']])
