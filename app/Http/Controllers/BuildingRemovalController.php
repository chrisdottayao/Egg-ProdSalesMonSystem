<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\HenBatch;
use App\Support\ManilaDate;
use Illuminate\Http\Request;

/**
 * Phase 3 item 9: "Remove" a building from the dashboard (admin/manager).
 *
 * - mode=end (always allowed): the building is ended as of today (Manila),
 *   reusing the ended/culled mechanism. It leaves Daily Entry and the active
 *   list; its history, reports and earlier totals are untouched. No cull
 *   record is created (the Daily Entry "Culled" choice remains the way to
 *   record a depopulation).
 * - mode=delete: permanent, and only for a building with no records at all
 *   (no daily entries of any status, culls, expenses or alerts) — e.g. one
 *   added by mistake.
 *
 * OPEN QUESTION for the panel: "remove" is implemented as end/archive.
 */
class BuildingRemovalController extends Controller
{
    public function remove(Request $request, HenBatch $henBatch)
    {
        $validated = $request->validate(['mode' => 'required|in:end,delete']);
        abort_unless($henBatch->is_tracked, 404);
        $label = $henBatch->display_label;

        if ($validated['mode'] === 'delete') {
            if ($henBatch->hasHistory()) {
                return redirect()->route('dashboard')->with('error', "{$label} has recorded history, so it can't be deleted. Use \"End building\" instead; its history stays.");
            }
            $this->audit('remove_building_delete', $henBatch, ['building' => $label]);
            $henBatch->delete();

            return redirect()->route('dashboard')->with('success', "{$label} deleted permanently (it had no records).");
        }

        if ($henBatch->isEnded()) {
            return redirect()->route('dashboard')->with('success', "{$label} was already removed on {$henBatch->ended_at->format('M d, Y')}.");
        }

        $today = ManilaDate::todayString();
        $henBatch->update(['ended_at' => $today]);
        $this->audit('remove_building_end', $henBatch, ['building' => $label, 'ended_at' => $today]);

        return redirect()->route('dashboard')->with('success', "{$label} removed as of {$today}. It no longer appears in Daily Entry; its history and reports are kept.");
    }

    private function audit(string $action, HenBatch $b, array $details): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => 'HenBatch',
            'model_id'   => $b->id,
            'details'    => $details,
        ]);
    }
}
