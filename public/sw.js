// Egg Monitor — Service Worker
// Handles offline caching, POST queue for production/sales, and background sync

const CACHE = 'egg-monitor-v1';
const OFFLINE_DB = 'EggMonitorSW';

// ── Lifecycle ──────────────────────────────────────────────────────────────

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

// ── Fetch interception ────────────────────────────────────────────────────

self.addEventListener('fetch', event => {
    const { request } = event;
    if (request.method !== 'POST') return;

    const url = new URL(request.url);
    const isProd  = url.pathname === '/production';
    const isSales = url.pathname === '/sales';
    if (!isProd && !isSales) return;

    event.respondWith(handlePost(event.request, isProd ? 'production' : 'sales'));
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
    if (event.tag === 'productionSync') event.waitUntil(replayQueue('production'));
    if (event.tag === 'salesSync')      event.waitUntil(replayQueue('sales'));
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
                // Validation / CSRF conflict — mark as flagged
                await dbUpdate(db, entry.id, { ...entry, status: 'conflicted' });
                notifyClients({ type: 'SYNC_CONFLICT', entryType: type, entry });
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
