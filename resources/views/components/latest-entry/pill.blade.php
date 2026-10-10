@props(['latest'])

<span class="ml-1 inline-block whitespace-nowrap text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded {{ \App\Support\LatestEntry::PILL_CLASSES }}"
      title="{{ $latest->addedLabel() }}">New · {{ $latest->addedLabel() }}</span>
