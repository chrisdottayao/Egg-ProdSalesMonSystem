<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\CullRecord;
use App\Models\EggGradingDaily;
use App\Models\EggProduction;
use App\Models\HenBatch;
use App\Services\ProductionRollupService;
use App\Support\ManilaDate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyEntryController extends Controller
{
    // Same packing EggGradingDaily::booted() uses: 1 case = 12 trays = 360 pcs.
    private const PCS_PER_CASE = 360;
    private const PCS_PER_TRAY = 30;

    public function index(Request $request)
    {
        // S16: today (Manila) only. Admin/manager may still open a past date to
        // correct it — Daily Entry is the only editor for per-building rows.
        $today = ManilaDate::todayString();
        $requested = $request->query('date');
        if ($requested) {
            try {
                $requestedDay = Carbon::parse($requested)->toDateString();
            } catch (\Throwable) {
                $requestedDay = null;
            }
            $allowed = $requestedDay === $today
                || ($requestedDay !== null && $requestedDay < $today && $request->user()->canManageRecords());
            if (! $allowed) {
                return redirect()->route('daily-entry.index')->with('date_notice', ManilaDate::MESSAGE);
            }
        }

        $date         = Carbon::parse($requested ?: $today);
        $previousDate = $date->copy()->subDay();

        // whereNotNull('building_no'): a batch with no building number (e.g. legacy
        // seed data predating this feature) has no row to fill on a per-building
        // logbook form — including it would write a phantom building_daily row
        // and corrupt the rollup with population/eggs that belong to no building.
        // ->tracked(): only the 3 actively-tracked buildings (3M). Ordered by
        // the DISPLAYED number (3N), not the real building_no.
        // ->runningOn(): a building marked culled (S3) drops out of the form from
        // its culling date on (it still shows on earlier dates, for corrections).
        $batches = HenBatch::where('status', 'Active')->whereNotNull('building_no')->tracked()->runningOn($date->toDateString())
            ->orderByRaw('COALESCE(display_no, building_no) ASC')->get();

        // withPending(): the form shows what was entered, approved or not
        // (Phase 3 item 1) — a staff member sees their own pending/rejected row.
        $existingRows = BuildingDaily::withPending()->whereDate('date', $date)
            ->get()
            ->keyBy('hen_batch_id');

        $previousRows = BuildingDaily::withPending()->whereDate('date', $previousDate)
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
                // A building with no rows yet (just added, S17) starts from its
                // starting hen count.
                'population'   => $existing->population ?? $previous->net_birds
                    ?? ($batch->buildingDailies()->withoutGlobalScope('approved')->exists() ? null : $batch->batch_size),
                'mortality'    => $existing->mortality ?? null,
                'feed_bags'    => $existing->feed_bags ?? null,
                'eggs_house'   => $existing->eggs_house ?? null,
                'eggs_eggroom' => $existing->eggs_eggroom ?? null,
                'soft_shell'   => $existing->soft_shell ?? null,
                'age_weeks'    => $existing->age_weeks ?? $previous->age_weeks ?? null,
                'status'       => $existing?->approval_status,
                'review_note'  => $existing?->review_note,
                // Phase 3B item 4: staff see an approved (or someone else's) row read-only.
                'locked'       => $existing !== null && ! $this->staffMayChange($existing),
            ];
        });

        // withPending(): like the building rows, the sheet shows what was
        // entered whether or not it has been approved yet (Phase 3B).
        // Phase 3B item 5: buildings whose cull is waiting for approval today.
        $pendingCulls = CullRecord::pending()->whereDate('date', $date)->where('reason', CullRecord::BUILDING_CULLED_REASON)
            ->pluck('hen_batch_id')->flip();
        $rows = $rows->map(fn ($r) => $r + ['cull_pending' => $pendingCulls->has($r['batch']->id)]);

        $existingGrading = EggGradingDaily::withPending()->whereDate('date', $date)->get()->keyBy('category');

        $gradingRows = collect(EggGradingDaily::CATEGORIES)->map(function (string $category) use ($existingGrading) {
            $existing = $existingGrading->get($category);
            return [
                'category'  => $category,
                'total_pcs' => $existing->total_pcs ?? null,
                'status'    => $existing?->approval_status,
                'review_note' => $existing?->review_note,
                'locked'    => $existing !== null && ! $this->staffMayChange($existing),
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
        $baseRules = [
            'date'                        => $request->user()->canManageRecords()
                ? ['required', 'date', 'before_or_equal:' . ManilaDate::todayString()]
                : $this->entryDateRules(),
            'buildings'                   => 'required|array',
            'buildings.*.skip'            => 'nullable|boolean',
            'buildings.*.choice'          => 'nullable|in:skip,culled',
            'buildings.*.mortality'       => 'nullable|integer|min:0',
            'buildings.*.feed_bags'       => 'nullable|numeric|min:0',
            'buildings.*.eggs_eggroom'    => 'nullable|integer|min:0',
            'buildings.*.soft_shell'      => 'nullable|integer|min:0',
            'buildings.*.age_weeks'       => 'nullable|integer|min:0',
            'grading'                     => 'nullable|array',
            'grading.*.cases'             => 'nullable|integer|min:0',
            'grading.*.trays'             => 'nullable|integer|min:0',
            'grading.*.pieces'            => 'nullable|integer|min:0',
            'grading.*.total_pcs'         => 'nullable|integer|min:0',
        ];

        // population/eggs_house are required per row UNLESS that row is marked
        // "no data today" — built per submitted key rather than a wildcard rule,
        // since whether a row is required depends on that same row's own skip
        // flag, not a fixed pattern.
        // A culled row needs its population (the hens removed); eggs aren't
        // asked for.
        foreach ($request->input('buildings', []) as $henBatchId => $row) {
            $choice = is_array($row) ? self::rowChoice($row) : null;
            $baseRules["buildings.{$henBatchId}.population"] = match ($choice) {
                'skip'   => 'nullable|integer|min:0',
                'culled' => 'required|integer|min:1',
                default  => 'required|integer|min:0',
            };
            $baseRules["buildings.{$henBatchId}.eggs_house"] = $choice ? 'nullable|integer|min:0' : 'required|integer|min:0';
        }

        $validated = $request->validate($baseRules);

        $date = Carbon::parse($validated['date'])->format('Y-m-d');

        // Only hen_batch_ids that actually belong to an active, numbered, tracked,
        // not-ended building — a stale hidden input can't be used to write an
        // arbitrary building_daily row, or a row for a building already culled (S3).
        $validBatchIds = HenBatch::where('status', 'Active')->whereNotNull('building_no')->tracked()->runningOn($date)->pluck('id')->flip();

        DB::beginTransaction();

        try {
            $buildingsSaved  = 0;
            $buildingsSkipped = 0;

            // S18/S3: "Culled" ends the building as of this date — one cull
            // record for the remaining hens, ended_at set, and no normal
            // building_daily row (an existing one for this date is removed,
            // like a skip). Handled before the per-row loop so the loop never
            // sees these buildings.
            // Phase 3B item 5: a staff cull is pending, and so is its effect —
            // the building keeps running (and its hens keep counting) until a
            // manager or admin approves the cull; rejecting changes nothing.
            $culledLabels        = [];
            $pendingCullLabels   = [];
            foreach ($validated['buildings'] as $henBatchId => $row) {
                if (! $validBatchIds->has($henBatchId) || self::rowChoice($row) !== 'culled') {
                    continue;
                }

                $batch = HenBatch::find($henBatchId);
                // Marking the same building culled again the same day updates the
                // waiting cull rather than queueing a second one.
                $cull = CullRecord::pending()->whereDate('date', $date)->where('hen_batch_id', $henBatchId)
                    ->where('reason', CullRecord::BUILDING_CULLED_REASON)->where('entered_by', $request->user()->id)->first()
                    ?? new CullRecord(['date' => $date, 'hen_batch_id' => $henBatchId, 'reason' => CullRecord::BUILDING_CULLED_REASON]);
                $cull->fill(CullRecord::entryFieldsFor($request->user()) + ['quantity_culled' => (int) $row['population']])->save();
                $validBatchIds->forget($henBatchId);

                if ($cull->isPending()) {
                    $pendingCullLabels[] = $batch->display_label;
                    continue;
                }

                $cull->applyBuildingEnd();
                $culledLabels[] = $batch->display_label;
                $buildingsSkipped++; // changes the day's building set, so the rollup below re-runs
            }

            // Suppress BuildingDaily's own saving/saved hooks for this per-row loop —
            // otherwise the model's saved-event rollup fires once per building (41-45
            // times) instead of once for the whole submission. prod_rate is computed
            // here with the exact same formula the model uses, so nothing is lost by
            // suppressing the saving hook that would otherwise compute it.
            // Phase 3 item 1: admin/manager rows are approved on save; staff rows
            // wait for review and stay out of totals until approved.
            $approval = BuildingDaily::approvalFieldsFor($request->user());

            $savedValues = []; // per building, for the Audit Log (Phase 3 item 2)
            $savedRows   = []; // newly created or newly approved, for the auto-approve audit (3B item 7)
            BuildingDaily::withoutEvents(function () use ($validated, $date, $validBatchIds, $approval, &$buildingsSaved, &$buildingsSkipped, &$savedValues, &$savedRows) {
                foreach ($validated['buildings'] as $henBatchId => $row) {
                    if (! $validBatchIds->has($henBatchId)) {
                        continue;
                    }

                    // Phase 3B item 4: staff can't change an approved row (or another
                    // person's). Resubmitting it unchanged is fine — it is left as is.
                    $existing = BuildingDaily::withPending()->whereDate('date', $date)->where('hen_batch_id', $henBatchId)->first();
                    $locked   = $existing !== null && ! $this->staffMayChange($existing);

                    if (self::rowChoice($row) === 'skip') {
                        if ($locked) {
                            $this->denyStaffChange($existing);
                        }
                        // "No data today" means absence, not a zero row — a zero would
                        // tell the peer-comparison and age-relative rules in
                        // RecommendationService that this building existed and produced
                        // nothing, a different (false) signal from not being tracked
                        // that day. Delete rather than upsert zeros — also handles the
                        // edit case: a previously-filled row that's now marked skipped
                        // must not leave stale data behind.
                        $existing?->delete();
                        $buildingsSkipped++;
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

                    if ($locked) {
                        $same = (int) $existing->population === $population && (int) $existing->mortality === $mortality
                            && (int) $existing->eggs_house === $eggsHouse && (int) $existing->eggs_eggroom === $eggsEggroom
                            && (int) $existing->soft_shell === $softShell
                            && ($existing->feed_bags === null ? $feedBags === null : (float) $existing->feed_bags === $feedBags)
                            && ($existing->age_weeks === null ? $ageWeeks === null : (int) $existing->age_weeks === $ageWeeks);
                        if (! $same) {
                            $this->denyStaffChange($existing);
                        }
                        continue;
                    }

                    // The row found above (whereDate, so it matches however the
                    // driver stores the date) is updated; otherwise a new one.
                    $saved = ($existing ?? new BuildingDaily(['date' => $date, 'hen_batch_id' => $henBatchId]))->fill(
                        $approval + [
                            'user_id'      => auth()->id(),
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
                    $saved->save();
                    if ($saved->wasRecentlyCreated || $saved->wasChanged('approval_status')) {
                        $savedRows[] = $saved;
                    }

                    $savedValues[$henBatchId] = ['population' => $population, 'mortality' => $mortality, 'eggs_house' => $eggsHouse, 'feed_bags' => $feedBags];
                    $buildingsSaved++;
                }
            });

            // Phase 3B item 2: the grading sheet saved in the same submit follows
            // the same rule as the building rows — staff rows wait for review.
            // Events were off for the loop above (AuditLog's own hooks too), so
            // the building rows' auto-approve entries are written now.
            foreach ($savedRows as $saved) {
                BuildingDaily::auditAutoApproval($saved);
            }

            $gradingFields = EggGradingDaily::entryFieldsFor($request->user());
            $gradingSaved = 0;
            foreach ($validated['grading'] ?? [] as $category => $row) {
                if (! in_array($category, EggGradingDaily::CATEGORIES, true)) {
                    continue;
                }

                // S10/S22: the form sends one total per size. Never trust the
                // browser's cases/trays/pieces split — derive it here. A
                // submission without total_pcs falls back to the old fields.
                $totalPcs = isset($row['total_pcs']) && $row['total_pcs'] !== ''
                    ? (int) $row['total_pcs']
                    : ((int) ($row['cases'] ?? 0)) * self::PCS_PER_CASE
                        + ((int) ($row['trays'] ?? 0)) * self::PCS_PER_TRAY
                        + (int) ($row['pieces'] ?? 0);

                // Phase 3B item 4: an approved size (or someone else's) is read-only
                // for staff; sending the same total again leaves it as it is.
                $existingGrade = EggGradingDaily::withPending()->whereDate('date', $date)->where('category', $category)->first();
                if ($existingGrade && ! $this->staffMayChange($existingGrade)) {
                    if ((int) $existingGrade->total_pcs !== max(0, $totalPcs)) {
                        $this->denyStaffChange($existingGrade);
                    }
                    continue;
                }

                // Only sizes with a value are saved; 0/blank means no row
                // (and clears a row saved earlier for this date).
                if ($totalPcs <= 0) {
                    EggGradingDaily::withPending()->whereDate('date', $date)->where('category', $category)->delete();
                    continue;
                }

                // (date, category) is unique whatever the status: update the row
                // found above (withPending, whereDate) or add a new one.
                ($existingGrade ?? new EggGradingDaily(['date' => $date, 'category' => $category]))->fill(
                    $gradingFields + [
                        'cases'  => intdiv($totalPcs, self::PCS_PER_CASE),
                        'trays'  => intdiv($totalPcs % self::PCS_PER_CASE, self::PCS_PER_TRAY),
                        'pieces' => $totalPcs % self::PCS_PER_TRAY,
                    ]
                )->save();

                $gradingSaved++;
            }

            // Recompute whenever this submission changed the day's building set at
            // all — including a pure skip that deleted a previously-saved row, which
            // must also shrink the rollup, not just a fresh save growing it.
            $latestProductionIds = [];
            if ($buildingsSaved > 0 || $buildingsSkipped > 0) {
                (new ProductionRollupService)->rollupDate($date);

                // S14: the rolled-up egg_productions row is what Production
                // History highlights as the latest entry. Touch it so a
                // re-save with unchanged numbers still counts as the newest
                // (the rollup's update() is a no-op when nothing is dirty).
                $production = EggProduction::withPending()->whereDate('date', $date)->first();
                if ($production) {
                    $production->touchQuietly();
                    $latestProductionIds[] = $production->id;
                }
            }

            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'manual_entry',
                'model_type' => 'BuildingDailyManualEntry',
                'model_id'   => null,
                'details'    => [
                    'date'               => $date,
                    'buildings_saved'    => $buildingsSaved,
                    'buildings_skipped'  => $buildingsSkipped - count($culledLabels),
                    'buildings_culled'   => $culledLabels,
                    'culls_pending'      => $pendingCullLabels,
                    'grading_saved'      => $gradingSaved,
                    'status'             => ($buildingsSaved || $gradingSaved) ? $approval['approval_status'] : null,
                    'entries'            => collect($savedValues)->mapWithKeys(fn ($v, $id) => [HenBatch::find($id)?->display_label ?? "#{$id}" => $v])->all(),
                ],
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $skippedOnly = $buildingsSkipped - count($culledLabels);
        $message     = "Saved {$buildingsSaved} building rows ({$skippedOnly} skipped today) and {$gradingSaved} grading rows for {$date}.";
        if ($culledLabels) {
            $message .= ' Marked as culled: ' . implode(', ', $culledLabels) . '.';
        }
        if ($pendingCullLabels) {
            $message .= ' Cull of ' . implode(', ', $pendingCullLabels) . ' recorded; the building stays active until a manager or admin approves it.';
        }
        if (($buildingsSaved > 0 || $gradingSaved > 0) && ! $request->user()->canManageRecords()) {
            $message .= ' Everything you saved is waiting for a manager or admin to approve it before it counts in totals.';
        }

        return redirect()->route('daily-entry.index', ['date' => $date])
            ->with('success', $message)
            ->with('latest_ids', $latestProductionIds);
    }

    /** 'skip', 'culled' or null for a submitted building row (legacy skip=1 still means skip). */
    private static function rowChoice(array $row): ?string
    {
        $choice = $row['choice'] ?? null;
        if (in_array($choice, ['skip', 'culled'], true)) {
            return $choice;
        }

        return filter_var($row['skip'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'skip' : null;
    }
}
