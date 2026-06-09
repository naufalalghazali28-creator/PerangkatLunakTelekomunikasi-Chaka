<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    {{-- Vite: CSS dulu, JS belakangan (defer otomatis) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Chart.js: WAJIB defer agar tidak balapan dengan Alpine --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        [x-cloak] { display: none !important; }

        .nav-active-green  { @apply bg-green-500/10  text-green-400  border border-green-500/20  shadow-sm shadow-green-500/10; }
        .nav-active-blue   { @apply bg-blue-500/10   text-blue-400   border border-blue-500/20   shadow-sm shadow-blue-500/10; }
        .nav-active-violet { @apply bg-violet-500/10 text-violet-400 border border-violet-500/20 shadow-sm shadow-violet-500/10; }
        .nav-idle          { @apply border border-transparent text-zinc-400 hover:bg-zinc-900 hover:text-white; }
    </style>
</head>

<body class="min-h-screen antialiased bg-zinc-100 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-200 transition-colors duration-300">

@php
    $user     = auth()->user();
    $role     = strtolower(trim($user->role ?? ''));
    $isAdmin  = $role === 'admin';
    $isClient = $role === 'client';
    $isMaint  = $role === 'maintenance';
    $isOp     = $role === 'operator';
    $isViewer = $role === 'viewer';

    $accentClass = match(true) {
        $isMaint  => 'text-green-400',
        $isOp     => 'text-blue-400',
        $isViewer => 'text-violet-400',
        default   => 'text-emerald-400',
    };
    $accentBg = match(true) {
        $isMaint  => 'from-green-500 to-emerald-600',
        $isOp     => 'from-blue-500 to-cyan-600',
        $isViewer => 'from-violet-500 to-purple-600',
        default   => 'from-green-500 to-emerald-600',
    };
    $activeNav = match(true) {
        $isMaint  => 'nav-active-green',
        $isOp     => 'nav-active-blue',
        $isViewer => 'nav-active-violet',
        default   => 'nav-active-green',
    };

    $on = fn(string $pattern): bool => request()->is($pattern);
@endphp

<div
    x-data="{
        open: localStorage.getItem('sidebar') !== 'false',
        dark: localStorage.getItem('darkMode') === 'true',
        init() {
            this.$watch('dark', v => {
                localStorage.setItem('darkMode', v);
                document.documentElement.classList.toggle('dark', v);
            });
            this.$watch('open', v => localStorage.setItem('sidebar', v));
            document.documentElement.classList.toggle('dark', this.dark);
        }
    }"
    class="flex h-screen overflow-hidden"
>

{{-- ═══ SIDEBAR ═══ --}}
<aside
    class="relative shrink-0 flex flex-col bg-zinc-950 border-r border-zinc-900 transition-all duration-300 ease-[cubic-bezier(0.7,0,0.2,1)]"
    :class="open ? 'w-[272px]' : 'w-[72px]'"
>
    {{-- BRAND --}}
    <div class="h-16 px-3 border-b border-zinc-900 flex items-center justify-between shrink-0">
        <div class="flex items-center min-w-0" :class="open ? 'gap-3' : 'w-full justify-center'">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br {{ $accentBg }} flex items-center justify-center shadow-lg shrink-0">
                <span class="text-black font-black text-sm">C</span>
            </div>
            <div x-show="open" x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak class="leading-tight overflow-hidden">
                <p class="font-bold text-white text-sm tracking-wide">CHAKA</p>
                <p class="text-[10px] text-zinc-500">Telecom Dashboard</p>
            </div>
        </div>
        <button x-show="open" @click="open = false" class="w-8 h-8 rounded-lg border border-zinc-800 bg-zinc-900 hover:bg-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white transition-all shrink-0">
            <x-icon name="o-chevron-left" class="w-4 h-4" />
        </button>
    </div>

    {{-- COLLAPSED TOGGLE --}}
    <div x-show="!open" class="py-3 flex justify-center border-b border-zinc-900 shrink-0">
        <button @click="open = true" class="w-8 h-8 rounded-lg border border-zinc-800 bg-zinc-900 hover:bg-zinc-800 flex items-center justify-center text-zinc-500 hover:text-white transition-all">
            <x-icon name="o-chevron-right" class="w-4 h-4" />
        </button>
    </div>

    {{-- ROLE BADGE --}}
    <div x-show="open" x-cloak class="mx-3 mt-3 mb-1 px-3 py-2.5 rounded-xl bg-zinc-900 border border-zinc-800">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></div>
            <span class="text-[11px] font-semibold {{ $accentClass }} uppercase tracking-widest">{{ $role }}</span>
        </div>
        <p class="text-[11px] text-zinc-300 font-medium truncate">{{ $user->name }}</p>
        <p class="text-[10px] text-zinc-500 truncate">{{ $user->email }}</p>
    </div>

    {{-- NAV --}}
    <nav class="flex-1 overflow-y-auto no-scrollbar px-2 py-3 space-y-0.5">

        {{-- ─── ADMIN ─────────────────────────────── --}}
        @if($isAdmin)
        @php
            $adminSections = [
                ['label' => 'Overview',  'icon' => 'o-squares-2x2',       'url' => '/admin'],
                ['label' => 'Clients',   'icon' => 'o-building-office-2',  'url' => '/admin/client'],
                ['label' => 'Gedung',    'icon' => 'o-building-office',    'url' => '/admin/gedung'],
            ];
            $staffSections = [
                ['label' => 'Maintenance', 'icon' => 'o-wrench-screwdriver', 'url' => '/admin/maintenance', 'match' => 'admin/maintenance*'],
                ['label' => 'Operator',    'icon' => 'o-cpu-chip',           'url' => '/admin/operator',    'match' => 'admin/operator*'],
                ['label' => 'Viewer',      'icon' => 'o-eye',                'url' => '/admin/viewer',      'match' => 'admin/viewer*'],
            ];
        @endphp
        @foreach($adminSections as $s)
        <a href="{{ url($s['url']) }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group relative
                  {{ $on(ltrim($s['url'],'/')) ? 'nav-active-green' : 'nav-idle' }}"
           :class="!open && 'justify-center px-0'">
            <x-icon name="{{ $s['icon'] }}" class="w-5 h-5 shrink-0" />
            <span x-show="open" x-cloak class="text-sm font-medium">{{ $s['label'] }}</span>
            <div x-show="!open" class="absolute left-full ml-3 px-2.5 py-1.5 rounded-lg bg-zinc-800 text-xs text-white whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50">{{ $s['label'] }}</div>
        </a>
        @endforeach
        @php $staffInitOpen = collect($staffSections)->contains(fn($s) => $on($s['match'])) ? 'true' : 'false'; @endphp
        <div x-data="{ staffOpen: {{ $staffInitOpen }} }">
            <button @click="open ? staffOpen = !staffOpen : (open = true, staffOpen = true)"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all nav-idle group relative"
                :class="!open && 'justify-center px-0'">
                <x-icon name="o-users" class="w-5 h-5 shrink-0" />
                <span x-show="open" x-cloak class="text-sm font-medium flex-1 text-left">Staff</span>
                <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-zinc-600 transition-transform duration-300" :class="staffOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                <div x-show="!open" class="absolute left-full ml-3 px-2.5 py-1.5 rounded-lg bg-zinc-800 text-xs text-white whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50">Staff</div>
            </button>
            <div x-show="staffOpen && open" x-cloak
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
                 class="pl-4 mt-0.5 space-y-0.5">
                @foreach($staffSections as $s)
                <a href="{{ url($s['url']) }}" class="flex items-center gap-3 px-3 py-2 rounded-xl transition-all text-sm {{ $on($s['match']) ? 'nav-active-green' : 'nav-idle' }}">
                    <x-icon name="{{ $s['icon'] }}" class="w-4 h-4 shrink-0" />
                    <span>{{ $s['label'] }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif
        {{-- ─── END ADMIN ─── --}}

        {{-- ─── CLIENT ─── --}}
        @if($isClient)
        @php $clientNav = [
            ['label' => 'Gedung',    'icon' => 'o-building-office', 'url' => '/client?tab=infra', 'match' => 'client'],
            ['label' => 'Staff',     'icon' => 'o-users',           'url' => '/client?tab=staff', 'match' => 'client'],
            ['label' => 'Info Akun', 'icon' => 'o-identification',  'url' => '/client?tab=info',  'match' => 'client'],
        ]; @endphp
        @foreach($clientNav as $s)
        <a href="{{ url($s['url']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all group relative nav-idle" :class="!open && 'justify-center px-0'">
            <x-icon name="{{ $s['icon'] }}" class="w-5 h-5 shrink-0" />
            <span x-show="open" x-cloak class="text-sm font-medium">{{ $s['label'] }}</span>
            <div x-show="!open" class="absolute left-full ml-3 px-2.5 py-1.5 rounded-lg bg-zinc-800 text-xs text-white whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50">{{ $s['label'] }}</div>
        </a>
        @endforeach
        @endif

        {{-- ─── MAINTENANCE ─── --}}
        @if($isMaint)
        @php $maintNav = [
            ['label' => 'Dashboard',       'icon' => 'o-home',                     'url' => '/maintenance',              'match' => 'maintenance',           'desc' => 'Ringkasan aktivitas'],
            ['label' => 'Daftar Node',     'icon' => 'o-plus-circle',              'url' => '/maintenance/register-node','match' => 'maintenance/register-node*','desc' => 'Tambah node baru ke gedung'],
            ['label' => 'Node Inventory',  'icon' => 'o-server-stack',             'url' => '/maintenance/nodes',        'match' => 'maintenance/nodes*',    'desc' => 'Semua node terdaftar'],
            ['label' => 'Log Instalasi',   'icon' => 'o-clipboard-document-list',  'url' => '/maintenance/logs',         'match' => 'maintenance/logs*',     'desc' => 'Riwayat pemasangan'],
            ['label' => 'Info Akun',       'icon' => 'o-identification',           'url' => '/maintenance/akun',         'match' => 'maintenance/akun*',     'desc' => 'Profil & keamanan'],
        ]; @endphp
        <div x-show="open" x-cloak class="px-3 pt-3 pb-1 text-[10px] font-semibold text-zinc-600 uppercase tracking-[0.18em]">Maintenance</div>
        @foreach($maintNav as $s)
        <a href="{{ url($s['url']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group relative {{ $on($s['match']) ? $activeNav : 'nav-idle' }}" :class="!open && 'justify-center px-0'">
            <x-icon name="{{ $s['icon'] }}" class="w-5 h-5 shrink-0" />
            <div x-show="open" x-cloak class="flex-1 min-w-0">
                <p class="text-sm font-medium leading-tight">{{ $s['label'] }}</p>
                <p class="text-[10px] text-zinc-500 leading-tight truncate">{{ $s['desc'] }}</p>
            </div>
            <div x-show="!open" class="absolute left-full ml-3 px-2.5 py-1.5 rounded-lg bg-zinc-800 text-xs text-white whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50">{{ $s['label'] }}</div>
        </a>
        @endforeach
        @endif

        {{-- ─── OPERATOR ─── --}}
        @if($isOp)
        @php $opNav = [
            ['label' => 'Dashboard',      'icon' => 'o-home',           'url' => '/operator',          'match' => 'operator',         'desc' => 'Ringkasan sistem'],
            ['label' => 'Sensor Control', 'icon' => 'o-bolt',           'url' => '/operator/control',  'match' => 'operator/control*', 'desc' => 'Aktifkan / nonaktifkan sensor'],
            ['label' => 'Live Monitor',   'icon' => 'o-signal',         'url' => '/operator/monitor',  'match' => 'operator/monitor*', 'desc' => 'Data real-time sensor'],
            ['label' => 'Info Akun',      'icon' => 'o-identification', 'url' => '/operator/akun',     'match' => 'operator/akun*',    'desc' => 'Profil & keamanan'],
        ]; @endphp
        <div x-show="open" x-cloak class="px-3 pt-3 pb-1 text-[10px] font-semibold text-zinc-600 uppercase tracking-[0.18em]">Operator</div>
        @foreach($opNav as $s)
        <a href="{{ url($s['url']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group relative {{ $on($s['match']) ? $activeNav : 'nav-idle' }}" :class="!open && 'justify-center px-0'">
            <x-icon name="{{ $s['icon'] }}" class="w-5 h-5 shrink-0" />
            <div x-show="open" x-cloak class="flex-1 min-w-0">
                <p class="text-sm font-medium leading-tight">{{ $s['label'] }}</p>
                <p class="text-[10px] text-zinc-500 leading-tight truncate">{{ $s['desc'] }}</p>
            </div>
            <div x-show="!open" class="absolute left-full ml-3 px-2.5 py-1.5 rounded-lg bg-zinc-800 text-xs text-white whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50">{{ $s['label'] }}</div>
        </a>
        @endforeach
        @endif

        {{-- ─── VIEWER ─── --}}
        @if($isViewer)
        @php $viewNav = [
            ['label' => 'Dashboard',   'icon' => 'o-home',           'url' => '/viewer',          'match' => 'viewer',         'desc' => 'Kondisi semua gedung'],
            ['label' => 'List Gedung', 'icon' => 'o-building-office','url' => '/viewer/gedung',   'match' => 'viewer/gedung*', 'desc' => 'Daftar gedung terdaftar'],
            ['label' => 'List Sensor', 'icon' => 'o-eye',            'url' => '/viewer/sensors',  'match' => 'viewer/sensors*','desc' => 'Semua sensor & datanya'],
            ['label' => 'Info Akun',   'icon' => 'o-identification', 'url' => '/viewer/akun',     'match' => 'viewer/akun*',   'desc' => 'Profil'],
        ]; @endphp
        <div x-show="open" x-cloak class="px-3 pt-3 pb-1 text-[10px] font-semibold text-zinc-600 uppercase tracking-[0.18em]">Viewer</div>
        @foreach($viewNav as $s)
        <a href="{{ url($s['url']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group relative {{ $on($s['match']) ? $activeNav : 'nav-idle' }}" :class="!open && 'justify-center px-0'">
            <x-icon name="{{ $s['icon'] }}" class="w-5 h-5 shrink-0" />
            <div x-show="open" x-cloak class="flex-1 min-w-0">
                <p class="text-sm font-medium leading-tight">{{ $s['label'] }}</p>
                <p class="text-[10px] text-zinc-500 leading-tight truncate">{{ $s['desc'] }}</p>
            </div>
            <div x-show="!open" class="absolute left-full ml-3 px-2.5 py-1.5 rounded-lg bg-zinc-800 text-xs text-white whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50">{{ $s['label'] }}</div>
        </a>
        @endforeach
        @endif

    </nav>

    {{-- PROFILE --}}
    <div class="p-3 border-t border-zinc-900 shrink-0">
        <div class="flex items-center" :class="open ? 'gap-3' : 'justify-center'">
            <div class="w-9 h-9 rounded-xl bg-zinc-800 border border-zinc-700 flex items-center justify-center text-sm font-bold text-white shrink-0">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div x-show="open" x-cloak class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate">{{ $user->name }}</p>
                <p class="text-[10px] {{ $accentClass }} font-bold uppercase tracking-widest">{{ $role }}</p>
            </div>
            <div x-show="open" x-cloak>
                <form method="POST" action="/logout">
                    @csrf
                    <button type="submit" title="Logout" class="w-8 h-8 rounded-lg hover:bg-zinc-900 flex items-center justify-center text-zinc-500 hover:text-red-400 transition-all">
                        <x-icon name="o-arrow-right-on-rectangle" class="w-4 h-4" />
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
{{-- ═══ END SIDEBAR ═══ --}}

{{-- ═══ MAIN CONTENT ═══ --}}
<div class="flex-1 flex flex-col overflow-hidden">

    {{-- TOPBAR --}}
    <header class="h-16 shrink-0 border-b border-zinc-200 dark:border-zinc-900 bg-white/90 dark:bg-zinc-950/90 backdrop-blur-xl px-6 flex items-center justify-between">
        <div>
            @isset($pageTitle)
                <h1 class="text-base font-bold text-zinc-900 dark:text-white">{{ $pageTitle }}</h1>
            @else
                <h1 class="text-base font-bold text-zinc-900 dark:text-white">{{ config('app.name') }}</h1>
            @endisset
            <p class="text-xs text-zinc-400">{{ now()->translatedFormat('l, d F Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button @click="dark = !dark" class="w-9 h-9 rounded-xl bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700 flex items-center justify-center transition-all duration-200" :title="dark ? 'Switch to light mode' : 'Switch to dark mode'">
                <svg x-show="dark" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" /></svg>
                <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z" /></svg>
            </button>
            <button class="w-9 h-9 rounded-xl bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700 flex items-center justify-center transition-all duration-200">
                <x-icon name="o-bell" class="w-4 h-4 text-zinc-500 dark:text-zinc-400" />
            </button>
        </div>
    </header>

    {{-- PAGE CONTENT --}}
    <main class="flex-1 overflow-y-auto no-scrollbar bg-zinc-100 dark:bg-zinc-950">
        <div class="p-6">
            {{ $slot }}
        </div>
    </main>

</div>
{{-- ═══ END MAIN ═══ --}}

</div>

<x-toast />

{{--
    WAJIB: Livewire scripts harus di sini, di bawah body content.
    Tanpa ini, Livewire tidak bisa berkomunikasi dengan server (wire:model, dll tidak jalan).
    @stack('scripts') untuk menerima @push('scripts') dari komponen manapun.
--}}
@livewireScripts
@stack('scripts')

</body>
</html>