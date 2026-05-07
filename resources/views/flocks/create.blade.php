<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Flock</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('flocks.store') }}">
                    @csrf

                    <div class="mb-4">
                        <x-input-label for="name" value="Flock Name *" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="breed" value="Breed" />
                        <x-text-input id="breed" name="breed" type="text" class="mt-1 block w-full" :value="old('breed')" placeholder="e.g. Lohmann Brown, White Leghorn" />
                        <x-input-error :messages="$errors->get('breed')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <x-input-label for="quantity" value="Number of Birds *" />
                            <x-text-input id="quantity" name="quantity" type="number" min="1" class="mt-1 block w-full" :value="old('quantity')" required />
                            <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="acquisition_date" value="Acquisition Date *" />
                            <x-text-input id="acquisition_date" name="acquisition_date" type="date" class="mt-1 block w-full" :value="old('acquisition_date')" required />
                            <x-input-error :messages="$errors->get('acquisition_date')" class="mt-1" />
                        </div>
                    </div>

                    <div class="mb-4">
                        <x-input-label for="status" value="Status *" />
                        <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="sold" {{ old('status') === 'sold' ? 'selected' : '' }}>Sold</option>
                            <option value="retired" {{ old('status') === 'retired' ? 'selected' : '' }}>Retired</option>
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-1" />
                    </div>

                    <div class="mb-6">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Save Flock</x-primary-button>
                        <a href="{{ route('flocks.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
