<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\HenBatch;
use App\Support\ManilaDate;
use Illuminate\Http\Request;

class LivestockController extends Controller
{
    public function index()
    {
        // Tracked-only (3M): the live system actively displays 3 of the
        // farm's 45 real buildings. The other 42's flock records aren't
        // deleted — just not listed here — and stay reachable via Tinker/DB
        // if ever needed.
        $henBatches = HenBatch::tracked()->latest()->get();
        $activeHenCount = HenBatch::activeHenCount();
        return view('livestock.index', compact('henBatches', 'activeHenCount'));
    }

    public function storeHen(Request $request)
    {
        $validated = $request->validate([
            'batch_id'   => 'required|string|max:50|unique:hen_batches,batch_id',
            'batch_size' => 'required|integer|min:1',
            'status'     => 'required|in:Active,Culled,Mortality',
            'entry_date' => 'required|date',
            'notes'      => 'nullable|string',
            'pen_number' => 'nullable|string|max:100',
            'building'   => 'nullable|string|max:100',
        ]);

        HenBatch::create($validated);

        return redirect()->route('flock-records.index')->with('success', 'Hen record saved! Active hen count synced with Production module.');
    }

    /**
     * S17: add a building from the UI (admin/manager). Unlike storeHen(),
     * this sets everything Daily Entry needs (building_no, is_tracked), so
     * the building can be recorded for immediately.
     */
    public function storeBuilding(Request $request)
    {
        $validated = $request->validate([
            'building_no'    => ['required', 'integer', 'min:1', function ($attribute, $value, $fail) {
                // Clash with a running building's real or displayed number (3N
                // relabels e.g. real 4 as "Building 1").
                $taken = HenBatch::where('status', 'Active')->notEnded()
                    ->where(fn ($q) => $q->where('building_no', $value)->orWhereRaw('COALESCE(display_no, building_no) = ?', [$value]))
                    ->exists();
                if ($taken) {
                    $fail("Building {$value} already exists and is still active. Use another number, or mark the old one culled first.");
                }
            }],
            'batch_id'       => 'nullable|string|max:50|unique:hen_batches,batch_id',
            'breed'          => 'nullable|string|max:100',
            'batch_size'     => 'required|integer|min:1',
            'placement_date' => 'required|date|before_or_equal:' . ManilaDate::todayString(),
            'notes'          => 'nullable|string',
        ]);

        $number  = (int) $validated['building_no'];
        // Optional fields may be absent altogether (e.g. the dashboard form).
        $batchId = ($validated['batch_id'] ?? null) ?: "Building {$number}";
        if (HenBatch::where('batch_id', $batchId)->exists()) {
            // The default name is taken by an older (ended) flock in the same house.
            $batchId = "Building {$number} ({$validated['placement_date']})";
        }

        $building = HenBatch::create([
            'batch_id'       => $batchId,
            'batch_size'     => $validated['batch_size'],
            'status'         => 'Active',
            'entry_date'     => $validated['placement_date'],
            'placement_date' => $validated['placement_date'],
            'breed'          => ($validated['breed'] ?? null) ?: 'Dekalb White',
            'building_no'    => $number,
            'building'       => (string) $number, // read by alert clustering's adjacency note
            'display_no'     => null,             // label falls back to building_no
            'is_tracked'     => true,
            'notes'          => $validated['notes'] ?? null,
        ]);

        return $this->backTo($request)
            ->with('success', "{$building->display_label} added — it now appears in Daily Entry.");
    }

    public function updateHen(Request $request, HenBatch $henBatch)
    {
        $validated = $request->validate([
            'batch_id'   => 'required|string|max:50|unique:hen_batches,batch_id,' . $henBatch->id,
            'batch_size' => 'required|integer|min:1',
            'status'     => 'required|in:Active,Culled,Mortality',
            'entry_date' => 'required|date',
            'notes'      => 'nullable|string',
            'pen_number' => 'nullable|string|max:100',
            'building'   => 'nullable|string|max:100',
        ]);

        $henBatch->update($validated);

        return redirect()->route('flock-records.index')->with('success', 'Hen record updated.');
    }

    /**
     * Undo a "Culled" choice made by mistake in Daily Entry (S3): the
     * building reappears in Daily Entry. The cull record it created is kept
     * (it is history, and can be deleted from the Cull page if wrong).
     */
    public function reopenHen(Request $request, HenBatch $henBatch)
    {
        $endedAt = $henBatch->ended_at?->toDateString();
        $henBatch->update(['ended_at' => null]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'reopen_building',
            'model_type' => 'HenBatch',
            'model_id'   => $henBatch->id,
            'details'    => ['building' => $henBatch->display_label, 'previous_ended_at' => $endedAt],
        ]);

        return $this->backTo($request)->with('success', "{$henBatch->display_label} reopened — it appears in Daily Entry again.");
    }

    /** Add/reopen can be used from the dashboard too (Phase 3 item 9). */
    private function backTo(Request $request)
    {
        return $request->input('return_to') === 'dashboard'
            ? redirect()->route('dashboard')
            : redirect()->route('flock-records.index');
    }

    public function destroyHen(HenBatch $henBatch)
    {
        // Deleting a building with records would destroy or orphan its history
        // (and building_daily blocks it at the database level).
        if ($henBatch->hasHistory()) {
            return redirect()->route('flock-records.index')
                ->with('success', "{$henBatch->display_label} has recorded history, so it can't be deleted. End it from the dashboard (Remove → End building) instead.");
        }
        $henBatch->delete();
        return redirect()->route('flock-records.index')->with('success', 'Hen record deleted.');
    }
}
