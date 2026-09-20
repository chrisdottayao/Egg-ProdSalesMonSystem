<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SyncConflictController extends Controller
{
    /**
     * Fresh CSRF token for offline background sync replay (public/sw.js's
     * replayQueue() fetches this before resubmitting a queued entry, since
     * the token captured at queue time may have since expired). A plain
     * Closure route here would break route:cache (Fix 1, 3K).
     */
    public function csrfToken(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    /**
     * Record an offline-sync conflict server-side so Admin/Manager can
     * review it via the Audit Assistant, regardless of which device
     * originally queued the entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entry_type' => 'required|string|in:production,sales',
            'status'     => 'required|integer',
            'payload'    => 'nullable|string',
        ]);

        AuditLog::create([
            'user_id'               => auth()->id(),
            'action'                => 'offline_sync_conflict',
            'model_type'            => $validated['entry_type'],
            'model_id'              => null,
            'details'               => [
                'http_status' => $validated['status'],
                'payload'     => $validated['payload'] ?? null,
                'synced_at'   => now()->toIso8601String(),
            ],
            'inconsistency_flagged' => true,
            'inconsistency_rule'    => 'offline_sync_conflict',
            'resolved'              => false,
        ]);

        return response()->json(['logged' => true]);
    }
}