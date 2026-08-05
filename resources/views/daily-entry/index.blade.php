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
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php $batch = $row['batch']; @endphp
                            <tr class="border-b last:border-0 building-row" data-hen-batch-id="{{ $batch->id }}">
                                <td class="py-1 px-1 font-semibold text-gray-700">{{ $batch->building_no ?? $batch->building ?? '—' }}</td>
                                <td class="py-1 px-1 text-gray-500 text-xs">{{ $batch->batch_id }}</td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][age_weeks]" value="{{ old("buildings.{$batch->id}.age_weeks", $row['age_weeks']) }}"
                                           class="w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" required name="buildings[{{ $batch->id }}][population]" value="{{ old("buildings.{$batch->id}.population", $row['population']) }}"
                                           class="population-input w-20 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][mortality]" value="{{ old("buildings.{$batch->id}.mortality", $row['mortality']) }}"
                                           class="w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" step="0.01" name="buildings[{{ $batch->id }}][feed_bags]" value="{{ old("buildings.{$batch->id}.feed_bags", $row['feed_bags']) }}"
                                           class="w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" required name="buildings[{{ $batch->id }}][eggs_house]" value="{{ old("buildings.{$batch->id}.eggs_house", $row['eggs_house']) }}"
                                           class="eggs-house-input w-20 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][eggs_eggroom]" value="{{ old("buildings.{$batch->id}.eggs_eggroom", $row['eggs_eggroom']) }}"
                                           placeholder="= house"
                                           class="w-20 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1">
                                    <input type="number" min="0" name="buildings[{{ $batch->id }}][soft_shell]" value="{{ old("buildings.{$batch->id}.soft_shell", $row['soft_shell']) }}"
                                           class="w-16 px-2 py-1 border border-gray-300 rounded text-right focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" />
                                </td>
                                <td class="py-1 px-1 text-right">
                                    <span class="prod-rate-display font-semibold text-gray-700">—</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 mt-3">* Required. Feed bags and soft shell may be left blank. A row highlighted orange means eggs house exceeds population — not blocked, just worth a second look before you submit.</p>
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
</div>

<script>
(function () {
    const buildingRows = document.querySelectorAll('.building-row');

    function recomputeBuildingRow(row) {
        const pop   = parseFloat(row.querySelector('.population-input').value) || 0;
        const eggs  = parseFloat(row.querySelector('.eggs-house-input').value) || 0;
        const display = row.querySelector('.prod-rate-display');

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
        document.querySelectorAll('.eggs-house-input').forEach(el => { total += parseFloat(el.value) || 0; });
        document.getElementById('buildingEggsTotal').textContent = total.toLocaleString();
        document.getElementById('buildingEggsTotalEcho').textContent = total.toLocaleString();
    }

    buildingRows.forEach(row => {
        recomputeBuildingRow(row);
        row.querySelectorAll('.population-input, .eggs-house-input').forEach(input => {
            input.addEventListener('input', () => {
                recomputeBuildingRow(row);
                recomputeBuildingTotal();
            });
        });
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
