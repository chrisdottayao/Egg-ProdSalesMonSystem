// Egg Monitor — Service Worker
// Handles app-shell precaching, offline navigation fallback, POST queueing
// for offline-writable forms, and background sync.

const CACHE = 'egg-monitor-v2';
const OFFLINE_DB = 'EggMonitorSW';

// Static shell URLs — always the same path, safe to hardcode.
const SHELL_URLS = [
    '/offline',
    '/manifest.json',
    '/images/icon-192.png',
    '/images/icon-512.png',
];

// The single place offline-writable route paths live. Add a new form here
// (e.g. '/expenses': 'expenses') and every consumer below — the POST
// interceptor, the sync-tag dispatcher, and pwa.js's client-side replay —
// picks it up automatically. No other literal '/production'/'/sales'
// strings should exist in this file.
const OFFLINE_WRITABLE_ROUTES = {
    '/production': 'production',
    '/sales':      'sales',
    // '/expenses': 'expenses',  // add here once that form exists
};

function isOfflineWritable(pathname) {
    return pathname in OFFLINE_WRITABLE_ROUTES;
}
function offlineWriteType(pathname) {
    return OFFLINE_WRITABLE_ROUTES[pathname];
}

// ── Lifecycle ──────────────────────────────────────────────────────────────

self.addEventListener('install', event => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE);
            await cache.addAll(SHELL_URLS);

            // Vite fingerprints built asset filenames per build, so resolve
            // them from the build manifest at cache time rather than
            // hardcoding names that would go stale on the next deploy.
            try {
                const manifestRes = await fetch('/build/manifest.json');
                const manifest    = await manifestRes.json();
                const assetUrls   = Object.values(manifest)
                    .filter(entry => entry.isEntry && entry.file)
                    .map(entry => '/build/' + entry.file);

                if (assetUrls.length) {
                    await cache.addAll(assetUrls);
                }
            } catch {
                // Build manifest unreachable (e.g. local dev server) — the
                // shell still works without precached hashed assets since
                // the /offline fallback itself doesn't depend on them.
            }

            self.skipWaiting();
        })()
    );
});
self.addEventListener('activate', event => {
    event.waitUntil(
        (async () => {
            // Drop any caches from a previous SW version.
            const keys = await caches.keys();
            await Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)));
            await self.clients.claim();
        })()
    );
});

// ── Fetch interception ────────────────────────────────────────────────────

self.addEventListener('fetch', event => {
    const { request } = event;

    // Offline-write queueing — unchanged behavior, now driven by the
    // OFFLINE_WRITABLE_ROUTES map instead of hardcoded path checks.
    if (request.method === 'POST') {
        const url = new URL(request.url);
        if (isOfflineWritable(url.pathname)) {
            event.respondWith(handlePost(event.request, offlineWriteType(url.pathname)));
        }
        return;
    }

    // Navigation requests (loading a page, not a POST) — network first, so
    // logged-in users always see live data when online, falling back to a
    // cached copy of that exact page, and finally to /offline when neither
    // is available. Without this, any GET made with no network hits the
    // browser's native offline error instead of anything this app controls.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const cached = await caches.match(request);
                return cached || caches.match('/offline');
            })
        );
    }
});

async function handlePost(request, type) {
    try {
        const response = await fetch(request.clone());
        // Notify page that sync succeeded (if it was a background replay)
        notifyClients({ type: 'SYNC_SUCCESS', entryType: type });
        return response;
    } catch {
        // Network unavailable — queue the request body
        const body = await request.text();
        await enqueue({ type, url: request.url, body, timestamp: Date.now() });
        notifyClients({ type: 'OFFLINE_QUEUED', entryType: type });

        if ('sync' in self.registration) {
            self.registration.sync.register(type + 'Sync').catch(() => {});
        }

        return new Response(
            JSON.stringify({ offline: true, message: 'Saved offline. Will sync when reconnected.' }),
            { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
    }
}

// ── Background Sync ───────────────────────────────────────────────────────

self.addEventListener('sync', event => {
    const type = Object.values(OFFLINE_WRITABLE_ROUTES).find(t => event.tag === t + 'Sync');
    if (type) event.waitUntil(replayQueue(type));
});

async function replayQueue(type) {
    const db      = await openDB();
    const entries = await dbGetAll(db, type);
    for (const entry of entries) {
        try {
            // Fetch a fresh CSRF token before replaying
            const tokenRes = await fetch('/api/csrf-token');
            const { token } = await tokenRes.json();
            const body = entry.body.replace(/_token=[^&]+/, '_token=' + encodeURIComponent(token));

            const res = await fetch(entry.url, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });

            if (res.ok || res.redirected) {
                await dbDelete(db, entry.id);
                notifyClients({ type: 'ENTRY_SYNCED', entryType: type });
            } else if (res.status === 422 || res.status === 419) {
                // Validation / CSRF conflict — mark as flagged locally
                await dbUpdate(db, entry.id, { ...entry, status: 'conflicted' });
                notifyClients({ type: 'SYNC_CONFLICT', entryType: type, entry });

                // Also report server-side so Admin/Manager can see it from any device
                try {
                    await fetch('/api/sync-conflict', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ entry_type: type, status: res.status, payload: entry.body }),
                    });
                } catch { /* best-effort — local flag still stands even if this fails */ }
            }
        } catch {
            break; // Still offline — leave in queue
        }
    }
    notifyClients({ type: 'QUEUE_UPDATED' });
}

// ── Client messaging ──────────────────────────────────────────────────────

async function notifyClients(msg) {
    const all = await self.clients.matchAll({ type: 'window' });
    all.forEach(c => c.postMessage(msg));
}

self.addEventListener('message', event => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

// ── IndexedDB helpers (SW context) ────────────────────────────────────────

function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(OFFLINE_DB, 1);
        req.onupgradeneeded = e => {
            e.target.result.createObjectStore('queue', { keyPath: 'id', autoIncrement: true });
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror   = () => reject(req.error);
    });
}

function enqueue(data) {
    return new Promise(async (resolve, reject) => {
        const db = await openDB();
        const tx = db.transaction('queue', 'readwrite');
        tx.objectStore('queue').add({ ...data, status: 'pending' });
        tx.oncomplete = resolve;
        tx.onerror    = reject;
    });
}

function dbGetAll(db, type) {
    return new Promise((resolve, reject) => {
        const req = db.transaction('queue', 'readonly').objectStore('queue').getAll();
        req.onsuccess = () => resolve(req.result.filter(e => e.type === type && e.status === 'pending'));
        req.onerror   = reject;
    });
}

function dbDelete(db, id) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('queue', 'readwrite');
        tx.objectStore('queue').delete(id);
        tx.oncomplete = resolve;
        tx.onerror    = reject;
    });
}

function dbUpdate(db, id, data) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('queue', 'readwrite');
        tx.objectStore('queue').put(data);
        tx.oncomplete = resolve;
        tx.onerror    = reject;
    });
}
