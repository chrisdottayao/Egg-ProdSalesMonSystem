<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Offline — Egg Monitor</title>
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2E7D32">
    <link rel="apple-touch-icon" href="/images/icon-192.png">
</head>
<body class="font-sans antialiased bg-gray-50">

<div class="min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full text-center space-y-4">
        <div class="w-16 h-16 mx-auto bg-orange-100 rounded-full flex items-center justify-center">
            <svg class="w-8 h-8 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z"/>
                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.742L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-800">You're offline</h1>
        <p class="text-sm text-gray-500">
            This page isn't available without a connection yet. Once you're back online, reload to pick up where you left off —
            or use Quick Entry below to record a production or sales entry right now; it will sync automatically when you reconnect.
        </p>
        <a href="/dashboard" class="inline-block mt-2 text-sm text-[#4CAF50] hover:underline">Try the dashboard again</a>
    </div>
</div>

{{-- Quick Entry — same component used on every authenticated page; this is
     what makes landing here still useful instead of a dead end. --}}
<x-quick-entry />

{{-- ── PWA: Offline Banner / Sync Toast (same markup pwa.js already targets) ── --}}
<div id="offline-banner" style="display:none"
     class="fixed top-0 left-0 right-0 z-50 bg-orange-500 text-white px-4 py-2 flex items-center justify-between shadow-lg">
    <div class="flex items-center gap-2">
        <span class="font-semibold text-sm">You are offline</span>
    </div>
    <span id="offline-pending-count"
          class="bg-white text-orange-600 text-xs font-bold px-2 py-0.5 rounded-full min-w-[1.25rem] text-center"
          style="display:none">0</span>
</div>

<div id="sync-toast" style="display:none"
     class="fixed bottom-4 right-4 z-50 bg-green-600 text-white px-4 py-3 rounded-lg shadow-xl flex items-center gap-2">
    <span class="text-sm font-medium">Offline entries synced successfully!</span>
</div>

</body>
</html>
