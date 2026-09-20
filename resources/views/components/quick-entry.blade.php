{{--
    Floating Quick Entry — minimal production/sales capture reachable from
    any page (including the zero-DB /offline fallback), so a user who can't
    reach the full Production or Sales page can still record something.
    Renders with no PHP data dependency (no Auth::user(), no DB) so it's safe
    to include from an unauthenticated, uncached-data context.
--}}
<div x-data="quickEntry()" x-init="init()">
    <button type="button" @click="open = true"
            class="fixed bottom-6 right-4 z-40 w-14 h-14 rounded-full bg-[#4CAF50] text-white shadow-lg flex items-center justify-center hover:bg-green-600 transition-colors"
            title="Quick Entry" aria-label="Quick Entry">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    </button>

    <div x-show="open" x-cloak style="display:none" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @keydown.escape.window="open = false">
        <div class="bg-white rounded-lg p-6 max-w-md w-full shadow-xl" @click.outside="open = false">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">Quick Entry</h3>
                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex gap-2 mb-4">
                <button type="button" @click="tab = 'production'"
                        :class="tab === 'production' ? 'bg-[#4CAF50] text-white' : 'bg-gray-100 text-gray-700'"
                        class="flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-colors">Production</button>
                <button type="button" @click="tab = 'sales'"
                        :class="tab === 'sales' ? 'bg-[#4CAF50] text-white' : 'bg-gray-100 text-gray-700'"
                        class="flex-1 px-3 py-2 rounded-lg text-sm font-medium transition-colors">Sales</button>
            </div>

            <form @submit.prevent="submit()" class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Date</label>
                    <input type="date" x-model="form.date" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                </div>

                <template x-if="tab === 'production'">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Eggs Collected</label>
                            <input type="number" x-model="form.eggs_collected" min="0" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Active Hens</label>
                            <input type="number" x-model="form.active_hens" min="1" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Mortality</label>
                            <input type="number" x-model="form.mortality" min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                        </div>
                    </div>
                </template>

                <template x-if="tab === 'sales'">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Egg Size</label>
                            <select x-model="form.egg_size" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                                <option value="">Select…</option>
                                <option>Peewee</option><option>Small</option><option>Medium</option>
                                <option>Large</option><option>XL</option><option>Jumbo</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Quantity</label>
                            <input type="number" x-model="form.quantity" min="1" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Price per Unit (₱)</label>
                            <input type="number" x-model="form.price_per_unit" step="0.01" min="0" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" />
                        </div>
                    </div>
                </template>

                <p x-show="status" x-text="status" :class="statusClass" class="text-sm"></p>

                <div class="flex gap-2 pt-1">
                    <button type="button" @click="open = false" class="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-200">Cancel</button>
                    <button type="submit" :disabled="submitting"
                        class="flex-1 bg-[#4CAF50] text-white py-2 rounded-lg text-sm font-medium hover:bg-green-600 disabled:opacity-60">
                        <span x-text="submitting ? 'Saving…' : 'Save'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function quickEntry() {
    return {
        open: false,
        tab: 'production',
        submitting: false,
        status: '',
        statusClass: '',
        form: {
            date: new Date().toISOString().slice(0, 10),
            eggs_collected: '', active_hens: '', mortality: 0,
            egg_size: '', quantity: '', price_per_unit: '',
        },

        init() {
            // Auto-open when the user landed on /offline while genuinely
            // offline — that page exists because their intended page
            // couldn't load, so skip the extra tap to reach Quick Entry.
            if (!navigator.onLine && window.location.pathname === '/offline') {
                this.open = true;
            }
        },

        resetForm() {
            this.form = {
                date: new Date().toISOString().slice(0, 10),
                eggs_collected: '', active_hens: '', mortality: 0,
                egg_size: '', quantity: '', price_per_unit: '',
            };
        },

        async submit() {
            this.submitting = true;
            this.status = '';

            const type = this.tab;
            const url  = type === 'production' ? '/production' : '/sales';
            const data = type === 'production'
                ? {
                    date: this.form.date,
                    eggs_collected: this.form.eggs_collected,
                    active_hens: this.form.active_hens,
                    mortality: this.form.mortality || 0,
                }
                : {
                    date: this.form.date,
                    egg_size: this.form.egg_size,
                    quantity: this.form.quantity,
                    price_per_unit: this.form.price_per_unit,
                };

            if (!navigator.onLine) {
                await this.saveOffline(type, data);
                return;
            }

            try {
                const tokenEl = document.querySelector('meta[name="csrf-token"]');
                const params  = new URLSearchParams(data);
                params.set('_token', tokenEl ? tokenEl.getAttribute('content') : '');

                const res = await fetch(url, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    params.toString(),
                });

                if (res.redirected || (res.ok && res.status !== 200)) {
                    this.onSaved('Saved.', 'text-green-600');
                    return;
                }

                // A plain 200 with no redirect is the service worker's own
                // "queued offline" response (see public/sw.js handlePost) —
                // navigator.onLine can be true while the actual request
                // still fails, so this still counts as a successful queue.
                const body = await res.clone().json().catch(() => null);
                if (body?.offline) {
                    this.onSaved('Saved offline — will sync automatically.', 'text-orange-600');
                    if (window.updatePendingCount) window.updatePendingCount();
                    return;
                }

                if (res.ok) {
                    this.onSaved('Saved.', 'text-green-600');
                    return;
                }

                this.status = 'Could not save — check the fields and try again.';
                this.statusClass = 'text-red-600';
                this.submitting = false;
            } catch {
                await this.saveOffline(type, data);
            }
        },

        async saveOffline(type, data) {
            if (window.saveOfflineEntry) {
                await window.saveOfflineEntry(type, data);
            }
            this.onSaved('Saved offline — will sync automatically when you reconnect.', 'text-orange-600');
        },

        onSaved(message, cssClass) {
            this.status = message;
            this.statusClass = cssClass;
            this.submitting = false;
            setTimeout(() => {
                this.open = false;
                this.status = '';
                this.resetForm();
            }, 1200);
        },
    };
}
</script>
