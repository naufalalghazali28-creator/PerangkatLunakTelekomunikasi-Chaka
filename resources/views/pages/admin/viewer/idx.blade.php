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

    public function saveViewer() {
        $this->validate(['staffName' => 'required|min:3']);

        $generatedEmail = Str::slug($this->staffName) . '@staff.id';

        if (User::where('email', $generatedEmail)->exists()) {
            $this->error('Email ' . $generatedEmail . ' sudah terdaftar!');
            return;
        }

        User::create([
            'name' => $this->staffName,
            'email' => $generatedEmail,
            'password' => Hash::make('password123'),
            'role' => 'viewer', 
        ]);

        $this->reset(['staffName', 'staffModal']);
        $this->success('Staff Viewer berhasil didaftarkan!');
    }

    public function deleteStaff($id) {
        User::find($id)->delete();
        $this->success('Staf berhasil dihapus.');
    }

    public function loginAs($id) {
        Auth::loginUsingId($id);
        return redirect()->to('/viewer'); 
    }

    // --- FUNGSI EXPORT EXCEL ---
    public function exportExcel() {
        return Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromQuery, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping {
            public function query() {
                return User::where('role', 'viewer')->where(function($query) {
                    $query->where('email', 'like', '%@staff.id');
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
        }, 'Data_Viewer_' . now()->format('d-m-Y') . '.xlsx');
    }

    // --- FUNGSI EXPORT PDF ---
    public function exportPdf() {
        $users = User::where('role', 'viewer')->where(function($query) {
            $query->where('email', 'like', '%@staff.id');
        })->get();
        
        $html = "
            <style>
                body { font-family: sans-serif; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                h2 { text-align: center; }
            </style>
            <h2>LAPORAN DATA VIEWER</h2>
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
        }, 'Data_Viewer_' . now()->format('d-m-Y') . '.pdf');
    }

    public function render() {
        return <<<'HTML'
        <div>
            <x-header title="Admin | Viewer Management" subtitle="Staf pemantau data" separator progress-indicator>
                <x-slot:actions>
                    <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                        <x-menu-item title="Export PDF" icon="o-document-text" wire:click="exportPdf" class="text-error" />
                        <x-menu-item title="Export Excel" icon="o-table-cells" wire:click="exportExcel" class="text-success" />
                    </x-dropdown>
                    <x-button label="Add Viewer" icon="o-plus" wire:click="$set('staffModal', true)" class="btn-primary btn-sm" />
                </x-slot:actions>
            </x-header>

            <x-card shadow>
                {{-- Loading Indicator --}}
                <div wire:loading wire:target="exportPdf, exportExcel" class="mb-4">
                    <x-alert title="Generating File..." icon="o-arrow-path" class="alert-info" />
                </div>

                <x-table :headers="$this->headers" :rows="\App\Models\User::where('role', 'viewer')
                ->where(function($query) {
                    $query->where('email', 'like', '%@staff.id');
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
                            <x-button icon="o-trash" wire:click="deleteStaff({{ $user->id }})" wire:confirm="Hapus viewer ini?" class="btn-ghost btn-sm text-error" />
                            <x-button icon="o-arrow-right-on-rectangle" wire:click="loginAs({{ $user->id }})" class="btn-ghost btn-sm text-success" />
                        </div>
                    @endscope
                </x-table>
            </x-card>

            <x-modal wire:model="staffModal" title="Tambah Viewer Baru" separator>
                <x-input label="Nama Lengkap" wire:model="staffName" placeholder="Nama viewer..." hint="Otomatis jadi @staff.id" />
                <x-slot:actions>
                    <x-button label="Batal" wire:click="$set('staffModal', false)" />
                    <x-button label="Simpan" wire:click="saveViewer" class="btn-primary" />
                </x-slot:actions>
            </x-modal>
        </div>
        HTML;
    }
}; ?>