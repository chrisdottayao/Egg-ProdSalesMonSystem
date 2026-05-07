<x-app-layout>
<div class="space-y-6" x-data="{ editUser: null, showCreate: false }">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
        <button @click="showCreate = !showCreate" class="bg-[#4CAF50] text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors text-sm font-medium">
            + Add User
        </button>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 px-4 py-3 rounded-lg text-sm text-red-800">{{ session('error') }}</div>
    @endif

    {{-- Create User Form --}}
    <div x-show="showCreate" x-cloak class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-base font-bold text-gray-800 mb-4">Create New User</h2>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Juan dela Cruz"
                        class="w-full px-3 py-2 border {{ $errors->has('name') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="user@spcfarm.com"
                        class="w-full px-3 py-2 border {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('email')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" placeholder="Min. 8 characters"
                        class="w-full px-3 py-2 border {{ $errors->has('password') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('password')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                    <input type="password" name="password_confirmation" placeholder="Repeat password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                        @foreach(['staff','manager','admin'] as $r)
                            <option value="{{ $r }}" {{ old('role', 'staff') === $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button type="submit" class="bg-[#4CAF50] text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors font-medium">Create User</button>
                <button type="button" @click="showCreate = false" class="text-sm text-gray-600 hover:underline">Cancel</button>
            </div>
        </form>
    </div>

    {{-- Users Table --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Name</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Email</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Role</th>
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Joined</th>
                        <th class="text-right py-3 text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-b last:border-0 hover:bg-gray-50" x-show="editUser !== {{ $user->id }}">
                            <td class="py-3 text-sm font-medium">
                                {{ $user->name }}
                                @if($user->id === auth()->id())
                                    <span class="text-xs text-gray-400 ml-1">(you)</span>
                                @endif
                            </td>
                            <td class="py-3 text-sm text-gray-600">{{ $user->email }}</td>
                            <td class="py-3 text-sm">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $user->role === 'admin' ? 'bg-red-100 text-red-800' : ($user->role === 'manager' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700') }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td class="py-3 text-sm text-gray-500">{{ $user->created_at->format('Y-m-d') }}</td>
                            <td class="text-right py-3 text-sm space-x-2">
                                <button @click="editUser = {{ $user->id }}" class="text-blue-600 hover:underline">Edit</button>
                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete {{ $user->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        {{-- Inline Edit Row --}}
                        <tr class="border-b bg-blue-50" x-show="editUser === {{ $user->id }}" x-cloak>
                            <td colspan="5" class="py-4 px-2">
                                <form method="POST" action="{{ route('users.update', $user) }}" class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                                    @csrf @method('PATCH')
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Name</label>
                                        <input type="text" name="name" value="{{ $user->name }}"
                                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" required />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Email</label>
                                        <input type="email" name="email" value="{{ $user->email }}"
                                            class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]" required />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Role</label>
                                        <select name="role" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-[#4CAF50]">
                                            @foreach(['staff','manager','admin'] as $r)
                                                <option value="{{ $r }}" {{ $user->role === $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="bg-[#4CAF50] text-white px-3 py-1.5 rounded text-sm font-medium hover:bg-green-600">Save</button>
                                        <button type="button" @click="editUser = null" class="text-gray-600 px-3 py-1.5 rounded text-sm border border-gray-300 hover:bg-gray-100">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</div>
</x-app-layout>
