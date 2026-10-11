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

        {{-- Phase 3 item 10: the farm-wide grading sheet comes first, then the
             per-building rows. Layout only: same single form, fields and Save. --}}
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
                            @php $cat = $row['category']; $ro = $row['locked'] ? 'readonly title="Approved — ask a manager or admin to correct it"' : ''; @endphp
                            <tr class="border-b last:border-0 grading-row">
                                <td class="py-1 px-1 font-medium text-gray-700">{{ $cat }}
                                    @if($row['locked'])<span class="ml-1 text-xs font-normal text-green-700" title="Approved — ask a manager or admin to correct it">🔒</span>
                                    @elseif(in_array($row['status'], ['pending', 'rejected'], true))<div><x-approval-status :status="$row['status']" :note="$row['review_note']" /></div>@endif
                                </td>
                                <td class="py-1 px-1 text-right">
                                    <input type="number" min="0" step="1" name="grading[{{ $cat }}][total_pcs]" value="{{ old("grading.{$cat}.total_pcs", $row['total_pcs']) }}" {!! $ro !!}
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
                <p class="text-xs text-gray-500 border-t border-gray-200 pt-3">Farm-level grading output by category — no building column, since grading happens once for the whole farm at the egg room. The grading total is compared against the Eggs House total from the Buildings table below.</p>
            </aside>
            </div>
        </div>

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
                            @php $batch = $row['batch']; $ro = $row['locked'] ? 'readonly' : ''; @endphp
                            <tr class="border-b last:border-0 building-row" data-hen-batch-id="{{ $batch->id }}">
                                <td class="py-1 px-1 font-semibold text-gray-700">{{ $batch->effective_building_no ?? '—' }}</td>
                                <td class="py-1 px-1 text-gray-500 text-xs">
                                    {{ $batch->batch_id }}
                                    @if($row['locked'])
                                        {{-- Phase 3B item 4: approved rows are read-only for staff. --}}
                                        <div class="mt-1 text-xs text-green-700">🔒 Approved — ask a manager or admin to correct it</div>
                                    @elseif(in_array($row['status'], ['pending', 'rejected'], true))
                                        <div class="mt-1"><x-approval-status :status="$row['status']" :note="$row['review_note']" /></div>
                                    @endif
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][age_weeks]" value="{{ old("buildings.{$batch->id}.age_weeks", $row['age_weeks']) }}" {{ $ro }}
                                           class="row-field w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" required name="buildings[{{ $batch->id }}][population]" value="{{ old("buildings.{$batch->id}.population", $row['population']) }}" {{ $ro }}
                                           class="row-field population-input w-20 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][mortality]" value="{{ old("buildings.{$batch->id}.mortality", $row['mortality']) }}" {{ $ro }}
                                           class="row-field w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" step="0.01" name="buildings[{{ $batch->id }}][feed_bags]" value="{{ old("buildings.{$batch->id}.feed_bags", $row['feed_bags']) }}" {{ $ro }}
                                           class="row-field w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" required name="buildings[{{ $batch->id }}][eggs_house]" value="{{ old("buildings.{$batch->id}.eggs_house", $row['eggs_house']) }}" {{ $ro }}
                                           class="row-field eggs-house-input w-20 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][eggs_eggroom]" value="{{ old("buildings.{$batch->id}.eggs_eggroom", $row['eggs_eggroom']) }}" {{ $ro }}
                                           placeholder="= house"
                                           class="row-field w-20 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][soft_shell]" value="{{ old("buildings.{$batch->id}.soft_shell", $row['soft_shell']) }}" {{ $ro }}
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
                                            <input type="radio" class="row-choice sr-only" name="buildings[{{ $batch->id }}][choice]" value="skip" {{ $choice === 'skip' ? 'checked' : '' }} @disabled($row['locked']) />Skip today
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

        <div class="flex justify-end">
            <button type="submit" class="bg-[#4CAF50] text-white px-8 py-3 rounded-lg hover:bg-green-600 transition-colors font-medium">
                Save {{ $date->format('M d, Y') }}
            </button>
        </div>
    </form>

    @if(auth()->user()->canManageRecords())
    {{-- ── Import data (S20) ───────────────────────────────────────────────
         Historical backfill from CSV, separate from today's form above. Pick
         Production, Sales or both; each file is imported on its own, so one
         failing file does not undo another that succeeded. --}}
    <div class="pt-6 border-t border-gray-200"
         x-data="{ production: {{ in_array('production', old('types', [])) ? 'true' : 'false' }}, sales: {{ in_array('sales', old('types', [])) ? 'true' : 'false' }}, uploading: false }">
        <h2 class="text-xl font-bold text-gray-800 mb-1">Import Data</h2>
        <p class="text-sm text-gray-500 mb-4">Load past records from CSV files — for backfilling history, not today's entry. Choose what to import: production, sales, or both.</p>

        @if(session('combined_import_results'))
            @php
                $summaryLabels = [
                    'imported' => 'imported', 'skipped' => 'skipped (already recorded)', 'failed' => 'rejected',
                    'errors' => 'rows with errors', 'hen_batches_created' => 'hen batches created',
                    'prod_rate_mismatches' => 'prod-rate mismatches', 'total_pcs_mismatches' => 'total-pieces mismatches',
                ];
            @endphp
            <div class="space-y-3 mb-4">
                @foreach(session('combined_import_results') as $r)
                    <div class="p-4 rounded-lg text-sm border {{ $r['ok'] ? 'bg-green-50 border-green-300' : 'bg-red-50 border-red-300' }}">
                        <p class="font-bold {{ $r['ok'] ? 'text-green-800' : 'text-red-800' }} mb-1">{{ $r['type'] }}: {{ $r['ok'] ? 'import complete' : 'not imported' }}</p>
                        @if($r['ok'])
                            <ul class="text-green-700 flex flex-wrap gap-x-4 gap-y-1">
                                @foreach($r['summary'] as $key => $value)
                                    @if(isset($summaryLabels[$key]))
                                        <li><strong>{{ $value }}</strong> {{ $summaryLabels[$key] }}</li>
                                    @endif
                                @endforeach
                            </ul>
                            @if($r['error_url'])
                                <a href="{{ $r['error_url'] }}" class="inline-block mt-1 underline font-semibold text-orange-700">Download error log</a>
                            @endif
                        @else
                            <p class="text-red-700">{{ $r['message'] }}</p>
                        @endif
                    </div>
                @endforeach
                @if(collect(session('combined_import_results'))->contains('ok', false) && collect(session('combined_import_results'))->contains('ok', true))
                    <p class="text-xs text-gray-600">Each file is imported separately: the files marked complete were saved even though another file failed.</p>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('imports.combined') }}" enctype="multipart/form-data" @submit="uploading = true"
              class="bg-white rounded-lg shadow-md p-6 space-y-5">
            @csrf

            <fieldset>
                <legend class="text-sm font-semibold text-gray-700 mb-2">What do you want to import?</legend>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer" :class="production ? 'border-blue-500 bg-blue-50' : 'border-gray-300'">
                        <input type="checkbox" name="types[]" value="production" x-model="production" class="rounded border-gray-300 text-blue-600" />
                        <span class="text-sm font-medium text-gray-800">Production</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer" :class="sales ? 'border-blue-500 bg-blue-50' : 'border-gray-300'">
                        <input type="checkbox" name="types[]" value="sales" x-model="sales" class="rounded border-gray-300 text-blue-600" />
                        <span class="text-sm font-medium text-gray-800">Sales</span>
                    </label>
                </div>
                @error('types')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </fieldset>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Production: building daily + egg grading --}}
                <div x-show="production" x-cloak class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-bold text-gray-800 mb-1">Building daily CSV</h3>
                        <p class="text-xs text-gray-500 mb-2">One row per building per day. Matched to a building by building_no.</p>
                        <p class="text-xs font-mono bg-blue-50 text-blue-800 rounded p-2 mb-2">date, building_no, population, mortality, net_birds, feed_bags, eggs_house, eggs_eggroom, soft_shell, age_weeks, prod_rate</p>
                        <a href="{{ route('imports.building-daily.template') }}" class="text-sm text-blue-600 hover:underline">Download template</a>
                        <input type="file" name="building_file" accept=".csv,.txt" class="block w-full mt-2 text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                        @error('building_file')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-bold text-gray-800 mb-1">Egg grading CSV</h3>
                        <p class="text-xs text-gray-500 mb-2">Farm-level grading per size, per day.</p>
                        <p class="text-xs font-mono bg-blue-50 text-blue-800 rounded p-2 mb-2">date, category, cases, trays, pieces, total_pcs</p>
                        <a href="{{ route('imports.egg-grading.template') }}" class="text-sm text-blue-600 hover:underline">Download template</a>
                        <input type="file" name="grading_file" accept=".csv,.txt" class="block w-full mt-2 text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                        @error('grading_file')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Sales --}}
                <div x-show="sales" x-cloak class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-bold text-gray-800 mb-1">Sales CSV</h3>
                    <p class="text-xs text-gray-500 mb-2">One row per size per day. Rows already recorded are skipped.</p>
                    <p class="text-xs font-mono bg-blue-50 text-blue-800 rounded p-2 mb-2">date, egg_size, quantity_sold, price_per_unit, notes</p>
                    <a href="{{ route('sales.import.template') }}" class="text-sm text-blue-600 hover:underline">Download template</a>
                    <input type="file" name="sales_file" accept=".csv,.txt" class="block w-full mt-2 text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    @error('sales_file')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 flex-wrap">
                <p class="text-xs text-gray-500" x-show="production && sales">Production and sales are imported separately — if one file fails, the other is still saved.</p>
                <p class="text-xs text-gray-500" x-show="!production && !sales">Tick Production, Sales, or both.</p>
                <button type="submit" :disabled="uploading || (!production && !sales)"
                        class="ml-auto bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium disabled:opacity-50">
                    <span x-text="uploading ? 'Importing…' : 'Import'"></span>
                </button>
            </div>
        </form>
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
