<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        /* Sembunyikan scrollbar tapi tetap bisa scroll */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen font-sans antialiased bg-zinc-900 text-zinc-200">

    {{-- NAVBAR mobile only --}}
    <x-nav sticky class="lg:hidden bg-zinc-950 border-b border-zinc-800">
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            <x-theme-toggle class="btn-ghost btn-sm mr-2" />
            <label for="main-drawer" class="lg:hidden me-3">
                <x-icon name="o-bars-3" class="cursor-pointer text-zinc-400" />
            </label>
        </x-slot:actions>
    </x-nav>

    <x-main full-width>
        {{-- SIDEBAR CUSTOM (Nellavio Style) --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="!bg-zinc-950 border-r border-zinc-800 flex flex-col h-screen overflow-hidden">

            {{-- 1. BRAND LOGO --}}
            <div class="px-6 py-6 shrink-0">
                <x-app-brand class="scale-110 origin-left" />
            </div>

            {{-- 2. SEARCH BAR (Mungil ala Nellavio) --}}
            <div class="px-4 mb-6 shrink-0">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-zinc-500 group-focus-within:text-sky-500">
                        <x-icon name="o-magnifying-glass" class="w-4 h-4" />
                    </div>
                    <input type="text" placeholder="Search..." 
                        class="w-full bg-zinc-900 border border-zinc-800 text-[11px] rounded-lg pl-9 pr-3 py-2 text-zinc-300 focus:outline-none focus:border-zinc-700 transition-all placeholder:text-zinc-600">
                </div>
            </div>

            {{-- 3. MENU AREA (Scrollable) --}}
            <div class="flex-1 overflow-y-auto no-scrollbar px-2">
                <x-menu activate-by-route class="gap-1">
                    
                    {{-- GENERAL CATEGORY --}}
                    <div class="px-4 py-2 text-[10px] font-bold text-zinc-600 uppercase tracking-[0.15em]">General</div>
                    <x-menu-item title="Home" icon="o-home" link="/" class="!text-zinc-400 hover:!bg-zinc-900 rounded-lg" />

                    @php 
                        $user = auth()->user();
                        $role = strtolower($user->role ?? ''); 
                    @endphp

                    {{-- ADMIN MANAGEMENT --}}
                    @if($role === 'admin' || (auth()->check() && auth()->user()->email === 'admin@bems.id'))
                        <div class="px-4 py-3 mt-4 text-[10px] font-bold text-zinc-600 uppercase tracking-[0.15em]">Admin Area</div>
                        <x-menu-item title="Manage Clients" icon="o-users" link="{{ route('admin.client') }}" class="hover:!bg-zinc-900 rounded-lg" />
                        <x-menu-item title="Manage Gedung" icon="o-building-office" link="{{ route('admin.gedung') }}" class="hover:!bg-zinc-900 rounded-lg" />
                        <x-menu-item title="Manage Operator" icon="o-cpu-chip" link="{{ route('admin.operator') }}" class="hover:!bg-zinc-900 rounded-lg" />
                        <x-menu-item title="Manage Infrastructure" icon="o-wrench-screwdriver" link="{{ route('admin.maintenance') }}" class="hover:!bg-zinc-900 rounded-lg" />
                        <x-menu-item title="Data Viewer" icon="o-eye" link="{{ route('admin.viewer') }}" class="hover:!bg-zinc-900 rounded-lg" />
                    @endif

                    {{-- CLIENT AREA --}}
                    @if($role === 'client')
                        <div class="px-4 py-3 mt-4 text-[10px] font-bold text-zinc-600 uppercase tracking-[0.15em]">Client Center</div>
                        <x-menu-item title="Building Dashboard" icon="o-squares-2x2" link="/client?tab=infra" />
                        <x-menu-item title="Manage Staff" icon="o-user-group" link="/client?tab=staff" />
                        <x-menu-item title="Account Details" icon="o-identification" link="/client?tab=info" />
                    @endif

                    {{-- STAFF AREA --}}
                    @if(in_array($role, ['operator', 'maintenance', 'viewer']))
                        <div class="px-4 py-3 mt-4 text-[10px] font-bold text-zinc-600 uppercase tracking-[0.15em]">Staff Operations</div>
                        @if($role === 'maintenance')
                            <x-menu-item title="Hardware Setup" icon="o-wrench" link="/maintenance" />
                        @endif
                        @if($role === 'operator')
                            <x-menu-item title="Control Panel" icon="o-bolt" link="/operator" />
                        @endif
                        @if($role === 'viewer')
                            <x-menu-item title="Monitoring Logs" icon="o-chart-bar" link="/viewer" />
                        @endif
                    @endif
                </x-menu>
            </div>

            {{-- 4. BOTTOM PROFILE (Ala Nellavio) --}}
            <div class="p-4 shrink-0 border-t border-zinc-800 bg-zinc-950/50">
                @if($user)
                    <div class="flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                            {{-- Avatar Mungil --}}
                            <div class="w-8 h-8 rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center text-zinc-300 font-bold text-xs">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-[11px] font-bold text-zinc-200 truncate w-24">{{ $user->name }}</p>
                                <p class="text-[10px] text-zinc-500 truncate w-24 lowercase">{{ $role }}</p>
                            </div>
                        </div>
                        
                        {{-- Quick Actions --}}
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <x-button icon="o-power" class="btn-ghost btn-xs text-zinc-500 hover:text-red-500" no-wire-navigate link="/logout" />
                        </div>
                    </div>
                @endif
            </div>

        </x-slot:sidebar>

        {{-- MAIN CONTENT --}}
        <x-slot:content class="!p-6">
            {{ $slot }}
        </x-slot:content>
    </x-main>

    <x-toast />
</body>
</html>