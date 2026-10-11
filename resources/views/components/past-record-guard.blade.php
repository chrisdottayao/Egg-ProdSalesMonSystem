@props(['date' => null, 'record' => null])

{{-- S16: staff can only change today's records; admin/manager can correct older ones.
     Phase 3B item 4: given the record, staff also can't change an approved one or
     someone else's (HasApproval::changeableBy()). --}}
@php
    $user = auth()->user();
    $allowed = $record
        ? $record->changeableBy($user)
        : ($user->canManageRecords() || ! \App\Support\ManilaDate::isPast($date));
    $why = match (true) {
        $record?->isApproved() => 'Approved records can only be changed by an admin or manager.',
        $record && ! $record->isEnteredBy($user) => 'Only the person who entered this record (or an admin or manager) can change it.',
        default => 'Only an admin or manager can change records from previous days.',
    };
@endphp
@if($allowed)
    {{ $slot }}
@else
    <span class="text-xs text-gray-400" title="{{ $why }}">Locked</span>
@endif
