<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\EggGradingDaily;
use App\Models\HenBatch;
use App\Services\ProductionRollupService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyEntryController extends Controller
{
    public function index(Request $request)
    {
        $date         = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::today();
        $previousDate = $date->copy()->subDay();

        // whereNotNull('building_no'): a batch with no building number (e.g. legacy
        // seed data predating this feature) has no row to fill on a per-building
        // logbook form — including it would write a phantom building_daily row
        // and corrupt the rollup with population/eggs that belong to no building.
        $batches = HenBatch::where('status', 'Active')->whereNotNull('building_no')->orderBy('building_no')->get();

        $existingRows = BuildingDaily::whereDate('date', $date)
            ->get()
            ->keyBy('hen_batch_id');

        $previousRows = BuildingDaily::whereDate('date', $previousDate)
            ->get()
            ->keyBy('hen_batch_id');

        // Row values for the form: an already-saved row for THIS date wins (editing),
        // otherwise pre-fill population/age from the PREVIOUS day so staff correct
        // numbers instead of retyping them. Population pre-fills from net_birds
        // (not population) — that's already yesterday's mortality subtracted.
        $rows = $batches->map(function (HenBatch $batch) use ($existingRows, $previousRows) {
            $existing = $existingRows->get($batch->id);
            $previous = $previousRows->get($batch->id);

            return [
                'batch'        => $batch,
                'population'   => $existing->population ?? $previous->net_birds ?? null,
                'mortality'    => $existing->mortality ?? null,
                'feed_bags'    => $existing->feed_bags ?? null,
                'eggs_house'   => $existing->eggs_house ?? null,
                'eggs_eggroom' => $existing->eggs_eggroom ?? null,
                'soft_shell'   => $existing->soft_shell ?? null,
                'age_weeks'    => $existing->age_weeks ?? $previous->age_weeks ?? null,
            ];
        });

        $existingGrading = EggGradingDaily::whereDate('date', $date)->get()->keyBy('category');

        $gradingRows = collect(EggGradingDaily::CATEGORIES)->map(function (string $category) use ($existingGrading) {
            $existing = $existingGrading->get($category);
            return [
                'category' => $category,
                'cases'    => $existing->cases ?? null,
                'trays'    => $existing->trays ?? null,
                'pieces'   => $existing->pieces ?? null,
            ];
        });

        return view('daily-entry.index', [
            'date'        => $date,
            'rows'        => $rows,
            'gradingRows' => $gradingRows,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'                        => 'required|date',
            'buildings'                   => 'required|array',
            'buildings.*.population'      => 'required|integer|min:0',
            'buildings.*.mortality'       => 'nullable|integer|min:0',
            'buildings.*.feed_bags'       => 'nullable|numeric|min:0',
            'buildings.*.eggs_house'      => 'required|integer|min:0',
            'buildings.*.eggs_eggroom'    => 'nullable|integer|min:0',
            'buildings.*.soft_shell'      => 'nullable|integer|min:0',
            'buildings.*.age_weeks'       => 'nullable|integer|min:0',
            'grading'                     => 'nullable|array',
            'grading.*.cases'             => 'nullable|integer|min:0',
            'grading.*.trays'             => 'nullable|integer|min:0',
            'grading.*.pieces'            => 'nullable|integer|min:0',
        ]);

        $date = Carbon::parse($validated['date'])->format('Y-m-d');

        // Only hen_batch_ids that actually belong to an active, numbered building —
        // a stale hidden input can't be used to write an arbitrary building_daily row.
        $validBatchIds = HenBatch::where('status', 'Active')->whereNotNull('building_no')->pluck('id')->flip();

        DB::beginTransaction();

        try {
            $buildingsSaved = 0;

            // Suppress BuildingDaily's own saving/saved hooks for this per-row loop —
            // otherwise the model's saved-event rollup fires once per building (41-45
            // times) instead of once for the whole submission. prod_rate is computed
            // here with the exact same formula the model uses, so nothing is lost by
            // suppressing the saving hook that would otherwise compute it.
            BuildingDaily::withoutEvents(function () use ($validated, $date, $validBatchIds, &$buildingsSaved) {
                foreach ($validated['buildings'] as $henBatchId => $row) {
                    if (! $validBatchIds->has($henBatchId)) {
                        continue;
                    }

                    $population  = (int) $row['population'];
                    $mortality   = (int) ($row['mortality'] ?? 0);
                    $eggsHouse   = (int) $row['eggs_house'];
                    // eggs_house and eggs_eggroom are identical in the farm's own
                    // records — default eggroom to house rather than make staff
                    // retype the same number on every row.
                    $eggsEggroom = isset($row['eggs_eggroom']) && $row['eggs_eggroom'] !== ''
                        ? (int) $row['eggs_eggroom'] : $eggsHouse;
                    $feedBags    = isset($row['feed_bags']) && $row['feed_bags'] !== ''
                        ? (float) $row['feed_bags'] : null; // blank stays null, not 0 — meaningful to Phase 4 rules
                    $softShell   = (int) ($row['soft_shell'] ?? 0);
                    $ageWeeks    = isset($row['age_weeks']) && $row['age_weeks'] !== ''
                        ? (int) $row['age_weeks'] : null;

                    BuildingDaily::updateOrCreate(
                        ['date' => $date, 'hen_batch_id' => $henBatchId],
                        [
                            'population'   => $population,
                            'mortality'    => $mortality,
                            'net_birds'    => max(0, $population - $mortality),
                            'feed_bags'    => $feedBags,
                            'eggs_house'   => $eggsHouse,
                            'eggs_eggroom' => $eggsEggroom,
                            'soft_shell'   => $softShell,
                            'age_weeks'    => $ageWeeks,
                            'prod_rate'    => $population > 0 ? round(($eggsHouse / $population) * 100, 2) : 0,
                        ]
                    );

                    $buildingsSaved++;
                }
            });

            $gradingSaved = 0;
            foreach ($validated['grading'] ?? [] as $category => $row) {
                if (! in_array($category, EggGradingDaily::CATEGORIES, true)) {
                    continue;
                }

                $cases  = (int) ($row['cases'] ?? 0);
                $trays  = (int) ($row['trays'] ?? 0);
                $pieces = (int) ($row['pieces'] ?? 0);

                EggGradingDaily::updateOrCreate(
                    ['date' => $date, 'category' => $category],
                    ['cases' => $cases, 'trays' => $trays, 'pieces' => $pieces]
                );

                $gradingSaved++;
            }

            if ($buildingsSaved > 0) {
                (new ProductionRollupService)->rollupDate($date);
            }

            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'manual_entry',
                'model_type' => 'BuildingDailyManualEntry',
                'model_id'   => null,
                'details'    => [
                    'date'             => $date,
                    'buildings_saved'  => $buildingsSaved,
                    'grading_saved'    => $gradingSaved,
                ],
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('daily-entry.index', ['date' => $date])
            ->with('success', "Saved {$buildingsSaved} building rows and {$gradingSaved} grading rows for {$date}.");
    }
}
