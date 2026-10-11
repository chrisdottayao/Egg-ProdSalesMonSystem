<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Settings</h1>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Phase 3 item 5 (S1, S2): thresholds and alert rules, each with its basis --}}
    <div class="bg-white rounded-lg shadow-md p-6" id="thresholds">
        <h2 class="text-lg font-bold text-gray-800 mb-1">Thresholds &amp; Alert Rules</h2>
        <p class="text-sm text-gray-500 mb-4">
            The numbers the system uses to label buildings and raise alerts. "Basis" says where each number comes from;
            keep it honest. Watch is the range between Cull-consideration and Healthy. Every change is recorded in the Audit Log.
        </p>
        @if($errors->has('thresholds.*') || $errors->has('threshold_basis.*'))
            <div class="bg-red-50 border-l-4 border-red-500 px-4 py-3 rounded-lg text-sm text-red-800 mb-4">
                <ul class="list-disc list-inside">@foreach($errors->get('thresholds.*') as $msgs)@foreach($msgs as $m)<li>{{ $m }}</li>@endforeach @endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.thresholds.update') }}" class="space-y-6">
            @csrf
            @method('PATCH')

            @foreach($thresholds as $group => $items)
                <fieldset>
                    <legend class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">{{ $group }}</legend>
                    <div class="divide-y border border-gray-200 rounded-lg">
                        @foreach($items as $t)
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 p-3 items-start">
                                <label for="t_{{ $t['key'] }}" class="md:col-span-4 text-sm font-semibold text-gray-700">
                                    {{ $t['label'] }}
                                    <span class="block text-xs font-normal text-gray-500">Default {{ rtrim(rtrim(number_format($t['default'], 2, '.', ''), '0'), '.') }} {{ $t['unit'] }}</span>
                                </label>
                                <div class="md:col-span-2 flex items-center gap-1">
                                    <input type="number" step="any" id="t_{{ $t['key'] }}" name="thresholds[{{ $t['key'] }}]"
                                           value="{{ old("thresholds.{$t['key']}", rtrim(rtrim(number_format($t['value'], 2, '.', ''), '0'), '.')) }}"
                                           class="w-24 px-2 py-1.5 border {{ $errors->has("thresholds.{$t['key']}") ? 'border-red-400' : 'border-gray-300' }} rounded text-sm" required />
                                    <span class="text-xs text-gray-500">{{ $t['unit'] === '%' ? '%' : '' }}</span>
                                </div>
                                <div class="md:col-span-6">
                                    <label for="b_{{ $t['key'] }}" class="sr-only">Basis for {{ $t['label'] }}</label>
                                    <textarea id="b_{{ $t['key'] }}" name="threshold_basis[{{ $t['key'] }}]" rows="2" maxlength="500" required
                                              class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs text-gray-700" placeholder="Basis">{{ old("threshold_basis.{$t['key']}", $t['basis']) }}</textarea>
                                    @error("thresholds.{$t['key']}")<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">
                Save Thresholds
            </button>
        </form>
    </div>

    {{-- S12: egg prices per size --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-2">Egg Prices per Size (₱ per piece)</h2>
        <p class="text-sm text-gray-500 mb-4">
            The Sales form fills in the price from this list when a size is chosen (it can still be changed for a
            special price), and a sales CSV row with no price uses it too. Changing a price only affects new sales;
            sales already recorded keep the price they were saved with.
        </p>

        <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach($eggPrices as $size => $price)
                    @php $field = \App\Support\EggPrices::fieldKey($size); @endphp
                    <div>
                        <label for="egg_price_{{ $field }}" class="block text-sm font-semibold text-gray-700 mb-1">{{ $size }}</label>
                        <input type="number" id="egg_price_{{ $field }}" name="egg_prices[{{ $field }}]" value="{{ old("egg_prices.{$field}", number_format($price, 2, '.', '')) }}" step="0.01" min="0"
                            class="w-full px-3 py-2 border {{ $errors->has("egg_prices.{$field}") ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                        @error("egg_prices.{$field}")<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>

            <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">
                Save Egg Prices
            </button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-2">Utility Expense Estimate Defaults</h2>
        <p class="text-sm text-gray-500 mb-4">
            These are market-reference placeholders, not confirmed farm figures — feed comes through the owner's
            partner (kasosyo) and neither managers nor the production manager currently know the real price, and
            restocking cost is an owner-only figure. Edit here once the owner confirms a real number; already-saved
            expense entries keep whatever was recorded at the time and are not rewritten. Only new entries default
            to the updated value.
        </p>

        <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Feed Price per Bag Estimate (₱)</label>
                    <input type="number" name="feed_price_per_bag_estimate" value="{{ old('feed_price_per_bag_estimate', $values['feed_price_per_bag_estimate']) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    <p class="text-xs text-gray-500 mt-1">Prefills the "Price per Bag" field on new feed expense entries.</p>
                    @error('feed_price_per_bag_estimate')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Pullet Cost per Head Estimate (₱)</label>
                    <input type="number" name="pullet_cost_per_head_estimate" value="{{ old('pullet_cost_per_head_estimate', $values['pullet_cost_per_head_estimate']) }}" step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    <p class="text-xs text-gray-500 mt-1">The farm rears its own birds from chicks, so this stands in for chick + rearing feed cost, not a finished-pullet price.</p>
                    @error('pullet_cost_per_head_estimate')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">
                Save Settings
            </button>
        </form>
    </div>
</div>
</x-app-layout>
