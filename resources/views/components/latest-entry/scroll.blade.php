{{-- Scroll the highlighted row into view only right after a save (flash), or
     when arriving from the Daily Entry "View in Production History" link. --}}
@if(session('latest_ids') || request()->boolean('latest'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelector('[data-latest]')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    </script>
@endif
