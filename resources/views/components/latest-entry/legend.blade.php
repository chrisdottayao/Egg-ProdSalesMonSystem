@props(['latest'])

{{-- S8: explains the History-row highlight. Uses the same classes as the row. --}}
@if($latest->any())
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-xs text-gray-600']) }}>
        <span class="inline-block w-4 h-4 rounded {{ \App\Support\LatestEntry::DOT_CLASSES }}"></span>
        Latest entry (highlighted until the next one is saved)
    </span>
@endif
