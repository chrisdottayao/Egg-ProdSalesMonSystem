@props(['status', 'note' => null])

{{-- Phase 3 item 1: Approved / Pending / Rejected (with the reviewer's note). --}}
@php
    $styles = [
        'approved' => 'bg-green-100 text-green-700',
        'pending'  => 'bg-yellow-100 text-yellow-800',
        'rejected' => 'bg-red-100 text-red-700',
    ];
@endphp
<span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full {{ $styles[$status] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($status ?? 'approved') }}</span>
@if($status === 'rejected' && $note)
    <span class="block text-xs text-red-700 mt-1 max-w-xs">Note: {{ $note }}</span>
@endif
