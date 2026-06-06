import { Workbox } from 'workbox-window';

// ── Service Worker registration ───────────────────────────────────────────

if ('serviceWorker' in navigator) {
    const wb = new Workbox('/sw.js');

    // Auto-activate waiting SW
    wb.addEventListener('waiting', () => wb.messageSkipWaiting());

    // Listen for messages from SW
    navigator.serviceWorker.addEventListener('message', ({ data }) => {
        if (!data) return;
        if (data.type === 'OFFLINE_QUEUED') updatePendingCount();
        if (data.type === 'QUEUE_UPDATED')  updatePendingCount();
        if (data.type === 'ENTRY_SYNCED')   updatePendingCount();
        if (data.type === 'SYNC_CONFLICT')  handleConflict(data);
    });

    wb.register().catch(err => console.warn('[PWA] SW registration failed:', err));
}

// ── Online / Offline events ───────────────────────────────────────────────

window.addEventListener('offline', () => {
    showOfflineBanner();
});

window.addEventListener('online', () => {
    hideOfflineBanner();
    showSyncToast();
    updatePendingCount();
});

// Initialise on load
if (!navigator.onLine) showOfflineBanner();
updatePendingCount();

// ── IndexedDB utilities ───────────────────────────────────────────────────

const IDB_NAME    = 'EggMonitorOffline';
const IDB_VERSION = 1;
const IDB_STORE   = 'pendingEntries';

function openClientDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(IDB_NAME, IDB_VERSION);
        req.onupgradeneeded = e => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(IDB_STORE)) {
                db.createObjectStore(IDB_STORE, { keyPath: 'id', autoIncrement: true });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror   = () => reject(req.error);
    });
}

export async function saveOfflineEntry(type, data) {
    const db = await openClientDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(IDB_STORE, 'readwrite');
        tx.objectStore(IDB_STORE).add({
            type,
            data,
            timestamp: new Date().toISOString(),
            status: 'pending',
        });
        tx.oncomplete = () => { updatePendingCount(); resolve(); };
        tx.onerror    = reject;
    });
}

export async function getPendingEntries() {
    const db = await openClientDB();
    return new Promise((resolve, reject) => {
        const req = db.transaction(IDB_STORE, 'readonly').objectStore(IDB_STORE).getAll();
        req.onsuccess = () => resolve(req.result.filter(e => e.status === 'pending'));
        req.onerror   = reject;
    });
}

export async function updatePendingCount() {
    try {
        const entries = await getPendingEntries();
        const count   = entries.length;
        const badge   = document.getElementById('offline-pending-count');
        if (badge) {
            badge.textContent    = count;
            badge.style.display  = count > 0 ? 'inline' : 'none';
        }
        return count;
    } catch { return 0; }
}

// ── Client-side sync (fallback when SW background sync unavailable) ────────

export async function syncOfflineEntries() {
    const entries = await getPendingEntries();
    if (entries.length === 0) return;

    const tokenEl = document.querySelector('meta[name="csrf-token"]');
    const token   = tokenEl ? tokenEl.getAttribute('content') : '';

    const db = await openClientDB();
    for (const entry of entries) {
        const params = new URLSearchParams(entry.data);
        params.set('_token', token);

        const url = entry.type === 'production' ? '/production' : '/sales';
        try {
            const res = await fetch(url, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    params.toString(),
            });

            if (res.ok || res.redirected || res.status === 302) {
                // Mark synced — delete from store
                await new Promise((resolve, reject) => {
                    const tx = db.transaction(IDB_STORE, 'readwrite');
                    tx.objectStore(IDB_STORE).delete(entry.id);
                    tx.oncomplete = resolve;
                    tx.onerror    = reject;
                });
            } else if (res.status === 422) {
                await markConflict(db, entry.id);
            }
        } catch { break; } // Still offline
    }
    updatePendingCount();
}

async function markConflict(db, id) {
    const tx      = db.transaction(IDB_STORE, 'readwrite');
    const store   = tx.objectStore(IDB_STORE);
    const getReq  = store.get(id);
    getReq.onsuccess = () => {
        const rec = getReq.result;
        if (rec) { rec.status = 'conflicted'; store.put(rec); }
    };
}

function handleConflict(data) {
    console.warn('[PWA] Sync conflict detected for entry:', data.entry);
    // Surfaced in the offline pending count — Admin can review
}

// ── UI helpers ────────────────────────────────────────────────────────────

export function showOfflineBanner() {
    const el = document.getElementById('offline-banner');
    if (el) el.style.display = 'flex';
}

export function hideOfflineBanner() {
    const el = document.getElementById('offline-banner');
    if (el) el.style.display = 'none';
}

export function showSyncToast() {
    const el = document.getElementById('sync-toast');
    if (!el) return;
    el.style.display = 'flex';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

// Expose to non-module blade scripts
window.saveOfflineEntry  = saveOfflineEntry;
window.updatePendingCount = updatePendingCount;
window.syncOfflineEntries = syncOfflineEntries;
window.showSyncToast      = showSyncToast;
