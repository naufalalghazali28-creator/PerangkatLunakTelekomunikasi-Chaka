<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    {{-- Load Library Chart.js untuk komponen x-chart Mary UI --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">

    {{-- NAVBAR mobile only --}}
    <x-nav sticky class="lg:hidden">
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            {{-- Tombol ganti tema untuk versi Mobile --}}
            <x-theme-toggle class="btn-ghost btn-sm mr-2" />
            <label for="main-drawer" class="lg:hidden me-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>

    {{-- MAIN --}}
    <x-main>
        {{-- SIDEBAR --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

            {{-- BRAND --}}
            <x-app-brand class="px-5 pt-4" />

            {{-- MENU --}}
            <x-menu activate-by-route>
                
                @if($user = auth()->user())
                    @php 
                        $role = strtolower($user->role ?? ''); 
                    @endphp
                    <x-menu-separator />
                    <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
                        <x-slot:actions>
                            {{-- Tombol ganti tema dan tombol logout berjajar di sidebar Desktop --}}
                            <x-theme-toggle class="btn-circle btn-ghost btn-xs tooltip-left mr-1" tooltip="Ganti Tema" />
                            <x-button icon="o-power" class="btn-circle btn-ghost btn-xs text-error" tooltip-left="Logoff" no-wire-navigate link="/logout" />
                        </x-slot:actions>
                    </x-list-item>
                    <x-menu-separator />
                @else
                    <x-menu-item title="Login Ulang" icon="o-power" link="/logout" class="text-error" />
                    @php $role = ''; @endphp
                @endif

                <x-menu-item title="Home" icon="o-home" link="/" />

                {{-- MENU KHUSUS ADMIN MANAGEMENT --}}
                @if($role === 'admin' || (auth()->check() && auth()->user()->email === 'admin@bems.id'))
                    <x-menu-separator title="Admin Management" />
                    <x-menu-item title="Manage Clients" icon="o-users" link="{{ route('admin.client') }}" />
                    <x-menu-item title="Manage Gedung" icon="o-building-office" link="{{ route('admin.gedung') }}" />
                    <x-menu-item title="Manage Operator" icon="o-cpu-chip" link="{{ route('admin.operator') }}" />
                    <x-menu-item title="Manage Maintenance" icon="o-wrench-screwdriver" link="{{ route('admin.maintenance') }}" />
                    <x-menu-item title="Manage Viewer" icon="o-eye" link="{{ route('admin.viewer') }}" />
                @endif

                {{-- MENU KHUSUS CLIENT MANAGEMENT (PERBAIKAN DI SINI) --}}
                @if($role === 'client')
                    <x-menu-separator title="Client Area" />
                    
                    {{-- Semua mengarah ke route dashboard tapi dengan parameter tab yang berbeda --}}
                    <x-menu-item title="Dashboard Gedung" icon="o-squares-2x2" link="/client?tab=infra" />
                    <x-menu-item title="Manage Staf" icon="o-user-group" link="/client?tab=staff" />
                    <x-menu-item title="Info Akun" icon="o-identification" link="/client?tab=info" />
                @endif

                {{-- MENU KHUSUS STAFF AREA --}}
                @if(in_array($role, ['operator', 'maintenance', 'viewer']))
                    <x-menu-separator title="Staff Area" />
                    @if($role === 'maintenance')
                        <x-menu-item title="Hardware Setup" icon="o-wrench" link="/maintenance" />
                    @endif
                    @if($role === 'operator')
                        <x-menu-item title="Control Panel" icon="o-bolt" link="/operator" />
                    @endif
                    @if($role === 'viewer')
                        <x-menu-item title="Data Monitoring" icon="o-chart-bar" link="/viewer" />
                    @endif
                @endif

            </x-menu>
        </x-slot:sidebar>

        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-main>

    <x-toast />
</body>
</html>