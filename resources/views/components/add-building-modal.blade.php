{{-- Phase 3 item 9: Add Building from the dashboard. Posts to the same action
     as Flock Records' form (same validation) and returns to the dashboard.
     Needs an ancestor with x-data { addBuilding }. --}}
<div x-show="addBuilding" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @keydown.escape.window="addBuilding = false">
    <div class="bg-white rounded-lg p-6 max-w-lg w-full shadow-xl" role="dialog" aria-modal="true" aria-labelledby="add-building-title">
        <h3 id="add-building-title" class="text-lg font-bold text-gray-800 mb-1">Add Building</h3>
        <p class="text-sm text-gray-500 mb-4">It appears in Daily Entry immediately; its first day's population starts at the starting hen count.</p>
        <form method="POST" action="{{ route('flock-records.buildings.store') }}" class="space-y-3"
              x-data="{ no: '{{ old('building_no') }}', name: '{{ old('batch_id') }}', touched: {{ old('batch_id') ? 'true' : 'false' }} }">
            @csrf
            <input type="hidden" name="return_to" value="dashboard" />
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="dash_building_no" class="block text-sm font-semibold text-gray-700 mb-1">Building number <span class="text-red-500">*</span></label>
                    <input id="dash_building_no" type="number" name="building_no" min="1" required x-model="no" @input="if (!touched) name = no ? 'Building ' + no : ''"
                           class="w-full px-3 py-2 border {{ $errors->has('building_no') ? 'border-red-400' : 'border-gray-300' }} rounded-lg text-sm" />
                    @error('building_no')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="dash_batch_id" class="block text-sm font-semibold text-gray-700 mb-1">Flock / batch name</label>
                    <input id="dash_batch_id" type="text" name="batch_id" x-model="name" @input="touched = true" placeholder="Building N"
                           class="w-full px-3 py-2 border {{ $errors->has('batch_id') ? 'border-red-400' : 'border-gray-300' }} rounded-lg text-sm" />
                    @error('batch_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="dash_batch_size" class="block text-sm font-semibold text-gray-700 mb-1">Starting hen count <span class="text-red-500">*</span></label>
                    <input id="dash_batch_size" type="number" name="batch_size" min="1" required value="{{ old('batch_size') }}"
                           class="w-full px-3 py-2 border {{ $errors->has('batch_size') ? 'border-red-400' : 'border-gray-300' }} rounded-lg text-sm" />
                    @error('batch_size')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="dash_placement_date" class="block text-sm font-semibold text-gray-700 mb-1">Placement date <span class="text-red-500">*</span></label>
                    <input id="dash_placement_date" type="date" name="placement_date" required value="{{ old('placement_date', \App\Support\ManilaDate::todayString()) }}" max="{{ \App\Support\ManilaDate::todayString() }}"
                           class="w-full px-3 py-2 border {{ $errors->has('placement_date') ? 'border-red-400' : 'border-gray-300' }} rounded-lg text-sm" />
                    @error('placement_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="dash_breed" class="block text-sm font-semibold text-gray-700 mb-1">Breed</label>
                    <input id="dash_breed" type="text" name="breed" value="{{ old('breed', 'Dekalb White') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                </div>
                <div>
                    <label for="dash_notes" class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
                    <input id="dash_notes" type="text" name="notes" value="{{ old('notes') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                </div>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button type="button" @click="addBuilding = false" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#4CAF50] text-white text-sm font-medium hover:bg-green-600">Add Building</button>
            </div>
        </form>
    </div>
</div>
