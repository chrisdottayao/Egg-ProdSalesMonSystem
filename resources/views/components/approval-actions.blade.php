@props(['type', 'record'])

{{-- Approve, or reject with a required note (Phase 3 item 1). --}}
<div class="flex flex-col items-end gap-2" x-data="{ rejecting: false }">
    <div class="flex gap-2" x-show="!rejecting">
        <form method="POST" action="{{ route('approvals.approve', [$type, $record->id]) }}">
            @csrf @method('PATCH')
            <button type="submit" class="px-3 py-1 rounded bg-[#4CAF50] text-white text-xs font-semibold hover:bg-green-600">Approve</button>
        </form>
        <button type="button" @click="rejecting = true" class="px-3 py-1 rounded border border-red-300 text-red-700 text-xs font-semibold hover:bg-red-50">Reject…</button>
    </div>
    <form method="POST" action="{{ route('approvals.reject', [$type, $record->id]) }}" x-show="rejecting" x-cloak class="flex flex-col items-end gap-1 w-64">
        @csrf @method('PATCH')
        <textarea name="review_note" rows="2" required placeholder="What needs fixing?" class="w-full px-2 py-1 border border-gray-300 rounded text-xs"></textarea>
        <div class="flex gap-2">
            <button type="button" @click="rejecting = false" class="text-xs text-gray-500 hover:underline">Cancel</button>
            <button type="submit" class="px-3 py-1 rounded bg-red-600 text-white text-xs font-semibold hover:bg-red-700">Reject</button>
        </div>
    </form>
</div>
