@props(['record'])

{{-- Phase 3B item 6: Entered by / Status / Approved by / Approved at (Manila). --}}
<td class="py-3 pl-3 text-sm text-gray-700">{{ $record->entrant?->name ?? '—' }}</td>
<td class="py-3 pl-3 text-sm"><x-approval-status :status="$record->approval_status" :note="$record->review_note" /></td>
<td class="py-3 pl-3 text-sm text-gray-700">{{ $record->isApproved() ? ($record->approver?->name ?? '—') : '—' }}</td>
<td class="py-3 pl-3 text-sm text-gray-600 whitespace-nowrap">{{ $record->isApproved() && $record->approved_at ? $record->approved_at->copy()->setTimezone(\App\Support\ManilaDate::TZ)->format('M d, Y g:i A') : '—' }}</td>
