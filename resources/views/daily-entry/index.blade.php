<x-app-layout>
<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800">Daily Building &amp; Grading Entry</h1>
        @if(auth()->user()->canManageRecords())
            {{-- Admin/manager: today, or a past date to correct it (S16). --}}
            <form method="GET" action="{{ route('daily-entry.index') }}" class="flex items-center gap-2">
                <label class="text-sm text-gray-600">Date</label>
                <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" max="{{ \App\Support\ManilaDate::todayString() }}"
                       onchange="this.form.submit()"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
            </form>
        @else
            <div class="text-sm text-gray-600">Recording for <span class="font-semibold text-gray-800">today, {{ $date->format('M d, Y') }}</span></div>
        @endif
    </div>

    @if(session('date_notice'))
        <div class="bg-yellow-50 border-l-4 border-yellow-500 px-4 py-3 rounded-lg text-sm text-yellow-800">{{ session('date_notice') }} Showing today instead.</div>
    @endif

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800 flex items-center justify-between flex-wrap gap-2">
            <span>{{ session('success') }}</span>
            @if(session('latest_ids'))
                <a href="{{ route('productions.index', ['latest' => 1]) }}" class="font-semibold underline">View in Production History</a>
            @endif
        </div>
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
                            <th class="text-center py-2 px-1 font-semibold text-gray-700">Skip / Culled</th>
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
                                {{-- S18: Skip today / Culled. Neither selected = normal entry;
                                     clicking a selected option again clears it. --}}
                                @php $choice = old("buildings.{$batch->id}.choice"); @endphp
                                <td class="py-1 px-1 text-center whitespace-nowrap">
                                    <input type="hidden" name="buildings[{{ $batch->id }}][choice]" value="" />
                                    <div class="inline-flex rounded border border-gray-300 overflow-hidden text-xs" role="radiogroup" aria-label="{{ $batch->display_label }}: skip or culled">
                                        <label class="row-choice-label cursor-pointer px-2 py-1 has-[:checked]:bg-gray-600 has-[:checked]:text-white" title="No data today — this building will not get a row for this date">
                                            <input type="radio" class="row-choice sr-only" name="buildings[{{ $batch->id }}][choice]" value="skip" {{ $choice === 'skip' ? 'checked' : '' }} />Skip today
                                        </label>
                                        <label class="row-choice-label cursor-pointer px-2 py-1 border-l border-gray-300 has-[:checked]:bg-red-600 has-[:checked]:text-white" title="Building fully culled — it stops appearing in Daily Entry">
                                            <input type="radio" class="row-choice sr-only" name="buildings[{{ $batch->id }}][choice]" value="culled" data-label="{{ $batch->display_label }}" {{ $choice === 'culled' ? 'checked' : '' }} />Culled
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 mt-3">* Required unless "Skip today" or "Culled" is selected (Culled still needs the population — the hens removed). Feed bags and soft shell may be left blank. A row highlighted orange means eggs house exceeds population — not blocked, just worth a second look before you submit. "Skip today" (no data today) skips this building entirely for this date — it will not appear as a zero, matching how a building between flocks or simply not checked is handled everywhere else in the system. "Culled" records the remaining population as culled, marks the building as ended on this date, and removes it from Daily Entry; its history stays available, and an admin can reopen it from Flock Records.</p>
        </div>

        {{-- Egg Grading Daily table --}}
        {{-- S9: the totals caption sits beside the grading table (right column)
             at lg widths and stacks below it on smaller screens. --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Egg Grading (farm-wide)</h2>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="overflow-x-auto lg:col-span-2">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-1 font-semibold text-gray-700">Category</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-700">Total Pieces</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-500">Cases</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-500">Trays</th>
                            <th class="text-right py-2 px-1 font-semibold text-gray-500">Pieces</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- S10/S22: staff type one number per size; cases/trays/pieces are
                             derived (1 case = 360 pcs = 12 trays, 1 tray = 30 pcs) and sent as
                             hidden fields. The server recomputes them from total_pcs anyway. --}}
                        @foreach($gradingRows as $row)
                            @php $cat = $row['category']; @endphp
                            <tr class="border-b last:border-0 grading-row">
                                <td class="py-1 px-1 font-medium text-gray-700">{{ $cat }}</td>
                                <td class="py-1 px-1 text-right">
                                    <input type="number" min="0" step="1" name="grading[{{ $cat }}][total_pcs]" value="{{ old("grading.{$cat}.total_pcs", $row['total_pcs']) }}"
                                           placeholder="0"
                                           class="grading-total-input w-24 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                    <input type="hidden" name="grading[{{ $cat }}][cases]"  class="grading-cases" />
                                    <input type="hidden" name="grading[{{ $cat }}][trays]"  class="grading-trays" />
                                    <input type="hidden" name="grading[{{ $cat }}][pieces]" class="grading-pieces" />
                                </td>
                                <td class="py-1 px-1 text-right"><span class="grading-cases-display text-gray-400">—</span></td>
                                <td class="py-1 px-1 text-right"><span class="grading-trays-display text-gray-400">—</span></td>
                                <td class="py-1 px-1 text-right"><span class="grading-pieces-display text-gray-400">—</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-xs text-gray-500 mt-3">Type the total pieces for each size. Cases and trays are computed automatically (1 case = 12 trays = 360 pcs; 1 tray = 30 pcs). Sizes left blank or 0 are not saved.</p>
            </div>

            <aside class="lg:col-span-1 lg:sticky lg:top-4 rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3 text-sm">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-gray-600">Grading total</span>
                    <span id="gradingTotal" class="text-xl font-bold text-[#4CAF50]">0</span>
                </div>
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-gray-600">Eggs House total</span>
                    <span id="buildingEggsTotalEcho" class="text-xl font-bold text-gray-700">0</span>
                </div>
                <div id="gradingMatch" class="rounded px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-500">—</div>
                <p class="text-xs text-gray-500 border-t border-gray-200 pt-3">Farm-level grading output by category — no building column, since grading happens once for the whole farm at the egg room. The grading total is compared against the Eggs House total from the buildings table above.</p>
            </aside>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-[#4CAF50] text-white px-8 py-3 rounded-lg hover:bg-green-600 transition-colors font-medium">
                Save {{ $date->format('M d, Y') }}
            </button>
        </div>
    </form>

    @if(auth()->user()->canManageRecords())
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
    @endif
</div>

<script>
(function () {
    const buildingRows = document.querySelectorAll('.building-row');

    // 'skip', 'culled' or '' (normal entry) for a building row.
    function rowChoice(row) {
        return row.querySelector('.row-choice:checked')?.value || '';
    }

    function recomputeBuildingRow(row) {
        const display = row.querySelector('.prod-rate-display');
        const choice  = rowChoice(row);

        if (choice) {
            display.textContent = choice === 'culled' ? 'Culled' : 'Skipped';
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
            if (rowChoice(row)) return; // skipped/culled rows don't count toward the total
            const el = row.querySelector('.eggs-house-input');
            total += parseFloat(el.value) || 0;
        });
        document.getElementById('buildingEggsTotal').textContent = total.toLocaleString();
        document.getElementById('buildingEggsTotalEcho').textContent = total.toLocaleString();
        buildingEggsTotal = total;
        updateGradingMatch();
    }

    // Match/mismatch indicator in the caption beside the grading table.
    let buildingEggsTotal = 0;
    let gradingTotal      = 0;
    function updateGradingMatch() {
        const el = document.getElementById('gradingMatch');
        if (!el) return;
        const base = 'rounded px-2 py-1 text-xs font-semibold ';
        if (gradingTotal === 0 && buildingEggsTotal === 0) {
            el.className = base + 'bg-gray-100 text-gray-500';
            el.textContent = '—';
        } else if (gradingTotal === buildingEggsTotal) {
            el.className = base + 'bg-green-100 text-green-700';
            el.textContent = 'Totals match';
        } else {
            const diff = gradingTotal - buildingEggsTotal;
            el.className = base + 'bg-orange-100 text-orange-700';
            el.textContent = 'Mismatch: grading is ' + Math.abs(diff).toLocaleString() + (diff > 0 ? ' more' : ' fewer') + ' than Eggs House';
        }
    }

    function setRowState(row) {
        const choice = rowChoice(row);
        row.querySelectorAll('.row-field').forEach(input => {
            // A culled row keeps its population: that is the number of hens culled.
            input.disabled = choice === 'skip' || (choice === 'culled' && !input.classList.contains('population-input'));
        });
        row.classList.toggle('opacity-50', choice === 'skip');
        row.classList.toggle('bg-gray-50', choice === 'skip');
        row.classList.toggle('bg-red-50', choice === 'culled');
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

        row.querySelectorAll('.row-choice').forEach(radio => {
            // Remember the state before the click so a second click clears it.
            radio.closest('label').addEventListener('mousedown', () => { radio.dataset.wasChecked = radio.checked ? '1' : ''; });
            radio.addEventListener('click', () => {
                if (radio.dataset.wasChecked === '1') {
                    radio.checked = false;
                } else if (radio.value === 'culled') {
                    const msg = 'This marks ' + radio.dataset.label + ' as fully culled as of {{ $date->format('M d, Y') }}. It will stop appearing in Daily Entry.';
                    if (!confirm(msg)) {
                        radio.checked = false;
                    }
                }
                radio.dataset.wasChecked = '';
                setRowState(row);
            });
        });

        // Restore disabled/greyed state on redisplay after a validation error
        // elsewhere on the form — old() already restores the choice itself.
        setRowState(row);
    });
    recomputeBuildingTotal();

    // Grading section
    const gradingRows = document.querySelectorAll('.grading-row');

    const PCS_PER_CASE = 360, PCS_PER_TRAY = 30;

    function recomputeGradingRow(row) {
        const totalPcs = Math.max(0, Math.floor(parseFloat(row.querySelector('.grading-total-input').value) || 0));
        const hasValue = totalPcs > 0;
        const parts = {
            cases:  Math.floor(totalPcs / PCS_PER_CASE),
            trays:  Math.floor((totalPcs % PCS_PER_CASE) / PCS_PER_TRAY),
            pieces: totalPcs % PCS_PER_TRAY,
        };
        Object.entries(parts).forEach(([key, value]) => {
            // Blank/0 sizes submit nothing and show "—" (not saved server-side).
            row.querySelector('.grading-' + key).value = hasValue ? value : '';
            const display = row.querySelector('.grading-' + key + '-display');
            display.textContent = hasValue ? value.toLocaleString() : '—';
            display.classList.toggle('text-gray-400', !hasValue);
            display.classList.toggle('font-semibold', hasValue);
            display.classList.toggle('text-gray-700', hasValue);
        });
        return totalPcs;
    }

    function recomputeGradingTotal() {
        let total = 0;
        gradingRows.forEach(row => { total += recomputeGradingRow(row); });
        document.getElementById('gradingTotal').textContent = total.toLocaleString();
        gradingTotal = total;
        updateGradingMatch();
    }

    gradingRows.forEach(row => {
        row.querySelector('.grading-total-input').addEventListener('input', recomputeGradingTotal);
    });
    recomputeGradingTotal();
})();
</script>
</x-app-layout>
