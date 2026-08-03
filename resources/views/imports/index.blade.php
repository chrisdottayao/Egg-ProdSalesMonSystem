<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Bulk Data Import</h1>
    <p class="text-sm text-gray-500">Import per-building daily logs and farm-level egg grading data. Pick the button that matches your file — the importer does not try to guess.</p>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    @if(session('building_import_summary'))
        @php $s = session('building_import_summary'); @endphp
        <div class="p-4 bg-green-50 border border-green-300 rounded-lg text-sm">
            <p class="font-bold text-green-800 mb-2">Building Daily Import Complete</p>
            <ul class="space-y-1 text-green-700">
                <li><strong>{{ $s['imported'] }}</strong> rows imported, {{ $s['skipped'] }} skipped (duplicates)</li>
                <li>{{ $s['hen_batches_created'] }} hen batches created</li>
                @if($s['prod_rate_mismatches'] > 0)
                    <li class="text-orange-700">{{ $s['prod_rate_mismatches'] }} prod_rate mismatches — see error log</li>
                @endif
                @if($s['errors'] > 0)
                    <li class="text-orange-700">{{ $s['errors'] }} rows with errors
                        @if(session('import_error_token'))
                            &mdash; <a href="{{ route('imports.errors', session('import_error_token')) }}" class="underline font-semibold">Download error log</a>
                        @endif
                    </li>
                @endif
            </ul>
        </div>
    @endif

    @if(session('grading_import_summary'))
        @php $s = session('grading_import_summary'); @endphp
        <div class="p-4 bg-green-50 border border-green-300 rounded-lg text-sm">
            <p class="font-bold text-green-800 mb-2">Egg Grading Import Complete</p>
            <ul class="space-y-1 text-green-700">
                <li><strong>{{ $s['imported'] }}</strong> rows imported, {{ $s['skipped'] }} skipped (duplicates)</li>
                @if($s['total_pcs_mismatches'] > 0)
                    <li class="text-orange-700">{{ $s['total_pcs_mismatches'] }} total_pcs mismatches — see error log</li>
                @endif
                @if($s['errors'] > 0)
                    <li class="text-orange-700">{{ $s['errors'] }} rows with errors
                        @if(session('import_error_token'))
                            &mdash; <a href="{{ route('imports.errors', session('import_error_token')) }}" class="underline font-semibold">Download error log</a>
                        @endif
                    </li>
                @endif
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Building Daily --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-2">Building Daily</h2>
            <p class="text-xs text-gray-500 mb-4">One row per building per day: population, mortality, feed, eggs, age. Matched to a hen batch by building_no.</p>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-xs text-blue-800">
                <p class="font-semibold mb-1">Expected CSV columns:</p>
                <p class="font-mono">date, building_no, population, mortality, net_birds, feed_bags, eggs_house, eggs_eggroom, soft_shell, age_weeks, prod_rate</p>
            </div>

            <a href="{{ route('imports.building-daily.template') }}" class="flex items-center gap-1 text-sm text-blue-600 hover:underline mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download CSV Template
            </a>

            <form method="POST" action="{{ route('imports.building-daily.import') }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="csv_file" accept=".csv,.txt" required
                       class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 mb-4" />
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
                    Import Building Daily
                </button>
            </form>
        </div>

        {{-- Egg Grading Daily --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-2">Egg Grading Daily</h2>
            <p class="text-xs text-gray-500 mb-4">Farm-level grading output by category — no building column, since grading happens once for the whole farm at the egg room.</p>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-xs text-blue-800">
                <p class="font-semibold mb-1">Expected CSV columns:</p>
                <p class="font-mono">date, category, cases, trays, pieces, total_pcs</p>
                <p class="mt-1">Categories: No Value, No Weight, Peewee, Small, Medium, Large, XLarge, Jumbo, Broken, Dirty, Waste, TAPON</p>
            </div>

            <a href="{{ route('imports.egg-grading.template') }}" class="flex items-center gap-1 text-sm text-blue-600 hover:underline mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download CSV Template
            </a>

            <form method="POST" action="{{ route('imports.egg-grading.import') }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="csv_file" accept=".csv,.txt" required
                       class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 mb-4" />
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
                    Import Egg Grading Daily
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
