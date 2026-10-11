@props(['building'])

{{-- Phase 3 item 9: Remove a building (admin/manager). "End building" keeps all
     history; "Delete permanently" is offered only when it has no records. --}}
@php $canDelete = ! $building->hasHistory(); @endphp
<div x-data="{ open: false }" class="relative" @click.outside="open = false">
    <button type="button" @click.stop="open = !open" class="text-xs font-semibold text-red-600 hover:underline">Remove…</button>
    <div x-show="open" x-cloak @click.stop class="absolute right-0 z-20 mt-1 w-72 bg-white border border-gray-200 rounded-lg shadow-lg p-3 space-y-2 text-left">
        <form method="POST" action="{{ route('dashboard.buildings.remove', $building) }}"
              onsubmit="return confirm('End {{ $building->display_label }} as of today? It leaves Daily Entry and the active list. Its history, reports and past totals are kept; an admin can reopen it.')">
            @csrf
            <input type="hidden" name="mode" value="end" />
            <button type="submit" class="w-full text-left px-2 py-1.5 rounded text-sm hover:bg-orange-50">
                <span class="font-semibold text-orange-700">End building (keep history)</span>
                <span class="block text-xs text-gray-500">Leaves Daily Entry from today; nothing is deleted.</span>
            </button>
        </form>
        @if($canDelete)
            <form method="POST" action="{{ route('dashboard.buildings.remove', $building) }}"
                  onsubmit="return confirm('Permanently delete {{ $building->display_label }}? It has no records. This cannot be undone.')">
                @csrf
                <input type="hidden" name="mode" value="delete" />
                <button type="submit" class="w-full text-left px-2 py-1.5 rounded text-sm hover:bg-red-50">
                    <span class="font-semibold text-red-700">Delete permanently</span>
                    <span class="block text-xs text-gray-500">Only because it has no records yet.</span>
                </button>
            </form>
        @else
            <p class="px-2 text-xs text-gray-500">It has recorded history, so it can only be ended, not deleted.</p>
        @endif
    </div>
</div>
