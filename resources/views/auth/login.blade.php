<x-guest-layout>
<div class="min-h-screen flex flex-col items-center justify-center bg-[#f0faf0] relative overflow-hidden">

    {{-- Dotted background --}}
    <div class="absolute inset-0" style="background-image: radial-gradient(circle, #86c986 1px, transparent 1px); background-size: 28px 28px; opacity: 0.35;"></div>

    <div class="relative z-10 w-full max-w-md mx-4 flex flex-col items-center">

        {{-- Logo & Title --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-[#4CAF50] rounded-full mb-4 shadow-md">
                <svg class="w-10 h-10" fill="none" stroke="white" viewBox="0 0 24 24">
                    <ellipse cx="12" cy="13" rx="7" ry="8" stroke-width="2"/>
                    <path d="M12 5 C12 5 8 2 8 5" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">Egg Monitoring Dashboard</h1>
            <p class="text-gray-500 mt-1">SPC Farm Magalang</p>
        </div>

        {{-- Login Card --}}
        <div class="bg-white rounded-2xl shadow-lg p-8 w-full">

            <x-auth-session-status class="mb-4" :status="session('status')" />

            @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
                    Invalid email or password. Please try again.
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50] text-sm"
                            placeholder="your@email.com" />
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <input id="password" type="password" name="password" required autocomplete="current-password"
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#4CAF50] text-sm"
                            placeholder="••••••••" />
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-[#4CAF50] focus:ring-[#4CAF50]">
                        Remember me
                    </label>
                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sm text-[#4CAF50] hover:underline">Forgot Password?</a>
                    @endif
                </div>

                <button type="submit"
                    class="w-full bg-[#4CAF50] text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition-colors">
                    Login
                </button>
            </form>
        </div>

        <p class="mt-4 text-xs text-gray-400">© {{ date('Y') }} SPC Farm Magalang</p>
    </div>
</div>
</x-guest-layout>
