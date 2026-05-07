<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Egg Production</h2>
            <a href="{{ route('productions.create') }}" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-md">+ Add Record</a>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Flock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Eggs</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cracked</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Good Eggs</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($productions as $record)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $record->date->format('M d, Y') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <a href="{{ route('flocks.show', $record->flock) }}" class="text-green-600 hover:underline">{{ $record->flock->name }}</a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ number_format($record->total_eggs) }}</td>
                                <td class="px-6 py-4 text-sm text-red-600">{{ number_format($record->cracked_eggs) }}</td>
                                <td class="px-6 py-4 text-sm font-semibold text-green-700">{{ number_format($record->good_eggs) }}</td>
                                <td class="px-6 py-4 text-right text-sm space-x-3">
                                    <a href="{{ route('productions.edit', $record) }}" class="text-blue-600 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('productions.destroy', $record) }}" class="inline" onsubmit="return confirm('Delete this record?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">No production records yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $productions->links() }}</div>
        </div>
    </div>
</x-app-layout>
