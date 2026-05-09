<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\BEMS\Client;
use Mary\Traits\Toast;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Imports\ClientImport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use Toast, WithPagination, WithFileUploads;

    public string $search = '';
    
    public bool $importModal = false;
    public $importFile;

    // Otomatis mereset ke halaman 1 saat mengetik pencarian
    public function updatedSearch() {
        $this->resetPage();
    }

    public $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-1/12 hidden'],
        ['key' => 'code', 'label' => 'Code', 'class' => 'w-1/12'],
        ['key' => 'name', 'label' => 'Name', 'class' => 'w-4/12'],
        ['key' => 'user_id', 'label' => 'UserId', 'class' => 'w-2/12'],
        ['key' => 'expirity', 'label' => 'Expirity', 'class' => 'w-2/12', 'format' => ['date', 'd/m/Y']],
        ['key' => 'remain', 'label' => 'Remain', 'class' => 'w-2/12'],
        ['key' => 'action', 'label' => 'Action', 'class' => 'w-2/12'],
    ];

    #[On('refreshIndexClient')]
    function render(){
        $clients = Client::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        $clients->getCollection()->transform(function ($client) {
            if ($client->expirity) {
                $client->remain = (int) Carbon::now()->diffInDays(Carbon::parse($client->expirity), false);
                $client->remain = round($client->remain);
            } else {
                $client->remain = 0;
            }
            return $client;
        });
        return $this->view(['clients' => $clients]);
    }

    // Export Excel menggunakan Maatwebsite
    public function exportExcel() {
        return Excel::download(new class($this->search) implements \Maatwebsite\Excel\Concerns\FromQuery, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping {
            public function __construct(public string $search) {}
            
            public function query() {
                return Client::query()
                    ->when($this->search, function ($query) {
                        $query->where('name', 'like', '%' . $this->search . '%')
                              ->orWhere('code', 'like', '%' . $this->search . '%');
                    });
            }
            public function map($client): array {
                return [$client->code, $client->name, $client->expirity];
            }
            public function headings(): array { return ['Code', 'Client Name', 'Expirity']; }
        }, 'Data_Client_' . now()->format('d-m-Y') . '.xlsx');
    }

    // --- FUNGSI EXPORT PDF (DOMPDF VERSION) ---
    public function exportPdf() {
        $clients = Client::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%');
            })->get();
        
        // Buat HTML-nya
        $html = "
            <style>
                body { font-family: sans-serif; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                h2 { text-align: center; }
            </style>
            <h2>LAPORAN DATA CLIENT BEMS</h2>
            <p>Tanggal Cetak: " . now()->format('d/m/Y H:i') . "</p>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Expirity</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach($clients as $c) {
            $html .= "<tr>
                <td>{$c->code}</td>
                <td>{$c->name}</td>
                <td>" . (\Illuminate\Support\Carbon::parse($c->expirity)->format('d/m/Y')) . "</td>
            </tr>";
        }
        $html .= "</tbody></table>";

        // Logic download DomPDF
        $pdf = Pdf::loadHTML($html);
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Data_Client_' . now()->format('d-m-Y') . '.pdf');
    }

    public function deleteClient($clientId){
        DB::transaction(function () use ($clientId) {
            $client = Client::find($clientId);
            if ($client){
                User::find($client->user_id)?->delete();
                $client->delete();
                $this->success('Client data has been deleted!');
            }
        });
    }

    public function loginAs($clientId){
        $client = Client::find($clientId);
        $user = User::find($client->user_id);
        if($user) {
            Auth::login($user);
            return redirect()->route('client');
        }
        $this->error('User not found!');
    }

    // --- FUNGSI DOWNLOAD TEMPLATE EXCEL ---
    public function downloadTemplate() {
        return Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function array(): array {
                return [
                    ['FPBS01', 'Fakultas Pendidikan Bahasa', '2025-12-31'],
                ];
            }
            public function headings(): array { return ['code', 'name', 'expirity']; }
        }, 'Template_Import_Client.xlsx');
    }

    // --- FUNGSI PROSES IMPORT ---
    public function importClient() {
        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls']);
        try {
            Excel::import(new ClientImport, $this->importFile->getRealPath());
            $this->success('Data client berhasil diimport!');
            $this->importModal = false;
            $this->reset('importFile');
        } catch (\Exception $e) {
            $this->error('Gagal import: ' . $e->getMessage());
        }
    }
}; ?>

<div>
    <x-card title="Admin | Client Management" shadow separator>
        
        <x-slot:actions>
            {{-- Tombol PDF, Excel, dan Add Client sejajar di pojok kanan atas --}}
            <x-button label="Import" icon="o-arrow-up-tray" wire:click="$set('importModal', true)" class="btn-outline btn-info btn-sm" />
            <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                <x-menu-item title="Export PDF" icon="o-document-text" wire:click="exportPdf" class="text-error" />
                <x-menu-item title="Export Excel" icon="o-table-cells" wire:click="exportExcel" class="text-success" />
            </x-dropdown>
            <x-button label="Add Client" icon="o-plus" wire:click="$dispatch('toggleCreateClient')" class="btn-primary btn-sm" />
        </x-slot:actions>

        {{-- Panggil komponen create & edit di sini --}}
        <livewire:pages::admin.client.create />
        <livewire:pages::admin.client.edit />
        
        <div class="mt-2">
            {{-- Form pencarian realtime kita pindahkan ke sini agar muncul di atas tabel --}}
            <div class="mb-4">
                <x-input icon="o-magnifying-glass" wire:model.live.debounce.500ms="search" placeholder="Cari nama atau kode client..." class="w-full sm:max-w-xs" clearable />
            </div>

            {{-- Loading Indicator --}}
            <div wire:loading wire:target="exportPdf, exportExcel, importClient" class="mb-4">
                <x-alert title="Generating File..." icon="o-arrow-path" class="alert-info" />
            </div>

            <x-table :headers="$headers" :rows="$clients" with-pagination>
                @scope('cell_remain', $client)
                    <span class="{{ $client->remain < 7 ? 'text-error' : 'text-success' }} font-bold">
                        {{ $client->remain }} Days
                    </span>
                @endscope

                @scope('cell_action', $client)
                    <div class="flex gap-2">
                        <x-button wire:click="$dispatch('enableEditClient', {clientId: {{$client->id}} })" icon="o-pencil" class="btn-circle btn-sm btn-outline text-info" />
                        <x-button wire:click="deleteClient({{$client->id}})" wire:confirm="Yakin ingin menghapus client ini?" icon="o-trash" class="btn-circle btn-sm btn-outline text-error" />
                        <x-button wire:click="loginAs({{$client->id}})" icon="o-user-circle" class="btn-circle btn-sm btn-outline text-success" />
                    </div>
                @endscope
            </x-table>
        </div>
    </x-card>

    {{-- Modal Import Excel --}}
    <x-modal wire:model="importModal" title="Import Client Excel" class="backdrop-blur">
        <div class="mb-6 text-sm text-gray-600">
            Silakan unduh template Excel di bawah ini, isi data client baru sesuai format tabel, lalu unggah kembali untuk dimasukkan ke dalam sistem.
            <br>
            <x-button label="Download Template" icon="o-arrow-down-tray" wire:click="downloadTemplate" class="btn-sm btn-ghost mt-2" wire:loading.attr="disabled" />
        </div>
        
        <x-file wire:model="importFile" label="Upload File Excel" accept=".xlsx, .xls" />
        
        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('importModal', false)" class="btn-ghost" />
            <x-button label="Import Data" wire:click="importClient" class="btn-primary" spinner="importClient" />
        </x-slot:actions>
    </x-modal>

</div>