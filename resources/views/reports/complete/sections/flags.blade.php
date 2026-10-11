{{-- Complete report section "flags" (Phase 3 item 11); used by the PDF and the Excel sheet. --}}
<h2>Flagged records (unresolved)</h2>
<p class="muted">{{ $flags->count() }} unresolved flag(s) raised in this range. The full system Audit Log is a separate download.</p>
<table>
    <tr><th class="l">When (Asia/Manila)</th><th class="l">User</th><th class="l">Rule</th><th class="l">Record</th><th class="l">Details</th></tr>
    @forelse($flags as $f)
        <tr><td class="l">{{ $f->created_at->copy()->setTimezone(\App\Support\ManilaDate::TZ)->format('Y-m-d H:i') }}</td><td class="l">{{ $f->user?->name ?? '—' }}</td><td class="l">{{ \Illuminate\Support\Str::headline($f->inconsistency_rule ?? '') }}</td><td class="l">{{ $f->module_label }} #{{ $f->model_id }}</td><td class="l">{{ \App\Support\AuditLogFilter::detailsText($f) }}</td></tr>
    @empty
        <tr><td class="l" colspan="5">No unresolved flags in this range.</td></tr>
    @endforelse
</table>
