<x-guest-layout>
<div class="min-h-screen flex items-center justify-center bg-[#f0faf0] px-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full text-center space-y-4">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-yellow-100 rounded-full">
            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h1 class="text-xl font-bold text-gray-800">Waiting for admin approval</h1>
        <p class="text-sm text-gray-600">Your account was created, but an administrator must approve it before you can use the system. The admin has been notified. Try signing in again once you've been approved.</p>
        <a href="{{ route('login') }}" class="inline-block bg-[#4CAF50] text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-green-600">Back to login</a>
    </div>
</div>
</x-guest-layout>
