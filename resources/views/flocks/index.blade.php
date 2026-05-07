<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Flocks</h2>
            <a href="{{ route('flocks.create') }}" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">+ Add Flock</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-100 text-green-800 px-4 py-3 rounded-md text-sm">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Breed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acquired</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($flocks as $flock)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    <a href="{{ route('flocks.show', $flock) }}" class="text-green-600 hover:underline">{{ $flock->name }}</a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $flock->breed ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ number_format($flock->quantity) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $flock->acquisition_date->format('M d, Y') }}</td>
                                <td class="px-6 py-4">
                                    @php
                                        $badge = match($flock->status) {
                                            'active'  => 'bg-green-100 text-green-800',
                                            'sold'    => 'bg-blue-100 text-blue-800',
                                            'retired' => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $badge }}">{{ ucfirst($flock->status) }}</span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm space-x-3">
                                    <a href="{{ route('flocks.edit', $flock) }}" class="text-blue-600 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('flocks.destroy', $flock) }}" class="inline" onsubmit="return confirm('Delete this flock?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">No flocks recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $flocks->links() }}</div>
        </div>
    </div>
</x-app-layout>
