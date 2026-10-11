<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BuildingDaily;
use App\Models\EggSale;
use App\Services\ProductionRollupService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Phase 3 item 1 (S11, S5): admin/manager review of staff entries. A
 * pending row counts nowhere until approved; a rejected row stays visible
 * to staff with the reviewer's note and can be corrected and resubmitted.
 */
class ApprovalController extends Controller
{
    private const TYPES = [
        'building-daily' => BuildingDaily::class,
        'sales'          => EggSale::class,
    ];

    public function index()
    {
        $buildingRows = BuildingDaily::pending()->with(['henBatch', 'user'])->orderBy('date')->orderBy('id')->get();
        $sales        = EggSale::pending()->with('enteredBy')->orderBy('date')->orderBy('id')->get();

        return view('approvals.index', compact('buildingRows', 'sales'));
    }

    public function approve(Request $request, string $type, int $id)
    {
        $record = $this->findPending($type, $id);

        $record->forceFill([
            'approval_status' => 'approved',
            'approved_by'     => $request->user()->id,
            'approved_at'     => now(),
            'review_note'     => null,
        ])->save();

        // An approved building row now counts: recompute that day's farm total.
        if ($record instanceof BuildingDaily) {
            (new ProductionRollupService)->rollupDate($record->date);
        }

        $this->audit('approve', $record, null);

        return back()->with('success', $this->label($record) . ' approved.');
    }

    public function reject(Request $request, string $type, int $id)
    {
        $validated = $request->validate(
            ['review_note' => 'required|string|max:1000'],
            ['review_note.required' => 'Write a note so the staff member knows what to fix.']
        );
        $record = $this->findPending($type, $id);

        $record->forceFill([
            'approval_status' => 'rejected',
            'approved_by'     => $request->user()->id, // the reviewer
            'approved_at'     => now(),
            'review_note'     => $validated['review_note'],
        ])->save();

        $this->audit('reject', $record, $validated['review_note']);

        return back()->with('success', $this->label($record) . ' rejected and returned to staff.');
    }

    private function findPending(string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]::pending()->findOrFail($id);
    }

    private function label(Model $record): string
    {
        return $record instanceof BuildingDaily
            ? ($record->henBatch?->display_label ?? 'Building') . ' entry for ' . $record->date->toDateString()
            : "Sale of {$record->quantity} {$record->egg_size} on " . $record->date->toDateString();
    }

    private function audit(string $action, Model $record, ?string $note): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => class_basename($record),
            'model_id'   => $record->id,
            'details'    => array_filter([
                'record'     => $this->label($record),
                'entered_by' => $record instanceof BuildingDaily ? $record->user_id : $record->entered_by,
                'note'       => $note,
            ], fn ($v) => $v !== null),
        ]);
    }
}
