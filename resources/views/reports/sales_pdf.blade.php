<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; margin: 18px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th    { background: #2e7d32; color: #fff; text-align: right; padding: 4px 5px; font-size: 8px; }
    th.l, td.l { text-align: left; }
    td    { padding: 3px 5px; border-bottom: 1px solid #e5e5e5; text-align: right; }
    tr.total td { font-weight: bold; background: #f1f8e9; border-top: 2px solid #2e7d32; }
    h2 { font-size: 12px; margin: 14px 0 2px; }
</style>
</head>
<body>
@include('reports.partials.export-header', [
    'reportTitle' => 'Sales & Revenue Report',
    'rangeText'   => $salesReport->describe(),
])

@include('reports.partials.sales-table', ['sales' => $salesData, 'sizes' => $salesData['sizes']])
</body>
</html>
