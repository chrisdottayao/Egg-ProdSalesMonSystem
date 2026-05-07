<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $flock->name }}</h2>
            <div class="flex gap-3">
                <a href="{{ route('flocks.edit', $flock) }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-md">Edit</a>
                <a href="{{ route('flocks.index') }}" class="text-sm text-gray-600 hover:underline self-center">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-6">
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Breed</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $flock->breed ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Birds</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ number_format($flock->quantity) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Acquired</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $flock->acquisition_date->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Status</dt>
                        <dd class="mt-1">
                            @php
                                $badge = match($flock->status) {
                                    'active'  => 'bg-green-100 text-green-800',
                                    'sold'    => 'bg-blue-100 text-blue-800',
                                    'retired' => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $badge }}">{{ ucfirst($flock->status) }}</span>
                        </dd>
                    </div>
                </dl>
                @if($flock->notes)
                    <p class="mt-4 text-sm text-gray-600">{{ $flock->notes }}</p>
                @endif
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-sm font-semibold text-gray-700">Recent Production Records</h3>
                    <a href="{{ route('productions.create') }}" class="text-sm text-green-600 hover:underline">+ Add Record</a>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Eggs</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cracked</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Good</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($flock->productions as $record)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $record->date->format('M d, Y') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-900">{{ number_format($record->total_eggs) }}</td>
                                <td class="px-6 py-3 text-sm text-red-600">{{ number_format($record->cracked_eggs) }}</td>
                                <td class="px-6 py-3 text-sm text-green-700 font-medium">{{ number_format($record->good_eggs) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400">No production records yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
