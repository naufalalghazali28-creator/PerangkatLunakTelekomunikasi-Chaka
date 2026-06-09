<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $name            = '';
    public string $email           = '';
    public string $currentPassword = '';
    public string $newPassword     = '';
    public string $confirmPassword = '';

    public function mount(): void
    {
        $this->name  = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfil(): void
    {
        $this->validate([
            'name'  => 'required|min:3|max:100',
            'email' => 'required|email|unique:users,email,' . Auth::id(),
        ]);
        Auth::user()->update(['name' => $this->name, 'email' => $this->email]);
        $this->success('Profil berhasil diperbarui!');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'currentPassword' => 'required',
            'newPassword'     => ['required', 'min:8', Password::defaults()],
            'confirmPassword' => 'required|same:newPassword',
        ]);
        if (!Hash::check($this->currentPassword, Auth::user()->password)) {
            $this->addError('currentPassword', 'Password lama tidak sesuai.');
            return;
        }
        Auth::user()->update(['password' => Hash::make($this->newPassword)]);
        $this->reset(['currentPassword', 'newPassword', 'confirmPassword']);
        $this->success('Password berhasil diperbarui!');
    }
}; ?>

<div>
    <x-header title="Info Akun" subtitle="Kelola profil dan keamanan akun Anda" separator progress-indicator />

    <div class="max-w-2xl space-y-4">
        <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-zinc-100 dark:border-zinc-800">
                <div class="w-16 h-16 rounded-2xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-2xl font-bold text-violet-500">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-lg font-bold text-zinc-900 dark:text-white">{{ Auth::user()->name }}</p>
                    <p class="text-sm text-zinc-400">{{ Auth::user()->email }}</p>
                    <x-badge value="{{ Auth::user()->role }}" class="badge-ghost badge-sm capitalize mt-1" />
                </div>
                <div class="ml-auto text-right">
                    <p class="text-xs text-zinc-400">Bergabung sejak</p>
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ Auth::user()->created_at->format('d M Y') }}</p>
                </div>
            </div>
            <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-4">Edit Profil</p>
            <div class="space-y-4">
                <x-input label="Nama Lengkap" wire:model="name"  icon="o-user"     placeholder="Nama lengkap Anda" />
                <x-input label="Email"         wire:model="email" icon="o-envelope" type="email" />
            </div>
            <x-slot:actions>
                <x-button label="Simpan Profil" wire:click="updateProfil" spinner="updateProfil" class="btn-primary" />
            </x-slot:actions>
        </x-card>

        <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-4">🔒 Ganti Password</p>
            <div class="space-y-4">
                <x-input label="Password Lama"            wire:model="currentPassword" type="password" icon="o-lock-closed" />
                <x-input label="Password Baru"            wire:model="newPassword"     type="password" icon="o-lock-open"   hint="Min. 8 karakter" />
                <x-input label="Konfirmasi Password Baru" wire:model="confirmPassword" type="password" icon="o-check" />
            </div>
            <x-slot:actions>
                <x-button label="Ganti Password" wire:click="updatePassword" spinner="updatePassword" class="btn-warning" />
            </x-slot:actions>
        </x-card>

        <x-card shadow class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-3">Info Sistem</p>
            <div class="space-y-2 text-sm">
                @foreach([
                    ['User ID',    '#' . Auth::id()],
                    ['Role',       ucfirst(Auth::user()->role)],
                    ['Terdaftar',  Auth::user()->created_at->format('d M Y, H:i')],
                    ['Terakhir Update', Auth::user()->updated_at->diffForHumans()],
                ] as [$label, $val])
                <div class="flex justify-between">
                    <span class="text-zinc-400">{{ $label }}</span>
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $val }}</span>
                </div>
                @endforeach
            </div>
        </x-card>
    </div>
</div>