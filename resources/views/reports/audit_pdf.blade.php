<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 20px; }
    h1   { font-size: 16px; margin-bottom: 2px; }
    .sub { font-size: 10px; color: #666; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th    { background: #e53935; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
    td    { padding: 5px 8px; border-bottom: 1px solid #e5e5e5; }
    tr:nth-child(even) td { background: #f9f9f9; }
    .status-badge { padding: 2px 4px; border-radius: 3px; font-size: 9px; font-weight: bold; }
    .status-flagged { background: #ffebee; color: #c62828; }
    .status-clean { background: #e8f5e9; color: #2e7d32; }
    .section { margin-bottom: 6px; font-weight: bold; font-size: 12px; }
</style>
</head>
<body>

<h1>SPC Farm Magalang — Audit Inconsistency Report</h1>
<p class="sub">Period: {{ $startDate }} to {{ $endDate }} &nbsp;|&nbsp; Generated: {{ now()->format('M d, Y h:i A') }}</p>

<p class="section">Flagged Data Integrity Violations &amp; Operations Logs</p>
<table>
    <thead>
        <tr>
            <th>Entry Date</th>
            <th>User</th>
            <th>Action</th>
            <th>Model</th>
            <th>ID</th>
            <th>Rule Violated / Log Details</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse($auditLogs as $a)
        <tr>
            <td>{{ $a->created_at->format('Y-m-d H:i') }}</td>
            <td>{{ $a->user ? $a->user->name : 'System' }}</td>
            <td style="font-weight:bold;">{{ strtoupper($a->action) }}</td>
            <td>{{ $a->model_type }}</td>
            <td>{{ $a->model_id }}</td>
            <td>
                @if($a->inconsistency_flagged)
                    <strong style="color:#d32f2f;">[VIOLATION: {{ $a->inconsistency_rule }}]</strong>
                @endif
                {{ json_encode($a->details) }}
            </td>
            <td>
                <span class="status-badge {{ $a->inconsistency_flagged ? 'status-flagged' : 'status-clean' }}">
                    {{ $a->inconsistency_flagged ? 'Flagged' : 'Clean' }}
                </span>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#999;">No audit logs found for the selected period.</td></tr>
        @endforelse
    </tbody>
</table>

<p style="font-size:9px;color:#aaa;margin-top:30px;">
    Data-Driven Egg Production and Sales Monitoring System &mdash; SPC Farm Magalang, Sta. Maria, Magalang, Pampanga
</p>

</body>
</html>
