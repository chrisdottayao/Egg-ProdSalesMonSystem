<?php

namespace App\Http\Controllers;

class OfflineController extends Controller
{
    /**
     * PWA offline fallback — must render with zero DB access and zero auth
     * dependency, since it may be the only page a client can reach with no
     * network and no live session (see public/sw.js's navigation handler).
     *
     * A plain Closure route here would break route:cache (Fix 1, 3K), so
     * this exists as a controller method purely to be cacheable — the view
     * itself is unchanged from Prototype 3I.
     */
    public function index()
    {
        return view('offline');
    }
}
