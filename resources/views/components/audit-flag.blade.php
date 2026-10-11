@props(['log'])

{{-- Phase 3 item 3: a flag badge; managers/admins can resolve it with a note. --}}
@php
    $label = $log->inconsistency_rule === \App\Support\SoldVsProduced::RULE
        ? 'Sold > produced'
        : \Illuminate\Support\Str::headline($log->inconsistency_rule ?? 'Flagged');
@endphp
<div x-data="{ open: false }" class="space-y-1">
    <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $log->resolved ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-700' }}"
          title="{{ $log->resolved ? 'Resolved' . ($log->resolver ? ' by ' . $log->resolver->name : '') . ($log->resolution_note ? ': ' . $log->resolution_note : '') : 'Unresolved' }}">
        {{ $label }}{{ $log->resolved ? ' · resolved' : '' }}
    </span>
    @if($log->resolved && $log->resolution_note)
        <span class="block text-xs text-gray-500 max-w-xs">{{ $log->resolution_note }}</span>
    @endif
    @if(! $log->resolved && auth()->user()?->canManageRecords())
        <button type="button" @click="open = !open" class="block text-xs text-blue-600 hover:underline">Resolve…</button>
        <form method="POST" action="{{ route('audit-flags.resolve', $log) }}" x-show="open" x-cloak class="flex flex-col gap-1 w-56">
            @csrf @method('PATCH')
            <textarea name="resolution_note" rows="2" required placeholder="How was this resolved?" class="w-full px-2 py-1 border border-gray-300 rounded text-xs"></textarea>
            <button type="submit" class="self-start px-3 py-1 rounded bg-gray-700 text-white text-xs font-semibold hover:bg-gray-800">Mark resolved</button>
        </form>
    @endif
</div>
