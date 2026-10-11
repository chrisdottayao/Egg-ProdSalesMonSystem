<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 40px 28px 50px 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
    h2 { font-size: 12px; margin: 16px 0 4px; color: #1b5e20; border-bottom: 1px solid #c8e6c9; padding-bottom: 2px; page-break-after: avoid; }
    table { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
    th { background: #2e7d32; color: #fff; text-align: right; padding: 4px 5px; font-size: 8px; }
    td { padding: 3px 5px; border-bottom: 1px solid #e5e5e5; text-align: right; }
    th.l, td.l { text-align: left; }
    tr.total td { font-weight: bold; background: #f1f8e9; border-top: 2px solid #2e7d32; }
    .muted { color: #555; font-size: 9px; margin: 2px 0 4px; }
    table.charts td { width: 50%; border: 0; padding: 0 4px; vertical-align: top; }
    img.chart { width: 100%; margin: 4px 0 8px; }
    .section { page-break-inside: auto; }
    footer { position: fixed; bottom: -32px; left: 0; right: 0; font-size: 8px; color: #777; border-top: 1px solid #ddd; padding-top: 3px; }
</style>
</head>
<body>
<footer>
    <span>Computer-generated from the system — {{ config('dashboard.farm_name') }}</span>
</footer>

@include('reports.partials.export-header', [
    'reportTitle'  => 'Farm Production & Sales Report',
    'rangeText'    => $report->rangeText(),
    'filtersText'  => $report->filtersText(),
    'sectionsText' => $report->sectionsText(),
])

@foreach(array_keys(\App\Support\FarmReport::SECTIONS) as $section)
    @if($report->includes($section))
        <div class="section">
            @if($section === 'sales')<h2>Sales &amp; Revenue</h2>@endif
            @include('reports.complete.sections.' . $section)
        </div>
    @endif
@endforeach

@include('reports.complete.sections.definitions')
{{-- "Page N of M" is stamped by ReportsController after layout (CSS counter(pages) isn't supported by dompdf). --}}
</body>
</html>
