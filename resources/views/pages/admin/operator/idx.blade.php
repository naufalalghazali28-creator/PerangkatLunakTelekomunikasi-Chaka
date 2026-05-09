<?php

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Mary\Traits\Toast;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use Toast;

    public bool $staffModal = false;
    public string $staffName = '';

    public array $headers = [
        ['key' => 'id', 'label' => 'ID Staff', 'class' => 'w-1'],
        ['key' => 'name', 'label' => 'Nama'],
        ['key' => 'email', 'label' => 'Email'],
        ['key' => 'role', 'label' => 'Role', 'class' => 'capitalize'],
        ['key' => 'client_name', 'label' => 'Client Pemilik'],
    ];

    public function saveOperator() {
        $this->validate([
            'staffName' => 'required|min:3',
        ]);

        // Otomatis membuat email: nama@staff.id (slug agar tidak ada spasi)
        $generatedEmail = Str::slug($this->staffName) . '@staff.id';

        // Cek apakah email sudah ada
        if (User::where('email', $generatedEmail)->exists()) {
            $this->error('Email ' . $generatedEmail . ' sudah terdaftar!');
            return;
        }

        User::create([
            'name' => $this->staffName,
            'email' => $generatedEmail,
            'password' => Hash::make('password123'), // Password default internal
            'role' => 'operator', 
        ]);

        $this->reset(['staffName', 'staffModal']);
        $this->success('Operator ' . $this->staffName . ' berhasil didaftarkan!');
    }

    public function deleteStaff($id) {
        User::find($id)->delete();
        $this->success('Staf berhasil dihapus.');
    }

    public function loginAs($id) {
        Auth::loginUsingId($id);
        return redirect()->to('/operator'); 
    }

    // --- FUNGSI EXPORT EXCEL ---
    public function exportExcel() {
        return Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromQuery, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping {
            public function query() {
                return User::where('role', 'operator')->where(function($query) {
                    $query->where('email', 'like', '%@staff.id')
                          ->orWhere('email', 'like', '%@bems.id');
                });
            }
            public function map($user): array {
                return [
                    $user->id, $user->name, $user->email, $user->role
                ];
            }
            public function headings(): array { 
                return ['ID Staff', 'Nama', 'Email', 'Role']; 
            }
        }, 'Data_Operator_' . now()->format('d-m-Y') . '.xlsx');
    }

    // --- FUNGSI EXPORT PDF ---
    public function exportPdf() {
        $users = User::where('role', 'operator')->where(function($query) {
            $query->where('email', 'like', '%@staff.id')
                  ->orWhere('email', 'like', '%@bems.id');
        })->get();
        
        $html = "
            <style>
                body { font-family: sans-serif; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                h2 { text-align: center; }
            </style>
            <h2>LAPORAN DATA OPERATOR</h2>
            <p>Tanggal Cetak: " . now()->format('d/m/Y H:i') . "</p>
            <table>
                <thead>
                    <tr>
                        <th>ID Staff</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach($users as $u) {
            $html .= "<tr>
                <td>{$u->id}</td>
                <td>{$u->name}</td>
                <td>{$u->email}</td>
                <td style='text-transform: capitalize;'>{$u->role}</td>
            </tr>";
        }
        $html .= "</tbody></table>";

        $pdf = Pdf::loadHTML($html);
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Data_Operator_' . now()->format('d-m-Y') . '.pdf');
    }

    public function render() {
        return <<<'HTML'
        <div>
            <x-header title="Admin | Operator Management" subtitle="Manajemen Staf Operator (Global)" separator progress-indicator>
                <x-slot:actions>
                    <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                        <x-menu-item title="Export PDF" icon="o-document-text" wire:click="exportPdf" class="text-error" />
                        <x-menu-item title="Export Excel" icon="o-table-cells" wire:click="exportExcel" class="text-success" />
                    </x-dropdown>
                    <x-button label="Add Operator" icon="o-plus" wire:click="$set('staffModal', true)" class="btn-primary btn-sm" />
                </x-slot:actions>
            </x-header>

            <x-card shadow>
                {{-- Loading Indicator --}}
                <div wire:loading wire:target="exportPdf, exportExcel" class="mb-4">
                    <x-alert title="Generating File..." icon="o-arrow-path" class="alert-info" />
                </div>

                {{-- Gunakan WHERE LIKE agar tidak masalah huruf besar/kecil --}}
                <x-table 
                    :headers="$this->headers" 
                    :rows="\App\Models\User::where('role', 'operator')
                    ->where(function($query) {
                        $query->where('email', 'like', '%@staff.id')
                            ->orWhere('email', 'like', '%@bems.id'); // Tambahan jika ada email admin/client
                    })->get()">
                    @scope('cell_client_name', $user)
                        @if($user->parent_id)
                            {{ \App\Models\BEMS\Client::where('user_id', $user->parent_id)->value('name') ?? 'Tidak diketahui' }}
                        @else
                            <x-badge value="Global (Admin)" class="badge-ghost badge-sm" />
                        @endif
                    @endscope
                    @scope('actions', $user)
                        <div class="flex gap-2">
                            <x-button icon="o-pencil" class="btn-ghost btn-sm text-info" />
                            <x-button icon="o-trash" wire:click="deleteStaff({{ $user->id }})" wire:confirm="Hapus staf ini?" class="btn-ghost btn-sm text-error" />
                            <x-button icon="o-arrow-right-on-rectangle" wire:click="loginAs({{ $user->id }})" class="btn-ghost btn-sm text-success" />
                        </div>
                    @endscope
                </x-table>
            </x-card>

            <x-modal wire:model="staffModal" title="Tambah Operator Baru" separator>
                <div class="space-y-4">
                    {{-- Input Nama saja --}}
                    <x-input label="Nama Lengkap" wire:model="staffName" placeholder="Masukkan nama..." />
                    
                    {{-- Preview Email Otomatis (Biar kamu tahu emailnya apa) --}}
                    @if($staffName)
                        <div class="text-xs text-gray-400 mt-1">
                            Email otomatis: <strong>{{ Str::slug($staffName) }}@staff.id</strong>
                        </div>
                    @endif
                </div>
                <x-slot:actions>
                    <x-button label="Batal" wire:click="$set('staffModal', false)" />
                    <x-button label="Simpan" wire:click="saveOperator" class="btn-primary" />
                </x-slot:actions>
            </x-modal>
        </div>
        HTML;
    }
}; ?>