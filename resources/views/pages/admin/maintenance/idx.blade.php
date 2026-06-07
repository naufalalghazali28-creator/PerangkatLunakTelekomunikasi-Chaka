<?php

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Mary\Traits\Toast;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Computed;

new class extends Component {
    use Toast;

    public bool $staffModal = false;
    public string $staffName = '';

    public array $headers = [
        ['key' => 'id',          'label' => 'ID Staff',      'class' => 'w-1'],
        ['key' => 'name',        'label' => 'Nama'],
        ['key' => 'email',       'label' => 'Email'],
        ['key' => 'role',        'label' => 'Role',          'class' => 'capitalize'],
        ['key' => 'client_name', 'label' => 'Client Pemilik'],
    ];

    // FIX: Eager load relasi untuk hindari N+1 query
    #[Computed]
    public function maintenances()
    {
        return User::where('role', 'maintenance')
            ->where(function ($query) {
                $query->where('email', 'like', '%@staff.id')
                      ->orWhere('email', 'like', '%@bems.id');
            })
            ->with(['clientOwner'])
            ->get();
    }

    public function saveMaintenance(): void
    {
        $this->validate(['staffName' => 'required|min:3']);

        $generatedEmail = Str::slug($this->staffName) . '@staff.id';

        if (User::where('email', $generatedEmail)->exists()) {
            $this->error('Email ' . $generatedEmail . ' sudah terdaftar!');
            return;
        }

        User::create([
            'name'     => $this->staffName,
            'email'    => $generatedEmail,
            'password' => Hash::make(config('app.default_staff_password', Str::random(12))),
            'role'     => 'maintenance',
        ]);

        $this->reset(['staffName', 'staffModal']);
        $this->success('Staff Maintenance berhasil didaftarkan!');
    }

    public function deleteStaff(int $id): void
    {
        abort_unless(Auth::user()->role === 'admin', 403);
        User::findOrFail($id)->delete();
        $this->success('Staf berhasil dihapus.');
    }

    public function loginAs(int $id): mixed
    {
        abort_unless(Auth::user()->role === 'admin', 403);
        Auth::loginUsingId($id);
        return redirect()->to('/maintenance');
    }

    public function exportExcel(): mixed
    {
        return Excel::download(
            new class implements
                \Maatwebsite\Excel\Concerns\FromQuery,
                \Maatwebsite\Excel\Concerns\WithHeadings,
                \Maatwebsite\Excel\Concerns\WithMapping
            {
                public function query()
                {
                    return User::where('role', 'maintenance')
                        ->where(function ($query) {
                            $query->where('email', 'like', '%@staff.id')
                                  ->orWhere('email', 'like', '%@bems.id');
                        });
                }

                public function map($user): array
                {
                    return [$user->id, $user->name, $user->email, $user->role];
                }

                public function headings(): array
                {
                    return ['ID Staff', 'Nama', 'Email', 'Role'];
                }
            },
            'Data_Maintenance_' . now()->format('d-m-Y') . '.xlsx'
        );
    }

    public function exportPdf(): void
    {
        $users = $this->maintenances;

        $pdf = Pdf::loadView('exports.maintenance-pdf', [
            'users'     => $users,
            'printedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdfBase64 = base64_encode($pdf->output());
        $this->dispatch('open-pdf', pdfBase64: $pdfBase64);
    }
}; ?>

<div>
    <x-header
        title="Maintenance Management"
        subtitle="Kelola teknisi maintenance dan sensor"
        separator
        progress-indicator
        class="
            text-zinc-900 dark:text-white
            [&_p]:text-zinc-500
            [&_p]:dark:text-zinc-400
        "
    >
        <x-slot:actions>
            <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                <x-menu-item title="Export PDF"   icon="o-document-text" wire:click="exportPdf"   class="text-error"   />
                <x-menu-item title="Export Excel" icon="o-table-cells"   wire:click="exportExcel" class="text-success" />
            </x-dropdown>
            <x-button
                label="Add Maintenance"
                icon="o-plus"
                wire:click="$set('staffModal', true)"
                class="btn-primary btn-sm"
            />
        </x-slot:actions>
    </x-header>

    <x-card
        shadow
        class="
            bg-white dark:bg-zinc-900
            border border-zinc-200 dark:border-zinc-800
            shadow-sm dark:shadow-none
            rounded-3xl
        "
    >
        {{-- Loading Indicator --}}
        <div wire:loading wire:target="exportPdf, exportExcel" class="mb-4">
            <x-alert title="Generating File..." icon="o-arrow-path" class="alert-info" />
        </div>

        <x-table
            :headers="$this->headers"
            :rows="$this->maintenances"
            striped
            class="
                bg-white dark:bg-zinc-900
                text-zinc-700 dark:text-zinc-300
                [&_thead]:bg-zinc-100
                [&_thead]:dark:bg-zinc-800
                [&_th]:text-zinc-600
                [&_th]:dark:text-zinc-300
                [&_tbody_tr]:border-zinc-200
                [&_tbody_tr]:dark:border-zinc-800
                [&_tbody_tr:hover]:bg-zinc-100
                [&_tbody_tr:hover]:dark:bg-zinc-800/60
            "
        >
            @scope('cell_client_name', $user)
                @if($user->clientOwner)
                    {{ $user->clientOwner->name }}
                @else
                    <x-badge value="Global (Admin)" class="badge-ghost badge-sm" />
                @endif
            @endscope

            @scope('actions', $user)
                <div class="flex gap-2">
                    <x-button
                        icon="o-pencil"
                        class="btn-ghost btn-sm hover:bg-blue-500/10 hover:text-blue-500 transition-all"
                    />
                    <x-button
                        icon="o-trash"
                        wire:click="deleteStaff({{ $user->id }})"
                        wire:confirm="Hapus teknisi ini?"
                        class="btn-ghost btn-sm hover:bg-red-500/10 hover:text-red-500 transition-all"
                    />
                    <x-button
                        icon="o-arrow-right-on-rectangle"
                        wire:click="loginAs({{ $user->id }})"
                        class="btn-ghost btn-sm text-success"
                    />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal
        wire:model="staffModal"
        title="Tambah Teknisi Baru"
        separator
        class="
            bg-white dark:bg-zinc-900
            text-zinc-800 dark:text-zinc-200
            border border-zinc-200 dark:border-zinc-800
            rounded-3xl
        "
    >
        <x-input
            label="Nama Lengkap"
            wire:model="staffName"
            hint="Email otomatis menjadi @staff.id"
            class="
                bg-zinc-100 dark:bg-zinc-800
                border-zinc-300 dark:border-zinc-700
                text-zinc-800 dark:text-white
            "
        />
        <x-slot:actions>
            <x-button label="Batal"  wire:click="$set('staffModal', false)" />
            <x-button label="Simpan" wire:click="saveMaintenance" class="btn-primary" />
        </x-slot:actions>
    </x-modal>

    <script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('open-pdf', (event) => {
            const base64 = event[0]?.pdfBase64 || event.pdfBase64;
            if (!base64) return;

            const byteCharacters = atob(base64);
            const byteArray = new Uint8Array(
                [...byteCharacters].map(c => c.charCodeAt(0))
            );
            const blob = new Blob([byteArray], { type: 'application/pdf' });
            const url  = URL.createObjectURL(blob);
            const win  = window.open(url, '_blank');

            if (!win) {
                alert('Izinkan popup blocker pada browser Anda untuk melihat file PDF.');
            }
        });
    });
    </script>
</div>