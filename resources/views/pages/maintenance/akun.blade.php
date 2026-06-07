<?php

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    // Tab aktif
    public string $tab = 'profil';

    // Form profil
    public string $name  = '';
    public string $email = '';

    // Form password
    public string $currentPassword = '';
    public string $newPassword     = '';
    public string $confirmPassword = '';

    public function mount(): void
    {
        $user        = Auth::user();
        $this->name  = $user->name;
        $this->email = $user->email;
    }

    public function updateProfil(): void
    {
        $this->validate([
            'name'  => 'required|min:3|max:100',
            'email' => 'required|email|unique:users,email,' . Auth::id(),
        ], [
            'name.required'  => 'Nama wajib diisi.',
            'name.min'       => 'Nama minimal 3 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'email.unique'   => 'Email sudah digunakan akun lain.',
        ]);

        Auth::user()->update([
            'name'  => $this->name,
            'email' => $this->email,
        ]);

        $this->success('Profil berhasil diperbarui!');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'currentPassword' => 'required',
            'newPassword'     => ['required', 'min:8', Password::defaults()],
            'confirmPassword' => 'required|same:newPassword',
        ], [
            'currentPassword.required' => 'Password lama wajib diisi.',
            'newPassword.required'     => 'Password baru wajib diisi.',
            'newPassword.min'          => 'Password minimal 8 karakter.',
            'confirmPassword.same'     => 'Konfirmasi password tidak cocok.',
        ]);

        if (!Hash::check($this->currentPassword, Auth::user()->password)) {
            $this->addError('currentPassword', 'Password lama tidak sesuai.');
            return;
        }

        Auth::user()->update([
            'password' => Hash::make($this->newPassword),
        ]);

        $this->reset(['currentPassword', 'newPassword', 'confirmPassword']);
        $this->success('Password berhasil diperbarui!');
    }
}; ?>

<div>
    <x-header
        title="Info Akun"
        subtitle="Kelola profil dan keamanan akun Anda"
        separator
        progress-indicator
    />

    <div class="max-w-2xl space-y-4">

        {{-- PROFIL CARD --}}
        <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">

            {{-- Avatar & Role --}}
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-zinc-100 dark:border-zinc-800">
                <div class="w-16 h-16 rounded-2xl bg-green-500/10 border border-green-500/20 flex items-center justify-center text-2xl font-bold text-green-500">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-lg font-bold text-zinc-900 dark:text-white">{{ Auth::user()->name }}</p>
                    <p class="text-sm text-zinc-400">{{ Auth::user()->email }}</p>
                    <x-badge value="{{ Auth::user()->role }}" class="badge-ghost badge-sm capitalize mt-1" />
                </div>
                <div class="ml-auto text-right">
                    <p class="text-xs text-zinc-400">Bergabung sejak</p>
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">
                        {{ Auth::user()->created_at->format('d M Y') }}
                    </p>
                </div>
            </div>

            {{-- Form Profil --}}
            <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-4">Edit Profil</p>
            <div class="space-y-4">
                <x-input
                    label="Nama Lengkap"
                    wire:model="name"
                    icon="o-user"
                    placeholder="Nama lengkap Anda"
                />
                <x-input
                    label="Email"
                    wire:model="email"
                    icon="o-envelope"
                    type="email"
                    placeholder="email@staff.id"
                    hint="Email digunakan untuk login"
                />
            </div>
            <x-slot:actions>
                <x-button label="Simpan Profil" wire:click="updateProfil" spinner="updateProfil" class="btn-primary" />
            </x-slot:actions>
        </x-card>

        {{-- PASSWORD CARD --}}
        <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-4">🔒 Ganti Password</p>
            <div class="space-y-4">
                <x-input
                    label="Password Lama"
                    wire:model="currentPassword"
                    type="password"
                    icon="o-lock-closed"
                    placeholder="••••••••"
                />
                <x-input
                    label="Password Baru"
                    wire:model="newPassword"
                    type="password"
                    icon="o-lock-open"
                    placeholder="Min. 8 karakter"
                    hint="Gunakan kombinasi huruf besar, kecil, angka, dan simbol"
                />
                <x-input
                    label="Konfirmasi Password Baru"
                    wire:model="confirmPassword"
                    type="password"
                    icon="o-check"
                    placeholder="••••••••"
                />
            </div>
            <x-slot:actions>
                <x-button label="Ganti Password" wire:click="updatePassword" spinner="updatePassword" class="btn-warning" />
            </x-slot:actions>
        </x-card>

        {{-- INFO READONLY --}}
        <x-card shadow class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-3">Info Sistem</p>
            <div class="space-y-2 text-sm">
                @foreach([
                    ['label' => 'User ID',    'value' => '#' . Auth::id()],
                    ['label' => 'Role',        'value' => ucfirst(Auth::user()->role)],
                    ['label' => 'Status',      'value' => 'Aktif'],
                    ['label' => 'Terdaftar',   'value' => Auth::user()->created_at->format('d M Y, H:i')],
                    ['label' => 'Terakhir Update', 'value' => Auth::user()->updated_at->diffForHumans()],
                ] as $row)
                <div class="flex justify-between">
                    <span class="text-zinc-400">{{ $row['label'] }}</span>
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $row['value'] }}</span>
                </div>
                @endforeach
            </div>
        </x-card>

    </div>
</div>