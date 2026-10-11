@props(['status', 'note' => null])

{{-- Phase 3 item 1 / 3B item 6: Approved / Waiting for approval / Rejected (with the reviewer's note). --}}
@php
    $styles = [
        'approved' => 'bg-green-100 text-green-700',
        'pending'  => 'bg-yellow-100 text-yellow-800',
        'rejected' => 'bg-red-100 text-red-700',
    ];
    $labels = ['approved' => 'Approved', 'pending' => 'Waiting for approval', 'rejected' => 'Rejected'];
@endphp
<span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap {{ $styles[$status] ?? 'bg-gray-100 text-gray-600' }}" data-approval-status="{{ $status ?? 'approved' }}">{{ $labels[$status] ?? 'Approved' }}</span>
@if($status === 'rejected' && $note)
    <span class="block text-xs text-red-700 mt-1 max-w-xs">Note: {{ $note }}</span>
@endif
