<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Phase 3 item 3 (S24): a manager/admin marks a flagged entry (e.g. sold
 * more than produced) as resolved, with a note. The flagged row keeps its
 * original content (append-only); the resolution is recorded on it and as
 * a new Audit Log row.
 */
class AuditFlagController extends Controller
{
    public function resolve(Request $request, AuditLog $auditLog)
    {
        abort_unless($auditLog->inconsistency_flagged, 404);
        if ($auditLog->resolved) {
            return back()->with('success', 'That flag was already resolved.');
        }

        $validated = $request->validate(
            ['resolution_note' => 'required|string|max:1000'],
            ['resolution_note.required' => 'Write a short note on how the flag was resolved.']
        );

        $auditLog->update([
            'resolved'        => true,
            'resolved_by'     => $request->user()->id,
            'resolved_at'     => now(),
            'resolution_note' => $validated['resolution_note'],
        ]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'resolve_flag',
            'model_type' => $auditLog->model_type,
            'model_id'   => $auditLog->model_id,
            'details'    => ['flag_id' => $auditLog->id, 'rule' => $auditLog->inconsistency_rule, 'note' => $validated['resolution_note']],
        ]);

        return back()->with('success', 'Flag marked as resolved.');
    }
}
