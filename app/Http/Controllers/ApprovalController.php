<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ApprovalQueue;
use App\Support\RecordApprover;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase 3 item 1 (S11, S5), extended to every record type in Phase 3B
 * item 3: admin/manager review of staff entries. A pending row counts
 * nowhere until approved; a rejected row stays visible to staff with the
 * reviewer's note and can be corrected and resubmitted.
 */
class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        [$date, $userId] = $this->filters($request);

        $items = ApprovalQueue::pending($date, $userId);
        $users = User::whereIn('id', ApprovalQueue::pendingUserIds())->orderBy('name')->get(['id', 'name']);

        return view('approvals.index', compact('items', 'users', 'date', 'userId'));
    }

    public function approve(Request $request, string $type, int $id)
    {
        $record = $this->findPending($type, $id);
        RecordApprover::approve($record, $request->user());

        return back()->with('success', ApprovalQueue::label($record) . ' approved.');
    }

    /** "Approve all" for the selected date and/or person. */
    public function approveAll(Request $request)
    {
        [$date, $userId] = $this->filters($request);
        if (! $date && ! $userId) {
            return back()->withErrors(['bulk' => 'Pick a date or a person first, then approve all of their entries.']);
        }

        $records = ApprovalQueue::pending($date, $userId)->pluck('record');
        $count   = RecordApprover::approveMany($records, $request->user());

        return back()->with('success', "Approved {$count} " . ($count === 1 ? 'entry' : 'entries') . '.');
    }

    public function reject(Request $request, string $type, int $id)
    {
        $validated = $request->validate(
            ['review_note' => 'required|string|max:1000'],
            ['review_note.required' => 'Write a note so the staff member knows what to fix.']
        );
        $record = $this->findPending($type, $id);
        RecordApprover::reject($record, $request->user(), $validated['review_note']);

        return back()->with('success', ApprovalQueue::label($record) . ' rejected and returned to staff.');
    }

    /** @return array{0: ?string, 1: ?int} */
    private function filters(Request $request): array
    {
        $date = null;
        if ($request->filled('date')) {
            try {
                $date = Carbon::parse($request->input('date'))->toDateString();
            } catch (\Throwable) {
                $date = null;
            }
        }
        $userId = $request->filled('user_id') && ctype_digit((string) $request->input('user_id')) ? (int) $request->input('user_id') : null;

        return [$date, $userId];
    }

    private function findPending(string $type, int $id): Model
    {
        $class = ApprovalQueue::modelFor($type);
        abort_unless($class, 404);

        return $class::pending()->findOrFail($id);
    }
}
