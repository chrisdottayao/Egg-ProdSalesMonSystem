<x-app-layout>
<div class="space-y-6" x-data="{ editUser: null, showCreate: false }">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Users Management</h1>
        <button @click="showCreate = true"
            class="flex items-center gap-2 bg-[#4CAF50] text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add User
        </button>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 px-4 py-3 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 px-4 py-3 rounded-lg text-sm text-red-800">{{ session('error') }}</div>
    @endif

    {{-- Add User Modal --}}
    <div x-show="showCreate" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-lg font-bold mb-4">Add New User</h3>
            <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Enter name"
                        class="w-full px-3 py-2 border {{ $errors->has('name') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('name')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="Enter email"
                        class="w-full px-3 py-2 border {{ $errors->has('email') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('email')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]">
                        @foreach(['Admin','Manager','Staff'] as $r)
                            <option value="{{ strtolower($r) }}" {{ old('role', 'staff') === strtolower($r) ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" placeholder="Enter password"
                        class="w-full px-3 py-2 border {{ $errors->has('password') ? 'border-red-400' : 'border-gray-300' }} rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50]" required />
                    @error('password')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="button" @click="showCreate = false"
                        class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit"
                        class="flex-1 bg-[#4CAF50] text-white py-2 rounded-lg hover:bg-green-600">Save</button>
                </div>
            </form>
        </div>
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
                        <th class="text-left py-3 text-sm font-semibold text-gray-700">Status</th>
                        <th class="text-center py-3 text-sm font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-b last:border-0 hover:bg-gray-50" x-show="editUser !== {{ $user->id }}">
                            <td class="py-3 text-sm font-semibold">
                                {{ $user->name }}
                                @if($user->id === auth()->id())
                                    <span class="text-xs text-gray-400 ml-1">(you)</span>
                                @endif
                            </td>
                            <td class="py-3 text-sm">{{ $user->email }}</td>
                            <td class="py-3 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-semibold
                                    {{ $user->role === 'admin' ? 'bg-purple-100 text-purple-700' : ($user->role === 'manager' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td class="py-3 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-semibold bg-green-100 text-green-700">Active</span>
                            </td>
                            <td class="py-3 text-sm">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="editUser = {{ $user->id }}"
                                        class="p-2 hover:bg-gray-100 rounded transition-colors" title="Edit">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete {{ $user->name }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="p-2 hover:bg-gray-100 rounded transition-colors" title="Deactivate">
                                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
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

        <p class="text-sm text-gray-600 mt-4 italic">
            Note: Staff and Manager accounts can also log in via Google or Facebook.
        </p>

        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</div>
</x-app-layout>
