<x-app-layout>
<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800">Daily Building &amp; Grading Entry</h1>
        <form method="GET" action="{{ route('daily-entry.index') }}" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Date</label>
            <input type="date" name="date" value="{{ $date->format('Y-m-d') }}"
                   onchange="this.form.submit()"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
        </form>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border-l-4 border-red-500 px-4 py-3 rounded-lg text-sm text-red-800">
            <p class="font-semibold mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('daily-entry.store') }}" id="dailyEntryForm">
        @csrf
        <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}" />

        {{-- Building Daily table --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <h2 class="text-lg font-bold text-gray-800">Buildings ({{ $rows->count() }} active)</h2>
                <div class="text-sm text-gray-600">
                    Eggs House total (from table below): <span id="buildingEggsTotal" class="font-bold text-[#4CAF50]">0</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-1 font-semibold text-gray-700">Bldg</th>
                            <th class="text-left py-2 px-1 font-semibold text-gray-700">Batch</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Age (wks)</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Population *</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Mortality</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Feed Bags</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Eggs House *</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Eggs Egg Room</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Soft Shell</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Prod Rate</th>
                            <th class="text-center py-2 px-1 font-semibold text-gray-700">No Data Today</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php $batch = $row['batch']; @endphp
                            <tr class="border-b last:border-0 building-row" data-hen-batch-id="{{ $batch->id }}">
                                <td class="py-1 px-1 font-semibold text-gray-700">{{ $batch->effective_building_no ?? '—' }}</td>
                                <td class="py-1 px-1 text-gray-500 text-xs">{{ $batch->batch_id }}</td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][age_weeks]" value="{{ old("buildings.{$batch->id}.age_weeks", $row['age_weeks']) }}"
                                           class="row-field w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" required name="buildings[{{ $batch->id }}][population]" value="{{ old("buildings.{$batch->id}.population", $row['population']) }}"
                                           class="row-field population-input w-20 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][mortality]" value="{{ old("buildings.{$batch->id}.mortality", $row['mortality']) }}"
                                           class="row-field w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" step="0.01" name="buildings[{{ $batch->id }}][feed_bags]" value="{{ old("buildings.{$batch->id}.feed_bags", $row['feed_bags']) }}"
                                           class="row-field w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" required name="buildings[{{ $batch->id }}][eggs_house]" value="{{ old("buildings.{$batch->id}.eggs_house", $row['eggs_house']) }}"
                                           class="row-field eggs-house-input w-20 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][eggs_eggroom]" value="{{ old("buildings.{$batch->id}.eggs_eggroom", $row['eggs_eggroom']) }}"
                                           placeholder="= house"
                                           class="row-field w-20 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][soft_shell]" value="{{ old("buildings.{$batch->id}.soft_shell", $row['soft_shell']) }}"
                                           class="row-field w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1 text-right">
                                    <span class="prod-rate-display font-semibold text-gray-700">—</span>
                                </td>
                                <td class="py-1 px-1 text-center">
                                    <input type="hidden" name="buildings[{{ $batch->id }}][skip]" value="0" />
                                    <input type="checkbox" name="buildings[{{ $batch->id }}][skip]" value="1"
                                           {{ old("buildings.{$batch->id}.skip") ? 'checked' : '' }}
                                           class="skip-checkbox w-4 h-4" title="No data today — this building will not get a row for this date" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 mt-3">* Required unless "No Data Today" is checked. Feed bags and soft shell may be left blank. A row highlighted orange means eggs house exceeds population — not blocked, just worth a second look before you submit. "No Data Today" skips this building entirely for this date — it will not appear as a zero, matching how a building between flocks or simply not checked is handled everywhere else in the system.</p>
        </div>

        {{-- Egg Grading Daily table --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <h2 class="text-lg font-bold text-gray-800">Egg Grading (farm-wide)</h2>
                <div class="text-sm text-gray-600">
                    Grading total: <span id="gradingTotal" class="font-bold text-[#4CAF50]">0</span>
                    <span class="mx-2 text-gray-300">|</span>
                    vs Eggs House total: <span id="buildingEggsTotalEcho" class="font-bold text-gray-700">0</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-1 font-semibold text-gray-700">Category</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Cases</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Trays</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Pieces</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Total Pcs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($gradingRows as $row)
                            <tr class="border-b last:border-0 grading-row">
                                <td class="py-1 px-1 font-medium text-gray-700">{{ $row['category'] }}</td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="grading[{{ $row['category'] }}][cases]" value="{{ old("grading.{$row['category']}.cases", $row['cases']) }}"
                                           class="grading-cases w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="grading[{{ $row['category'] }}][trays]" value="{{ old("grading.{$row['category']}.trays", $row['trays']) }}"
                                           class="grading-trays w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="grading[{{ $row['category'] }}][pieces]" value="{{ old("grading.{$row['category']}.pieces", $row['pieces']) }}"
                                           class="grading-pieces w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1 text-right">
                                    <span class="grading-total-display font-semibold text-gray-700">0</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-[#4CAF50] text-white px-8 py-3 rounded-lg hover:bg-green-600 transition-colors font-medium">
                Save {{ $date->format('M d, Y') }}
            </button>
        </div>
    </form>

    {{-- ── Bulk / Historical Import ────────────────────────────────────────
         Separate from the day-to-day form above: use this for backfilling
         past dates from a spreadsheet instead of entering one date at a time. --}}
    <div class="pt-6 border-t border-gray-200">
        <h2 class="text-xl font-bold text-gray-800 mb-1">Bulk / Historical Import</h2>
        <p class="text-sm text-gray-500 mb-4">Import per-building daily logs and farm-level egg grading data from a CSV — for backfilling historical dates, not today's entry. Pick the button that matches your file; the importer does not try to guess.</p>

        @if(session('building_import_summary'))
            @php $s = session('building_import_summary'); @endphp
            <div class="p-4 bg-green-50 border border-green-300 rounded-lg text-sm mb-4">
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
            <div class="p-4 bg-green-50 border border-green-300 rounded-lg text-sm mb-4">
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
                <h3 class="text-lg font-bold text-gray-800 mb-2">Building Daily</h3>
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
                <h3 class="text-lg font-bold text-gray-800 mb-2">Egg Grading Daily</h3>
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
</div>

<script>
(function () {
    const buildingRows = document.querySelectorAll('.building-row');

    function recomputeBuildingRow(row) {
        const display = row.querySelector('.prod-rate-display');
        const skipBox = row.querySelector('.skip-checkbox');

        if (skipBox && skipBox.checked) {
            display.textContent = 'Skipped';
            display.classList.remove('text-orange-600');
            row.classList.remove('bg-orange-50');
            return;
        }

        const pop   = parseFloat(row.querySelector('.population-input').value) || 0;
        const eggs  = parseFloat(row.querySelector('.eggs-house-input').value) || 0;

        if (pop > 0) {
            const rate = (eggs / pop) * 100;
            display.textContent = rate.toFixed(1) + '%';
        } else {
            display.textContent = '—';
        }

        if (eggs > pop && pop > 0) {
            row.classList.add('bg-orange-50');
            display.classList.add('text-orange-600');
        } else {
            row.classList.remove('bg-orange-50');
            display.classList.remove('text-orange-600');
        }
    }

    function recomputeBuildingTotal() {
        let total = 0;
        document.querySelectorAll('.building-row').forEach(row => {
            const skipBox = row.querySelector('.skip-checkbox');
            if (skipBox && skipBox.checked) return; // skipped rows don't count toward the total
            const el = row.querySelector('.eggs-house-input');
            total += parseFloat(el.value) || 0;
        });
        document.getElementById('buildingEggsTotal').textContent = total.toLocaleString();
        document.getElementById('buildingEggsTotalEcho').textContent = total.toLocaleString();
    }

    function setRowSkipped(row, skipped) {
        row.querySelectorAll('.row-field').forEach(input => {
            input.disabled = skipped;
        });
        row.classList.toggle('opacity-50', skipped);
        row.classList.toggle('bg-gray-50', skipped);
        recomputeBuildingRow(row);
        recomputeBuildingTotal();
    }

    buildingRows.forEach(row => {
        row.querySelectorAll('.population-input, .eggs-house-input').forEach(input => {
            input.addEventListener('input', () => {
                recomputeBuildingRow(row);
                recomputeBuildingTotal();
            });
        });

        const skipBox = row.querySelector('.skip-checkbox');
        skipBox?.addEventListener('change', () => setRowSkipped(row, skipBox.checked));

        // Restore disabled/greyed state on redisplay after a validation error
        // elsewhere on the form — old() already restores the checkbox itself.
        setRowSkipped(row, !!skipBox?.checked);
    });
    recomputeBuildingTotal();

    // Grading section
    const gradingRows = document.querySelectorAll('.grading-row');

    function recomputeGradingRow(row) {
        const cases  = parseFloat(row.querySelector('.grading-cases').value) || 0;
        const trays  = parseFloat(row.querySelector('.grading-trays').value) || 0;
        const pieces = parseFloat(row.querySelector('.grading-pieces').value) || 0;
        const totalPcs = (cases * 360) + (trays * 30) + pieces;
        row.querySelector('.grading-total-display').textContent = totalPcs.toLocaleString();
        return totalPcs;
    }

    function recomputeGradingTotal() {
        let total = 0;
        gradingRows.forEach(row => { total += recomputeGradingRow(row); });
        document.getElementById('gradingTotal').textContent = total.toLocaleString();
    }

    gradingRows.forEach(row => {
        row.querySelectorAll('.grading-cases, .grading-trays, .grading-pieces').forEach(input => {
            input.addEventListener('input', recomputeGradingTotal);
        });
    });
    recomputeGradingTotal();
})();
</script>
</x-app-layout>
