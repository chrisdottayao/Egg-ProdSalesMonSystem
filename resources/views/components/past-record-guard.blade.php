@props(['date'])

{{-- S16: staff can only change today's records; admin/manager can correct older ones. --}}
@if(auth()->user()->canManageRecords() || ! \App\Support\ManilaDate::isPast($date))
    {{ $slot }}
@else
    <span class="text-xs text-gray-400" title="Only an admin or manager can change records from previous days.">Locked</span>
@endif
