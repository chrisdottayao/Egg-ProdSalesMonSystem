<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Production Record</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('productions.store') }}">
                    @csrf

                    <div class="mb-4">
                        <x-input-label for="flock_id" value="Flock *" />
                        <select id="flock_id" name="flock_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                            <option value="">— Select Flock —</option>
                            @foreach($flocks as $flock)
                                <option value="{{ $flock->id }}" {{ old('flock_id') == $flock->id ? 'selected' : '' }}>
                                    {{ $flock->name }} ({{ number_format($flock->quantity) }} birds)
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('flock_id')" class="mt-1" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="date" value="Date *" />
                        <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date', date('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('date')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <x-input-label for="total_eggs" value="Total Eggs Collected *" />
                            <x-text-input id="total_eggs" name="total_eggs" type="number" min="0" class="mt-1 block w-full" :value="old('total_eggs')" required />
                            <x-input-error :messages="$errors->get('total_eggs')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="cracked_eggs" value="Cracked Eggs *" />
                            <x-text-input id="cracked_eggs" name="cracked_eggs" type="number" min="0" class="mt-1 block w-full" :value="old('cracked_eggs', 0)" required />
                            <x-input-error :messages="$errors->get('cracked_eggs')" class="mt-1" />
                        </div>
                    </div>

                    <div class="mb-6">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Save Record</x-primary-button>
                        <a href="{{ route('productions.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
