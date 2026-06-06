<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Egg Monitor — SPC Farm Magalang</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/pwa.js'])
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2E7D32">
</head>
<body class="font-sans antialiased bg-gray-50">

<div class="min-h-screen" x-data="{ sidebarOpen: true }">

    {{-- Fixed Top Header --}}
    <header class="fixed top-0 left-0 right-0 z-30 h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4">
        <div class="flex items-center gap-4">
            {{-- Mobile hamburger --}}
            <button @click="sidebarOpen = !sidebarOpen" class="p-2 hover:bg-gray-100 rounded-lg lg:hidden">
                <svg x-show="sidebarOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <svg x-show="!sidebarOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            {{-- Logo --}}
            <div class="flex items-center gap-2">
                <div class="w-10 h-10 bg-[#4CAF50] rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <ellipse cx="12" cy="13" rx="7" ry="8" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M12 5 C12 5 8 2 8 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                    </svg>
                </div>
                <span class="font-bold text-lg hidden sm:block text-gray-800">Egg Monitor</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            {{-- Bell --}}
            <button class="p-2 hover:bg-gray-100 rounded-lg relative">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </button>

            {{-- User dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center gap-2 hover:bg-gray-100 px-2 py-1 rounded-lg transition-colors">
                    <div class="w-8 h-8 bg-[#4CAF50] rounded-full flex items-center justify-center text-white text-sm font-bold">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <span class="hidden sm:block text-sm font-medium text-gray-700">{{ Auth::user()->name }}</span>
                    <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
                <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-1 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-50 overflow-hidden">
                    <div class="px-3 py-2 text-xs text-gray-400 font-semibold uppercase border-b">
                        {{ ucfirst(Auth::user()->role) }}
                    </div>
                    <a href="{{ route('profile.edit') }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- Fixed Sidebar --}}
    <aside class="fixed left-0 top-16 bottom-0 w-64 bg-white border-r border-gray-200 z-20 transition-transform duration-300"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
        <nav class="p-4 space-y-1">

            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-[#4CAF50] text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Dashboard</span>
            </a>

            <a href="{{ route('productions.index') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('productions.*') ? 'bg-[#4CAF50] text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><ellipse cx="12" cy="13" rx="6" ry="7" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 6 C12 6 9 3 9 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/></svg>
                <span>Egg Production</span>
            </a>

            <a href="{{ route('sales.index') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('sales.*') ? 'bg-[#4CAF50] text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Egg Sales</span>
            </a>

            <a href="{{ route('cull.index') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('cull.*') ? 'bg-[#4CAF50] text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Cull Chickens</span>
            </a>

            <a href="{{ route('livestock.index') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('livestock.*') ? 'bg-[#4CAF50] text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Livestock Records</span>
            </a>

            @if(in_array(Auth::user()->role, ['admin', 'manager']))
            <a href="{{ route('reports.index') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('reports.*') ? 'bg-[#4CAF50] text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Reports</span>
            </a>
            @endif

            @if(Auth::user()->role === 'admin')
            <a href="{{ route('users.index') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('users.*') ? 'bg-[#4CAF50] text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Users</span>
            </a>
            @endif

        </nav>

        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-100">
            <div class="flex items-center gap-2 px-2">
                <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                <span class="text-xs text-gray-500">Logged in as <span class="font-semibold text-gray-700">{{ ucfirst(Auth::user()->role) }}</span></span>
            </div>
        </div>
    </aside>

    {{-- Mobile overlay --}}
    <div x-show="sidebarOpen" @click="sidebarOpen = false"
         class="fixed inset-0 bg-black bg-opacity-50 z-10 lg:hidden"></div>

    {{-- Main Content --}}
    <main class="pt-16 transition-all duration-300 lg:pl-64">
        <div class="p-4 lg:p-8">
            {{ $slot }}
        </div>
    </main>

</div>

{{-- ── PWA: Offline Banner ──────────────────────────────────────────────── --}}
<div id="offline-banner" style="display:none"
     class="fixed top-0 left-0 right-0 z-50 bg-orange-500 text-white px-4 py-2 flex items-center justify-between shadow-lg">
    <div class="flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z"/>
            <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.742L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>
        </svg>
        <span class="font-semibold text-sm">You are offline</span>
        <span class="text-xs opacity-90 hidden sm:inline">— Production and Sales entries will be saved locally and synced when reconnected</span>
    </div>
    <span id="offline-pending-count"
          class="bg-white text-orange-600 text-xs font-bold px-2 py-0.5 rounded-full min-w-[1.25rem] text-center"
          style="display:none">0</span>
</div>

{{-- ── PWA: Sync Success Toast ──────────────────────────────────────────── --}}
<div id="sync-toast" style="display:none"
     class="fixed bottom-4 right-4 z-50 bg-green-600 text-white px-4 py-3 rounded-lg shadow-xl flex items-center gap-2">
    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
    </svg>
    <span class="text-sm font-medium">Offline entries synced successfully!</span>
</div>

</body>
</html>
