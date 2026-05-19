<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        {{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        [x-cloak] {
            display: none !important;
        }

    </style>
</head>

<body
    x-data="{
        sidebarOpen: true
    }"
    class="min-h-screen bg-zinc-950 text-zinc-200 antialiased overflow-hidden"
>

    @php
        $user = auth()->user();
        $role = trim(strtolower($user->role ?? ''));
    @endphp

    <div class="flex h-screen overflow-hidden">

        {{-- SIDEBAR --}}
        <aside

            class="relative shrink-0 bg-zinc-950 border-r border-zinc-900 flex flex-col transition-all duration-300 ease-[cubic-bezier(0.7,0,0.2,1)]"

            x-bind:class="sidebarOpen ? 'w-[290px]' : 'w-[88px]'"
        >

            {{-- TOP --}}
            <div class="h-20 px-4 border-b border-zinc-900 flex items-center justify-between shrink-0">

                {{-- LOGO --}}
                <div
                    class="flex items-center overflow-hidden transition-all duration-300"
                    x-bind:class="sidebarOpen ? 'gap-3' : 'justify-center w-full'"
                >

                    {{-- ICON --}}
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-500/20 shrink-0">
                        <span class="text-black font-black text-lg">
                            C
                        </span>
                    </div>

                    {{-- TEXT --}}
                    <div
                        x-show="sidebarOpen"
                        x-transition.opacity.duration.200ms
                        x-cloak
                        class="leading-tight"
                    >
                        <h1 class="font-bold text-white text-sm tracking-wide">
                            CHAKA
                        </h1>

                        <p class="text-[11px] text-zinc-500">
                            Telecom Dashboard
                        </p>
                    </div>

                </div>

                {{-- TOGGLE --}}
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    x-show="sidebarOpen"
                    x-transition.opacity
                    class="w-9 h-9 rounded-xl border border-zinc-800 bg-zinc-900/80 hover:bg-zinc-800 transition-all duration-300 flex items-center justify-center text-zinc-400 hover:text-white shrink-0"
                >
                    <x-icon
                        name="o-bars-3"
                        class="w-5 h-5 transition-transform duration-300"
                    />
                </button>

            </div>

            {{-- COLLAPSED TOGGLE --}}
            <div
                x-show="!sidebarOpen"
                x-transition.opacity
                class="absolute top-5 left-1/2 -translate-x-1/2"
            >

                <button
                    @click="sidebarOpen = true"
                    class="w-9 h-9 rounded-xl border border-zinc-800 bg-zinc-900 hover:bg-zinc-800 transition-all flex items-center justify-center text-zinc-400 hover:text-white"
                >
                    <x-icon name="o-bars-3" class="w-5 h-5" />
                </button>

            </div>

            {{-- SEARCH --}}
            <div
                x-show="sidebarOpen"
                x-transition.opacity.duration.200ms
                x-cloak
                class="px-3 py-4 shrink-0"
            >

                <div class="relative group">

                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-zinc-500">
                        <x-icon name="o-magnifying-glass" class="w-4 h-4" />
                    </div>

                    <input
                        type="text"
                        placeholder="Search..."
                        class="w-full bg-zinc-900 border border-zinc-800 text-[11px] rounded-xl pl-9 pr-3 py-2.5 text-zinc-300 focus:outline-none focus:border-zinc-700 transition-all placeholder:text-zinc-600"
                    >

                </div>

            </div>

            {{-- MENU --}}
            <div class="flex-1 overflow-y-auto no-scrollbar px-3 pb-5">

                <div class="space-y-2">

                    {{-- GENERAL --}}
                    <div
                        x-show="sidebarOpen"
                        x-transition.opacity.duration.200ms
                        x-cloak
                        class="px-3 pt-2 pb-1 text-[10px] font-semibold text-zinc-600 uppercase tracking-[0.2em]"
                    >
                        General
                    </div>

                    {{-- HOME --}}
                    <a
                        href="/"
                        class="group relative flex items-center gap-3 min-h-[54px] rounded-2xl px-3 text-zinc-400 hover:bg-zinc-900 hover:text-white transition-all duration-300"
                        x-bind:class="!sidebarOpen ? 'justify-center px-0' : ''"
                    >

                        <x-icon name="o-home" class="w-5 h-5 shrink-0" />

                        <span
                            x-show="sidebarOpen"
                            x-transition.opacity.duration.200ms
                            x-cloak
                            class="text-sm font-medium"
                        >
                            Home
                        </span>

                        {{-- TOOLTIP --}}
                        <div
                            x-show="!sidebarOpen"
                            x-transition.opacity
                            class="absolute left-full ml-3 whitespace-nowrap rounded-xl bg-zinc-800 px-3 py-2 text-xs text-white opacity-0 pointer-events-none group-hover:opacity-100"
                        >
                            Home
                        </div>

                    </a>

                    {{-- ADMIN --}}
                    @if($role === 'admin' || (auth()->check() && auth()->user()->email === 'admin@bems.id'))

                    <div class="space-y-2">

                        {{-- LABEL --}}
                        <div
                            x-show="sidebarOpen"
                            x-transition.opacity
                            class="px-3 pt-5 pb-1 text-[10px] font-semibold text-zinc-600 uppercase tracking-[0.2em]"
                        >
                            Admin Area
                        </div>

                        {{-- 1. MANAGE CLIENT (SINGLE MENU) --}}
                        <a
                            href="{{ route('admin.client') }}"
                            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm
                            text-zinc-400 hover:bg-zinc-900 hover:text-white transition-all"
                        >
                            <x-icon name="o-users" class="w-5 h-5" />
                            <span x-show="sidebarOpen">Manage Client</span>
                        </a>

                        {{-- 2. STAFF DROPDOWN --}}
                        <div
                            x-data="{ openStaff: false }"
                            x-effect="if(!sidebarOpen) openStaff = false"
                            class="space-y-1"
                        >

                            {{-- BUTTON --}}
                            <button
                                @click="openStaff = !openStaff"
                                class="w-full flex items-center justify-between px-4 py-3 rounded-2xl
                                text-zinc-400 hover:bg-zinc-900 hover:text-white transition-all"
                            >

                                <div class="flex items-center gap-3">
                                    <x-icon name="o-users" class="w-5 h-5" />
                                    <span x-show="sidebarOpen">Staff</span>
                                </div>

                                <x-icon
                                    name="o-chevron-down"
                                    class="w-4 h-4 transition-transform duration-300"
                                    x-bind:class="openStaff ? 'rotate-180' : ''"
                                    x-show="sidebarOpen"
                                />

                            </button>

                            {{-- DROPDOWN CONTENT --}}
                            <div
                                x-show="openStaff && sidebarOpen"
                                x-collapse
                                class="pl-6 space-y-1"
                            >

                                {{-- MANAGE MAINTENANCE --}}
                                <a
                                    href="/admin/maintenance"
                                    class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                                    text-zinc-400 hover:bg-zinc-900 hover:text-white transition-all"
                                >
                                    <x-icon name="o-wrench" class="w-4 h-4" />
                                    Manage Maintenance
                                </a>

                                {{-- MANAGE OPERATOR --}}
                                <a
                                    href="{{ route('admin.operator') }}"
                                    class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                                    text-zinc-400 hover:bg-zinc-900 hover:text-white transition-all"
                                >
                                    <x-icon name="o-cpu-chip" class="w-4 h-4" />
                                    Manage Operator
                                </a>

                                {{-- MANAGE VIEWER --}}
                                <a
                                    href="{{ route('admin.viewer') }}"
                                    class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                                    text-zinc-400 hover:bg-zinc-900 hover:text-white transition-all"
                                >
                                    <x-icon name="o-eye" class="w-4 h-4" />
                                    Manage Viewer
                                </a>

                            </div>
                        </div>

                        {{-- 3. MANAGE GEDUNG (SEPARATE / CLEAN) --}}
                        <a
                            href="{{ route('admin.gedung') }}"
                            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm
                            text-zinc-400 hover:bg-zinc-900 hover:text-white transition-all"
                        >
                            <x-icon name="o-building-office" class="w-5 h-5" />
                            <span x-show="sidebarOpen">Manage Gedung</span>
                        </a>

                    </div>

                    @endif

                    @if($role === 'client')

                        <div class="space-y-2">

                            <div x-show="sidebarOpen"
                                class="px-3 pt-5 pb-1 text-[10px] font-semibold text-zinc-600 uppercase">
                                Client Area
                            </div>

                            <a href="/client?tab=infra"
                            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-zinc-400 hover:bg-zinc-900 hover:text-white">
                                <x-icon name="o-building-office" class="w-5 h-5" />
                                <span x-show="sidebarOpen">Gedung</span>
                            </a>

                            <a href="/client?tab=staff"
                            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-zinc-400 hover:bg-zinc-900 hover:text-white">
                                <x-icon name="o-users" class="w-5 h-5" />
                                <span x-show="sidebarOpen">Staff</span>
                            </a>

                            <a href="/client?tab=info"
                            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-zinc-400 hover:bg-zinc-900 hover:text-white">
                                <x-icon name="o-identification" class="w-5 h-5" />
                                <span x-show="sidebarOpen">Info Akun</span>
                            </a>

                        </div>

                    @endif

                    {{-- STAFF --}}
                    @if(in_array($role, ['operator', 'maintenance', 'viewer']))

                        <div
                            x-data="{ openStaff: false }"
                            class="space-y-2"
                        >

                            {{-- BUTTON --}}
                            <button
                                @click="
                                    if(!sidebarOpen){
                                        sidebarOpen = true
                                        openStaff = false
                                    } else {
                                        openStaff = !openStaff
                                    }
                                "
                                class="w-full flex items-center justify-between px-4 py-3 rounded-2xl bg-zinc-900 hover:bg-zinc-800 hover:shadow-lg hover:shadow-green-500/5 transition-all duration-300"
                            >

                                <div
                                    class="flex items-center"
                                    x-bind:class="sidebarOpen ? 'gap-3' : 'w-full justify-center'"
                                >

                                    <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center shrink-0">
                                        <x-icon name="o-cpu-chip" class="w-5 h-5 text-green-400" />
                                    </div>

                                    <div
                                        x-show="sidebarOpen"
                                        x-transition.opacity.duration.200ms
                                        x-cloak
                                        class="text-left"
                                    >

                                        <p class="text-sm font-semibold text-white">
                                            Staff Operations
                                        </p>

                                        <p class="text-[11px] text-zinc-500">
                                            Operator & Monitoring
                                        </p>

                                    </div>

                                </div>

                                <x-icon
                                    name="o-chevron-down"
                                    x-show="sidebarOpen"
                                    x-transition
                                    class="w-4 h-4 text-zinc-500 transition-transform duration-300"
                                    x-bind:class="openStaff ? 'rotate-180' : ''"
                                />

                            </button>

                            {{-- DROPDOWN --}}
                            <div
                                x-show="openStaff"
                                x-collapse
                                class="space-y-1 pl-3"
                            >

                            @if($role === 'maintenance')

                                <div class="space-y-2">

                                    <a href="/maintenance"
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-zinc-900">
                                        <x-icon name="o-home" class="w-5 h-5" />
                                        <span x-show="sidebarOpen">Dashboard</span>
                                    </a>

                                    <a href="/maintenance/register-sensor"
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-zinc-900">
                                        <x-icon name="o-signal" class="w-5 h-5" />
                                        <span x-show="sidebarOpen">Register Sensor</span>
                                    </a>

                                    <a href="/maintenance/akun"
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-zinc-900">
                                        <x-icon name="o-identification" class="w-5 h-5" />
                                        <span x-show="sidebarOpen">Info Akun</span>
                                    </a>

                                </div>

                                @endif

                            @if($role === 'operator')

                                <div class="space-y-2">

                                    <a href="/operator"
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-zinc-900">
                                        <x-icon name="o-home" class="w-5 h-5" />
                                        <span x-show="sidebarOpen">Dashboard</span>
                                    </a>

                                    <a href="/operator/control"
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-zinc-900">
                                        <x-icon name="o-bolt" class="w-5 h-5" />
                                        <span x-show="sidebarOpen">Sensor Control</span>
                                    </a>

                                    <a href="/operator/akun"
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-zinc-900">
                                        <x-icon name="o-identification" class="w-5 h-5" />
                                        <span x-show="sidebarOpen">Info Akun</span>
                                    </a>

                                </div>

                            @endif

                            @if($role === 'viewer')

                                <div class="space-y-2">

                                    <a href="/viewer"
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-zinc-900">
                                        <x-icon name="o-home" class="w-5 h-5" />
                                        <span x-show="sidebarOpen">Dashboard</span>
                                    </a>

                                    <a href="/viewer/sensors"
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-zinc-900">
                                        <x-icon name="o-eye" class="w-5 h-5" />
                                        <span x-show="sidebarOpen">List Sensor</span>
                                    </a>

                                    <a href="/viewer/akun"
                                    class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-zinc-900">
                                        <x-icon name="o-identification" class="w-5 h-5" />
                                        <span x-show="sidebarOpen">Info Akun</span>
                                    </a>

                                </div>

                            @endif

                            </div>

                        </div>

                    @endif

                </div>

            </div>

            {{-- PROFILE --}}
            <div class="p-4 border-t border-zinc-900 bg-zinc-950/80 shrink-0">

                @if($user)

                    <div
                        class="flex items-center"
                        x-bind:class="sidebarOpen ? 'justify-between' : 'justify-center'"
                    >

                        <div class="flex items-center gap-3 overflow-hidden">

                            {{-- AVATAR --}}
                            <div class="w-10 h-10 rounded-2xl bg-zinc-800 border border-zinc-700 flex items-center justify-center text-sm font-bold text-white shrink-0">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>

                            {{-- USER --}}
                            <div
                                x-show="sidebarOpen"
                                x-transition.opacity.duration.200ms
                                x-cloak
                            >

                                <p class="text-sm font-semibold text-white truncate w-28">
                                    {{ $user->name }}
                                </p>

                                <div x-show="sidebarOpen" x-transition>
                                    <p class="text-[11px] text-zinc-500 truncate w-40">
                                        admin@bems.id
                                    </p>
                                </div>

                            </div>

                        </div>

                        {{-- LOGOUT --}}
                        <div
                            x-show="sidebarOpen"
                            x-transition.opacity.duration.200ms
                            x-cloak
                        >

                            <a
                                href="/logout"
                                class="w-9 h-9 rounded-xl hover:bg-zinc-900 flex items-center justify-center text-zinc-500 hover:text-red-500 transition-all"
                            >
                                <x-icon name="o-power" class="w-5 h-5" />
                            </a>

                        </div>

                    </div>

                @endif

            </div>

        </aside>

        {{-- MAIN CONTENT --}}
        <main class="flex-1 overflow-hidden bg-zinc-950">

            {{-- TOPBAR --}}
            <div class="h-20 border-b border-zinc-900 bg-zinc-950/80 backdrop-blur-xl px-6 flex items-center justify-between">

                <div>

                    <h1 class="text-lg font-bold text-white">
                        Dashboard
                    </h1>

                    <p class="text-sm text-zinc-500">
                        Welcome back, {{ $user->name ?? 'User' }}
                    </p>

                </div>

                <div class="flex items-center gap-3">

                    <button class="w-10 h-10 rounded-2xl bg-zinc-900 border border-zinc-800 hover:bg-zinc-800 transition-all flex items-center justify-center">
                        <x-icon name="o-bell" class="w-5 h-5 text-zinc-400" />
                    </button>

                    <button class="w-10 h-10 rounded-2xl bg-zinc-900 border border-zinc-800 hover:bg-zinc-800 transition-all flex items-center justify-center">
                        <x-icon name="o-cog-6-tooth" class="w-5 h-5 text-zinc-400" />
                    </button>

                </div>

            </div>

            {{-- PAGE CONTENT --}}
            <div class="h-[calc(100vh-80px)] overflow-y-auto no-scrollbar">

                <div class="p-6">
                    {{ $slot }}
                </div>

            </div>

        </main>

    </div>

    <x-toast />

</body>
</html>