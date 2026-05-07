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

            {{-- Divider --}}
            <div class="flex items-center my-5">
                <div class="flex-1 border-t border-gray-200"></div>
                <span class="px-3 text-gray-400 text-sm">or continue with</span>
                <div class="flex-1 border-t border-gray-200"></div>
            </div>

            {{-- Social Login --}}
            <div class="space-y-3">
                <button type="button" class="w-full flex items-center justify-center gap-3 bg-white border border-gray-300 py-2.5 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium text-gray-700">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </button>

                <button type="button" class="w-full flex items-center justify-center gap-3 bg-[#1877F2] text-white py-2.5 rounded-lg hover:bg-[#166FE5] transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Continue with Facebook
                </button>
            </div>

            <div class="mt-5 text-center">
                <a href="{{ route('password.request') }}" class="text-sm text-[#4CAF50] hover:underline">Forgot Password?</a>
            </div>
        </div>

        <p class="mt-5 text-sm text-gray-500 text-center">
            Login with your <span class="font-medium text-gray-600">SPC Farm Magalang</span> account credentials.
        </p>

        <p class="mt-4 text-xs text-gray-400">© {{ date('Y') }} SPC Farm Magalang</p>
    </div>
</div>
</x-guest-layout>
