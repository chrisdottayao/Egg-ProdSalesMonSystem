<x-app-layout>
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Settings</h1>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-2">Expense Estimate Defaults</h2>
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
