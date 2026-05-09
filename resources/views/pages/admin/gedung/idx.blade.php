<?php

use Livewire\Component;
use App\Models\BEMS\Room;
use Livewire\WithPagination;
use App\Models\BEMS\Building;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Mary\Traits\Toast;

new class extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public array $chartData = [];
    public array $barChartData = [];

    public bool $editModal = false;
    public $editRoomId;
    public $editRoomName;
    public $editRoomFloor;
    public $editBuildingId;
    public $editBuildingName;

    public array $headers = [
        ['key' => 'building.name', 'label' => 'Nama Gedung'],
        ['key' => 'floor', 'label' => 'Lantai'],
        ['key' => 'name', 'label' => 'Nama Ruangan'],
        ['key' => 'building.client.name', 'label' => 'Client Pemilik'],
        ['key' => 'action', 'label' => 'Aksi'],
    ];

    public function editRoom($roomId) {
        $room = Room::with('building')->find($roomId);
        if ($room) {
            $this->editRoomId = $room->id;
            $this->editRoomName = $room->name;
            $this->editRoomFloor = $room->floor;
            $this->editBuildingId = $room->building_id;
            $this->editBuildingName = $room->building->name ?? '';
            $this->editModal = true;
        }
    }

    public function updateRoom() {
        $this->validate([
            'editBuildingName' => 'required',
            'editRoomName' => 'required', 
            'editRoomFloor' => 'required'
        ]);
        
        Room::where('id', $this->editRoomId)->update([
            'name' => $this->editRoomName, 
            'floor' => $this->editRoomFloor
        ]);

        if ($this->editBuildingId) {
            Building::where('id', $this->editBuildingId)->update(['name' => $this->editBuildingName]);
        }

        $this->success('Data ruangan dan gedung berhasil diupdate!');
        $this->editModal = false;
    }

    // Reset pagination ketika melakukan pencarian
    public function updatedSearch()
    {
        $this->resetPage();
    }

    // --- FUNGSI EXPORT EXCEL ---
    public function exportExcel() {
        return Excel::download(new class($this->search) implements \Maatwebsite\Excel\Concerns\FromQuery, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping {
            public function __construct(public string $search) {}

            public function query() {
                return Room::query()->with(['building.client'])->when($this->search, function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhereHas('building', function ($q) {
                            $q->where('name', 'like', '%' . $this->search . '%')
                            ->orWhereHas('client', function ($cq) {
                                $cq->where('name', 'like', '%' . $this->search . '%');
                            });
                        });
                });
            }
            public function map($room): array {
                return [
                    $room->building->client->name ?? '-',
                    $room->building->name ?? '-',
                    $room->floor,
                    $room->name
                ];
            }
            public function headings(): array { 
                return ['Nama Gedung', 'Lantai', 'Nama Ruangan','Client Pemilik']; 
            }
        }, 'Data_Gedung_Ruangan_' . now()->format('d-m-Y') . '.xlsx');
    }

    // --- FUNGSI EXPORT PDF ---
    public function exportPdf() {
        $rooms = Room::with(['building.client'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhereHas('building', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhereHas('client', function ($cq) {
                            $cq->where('name', 'like', '%' . $this->search . '%');
                        });
                    });
            })->latest()->get();
        
        $html = "
            <style>
                body { font-family: sans-serif; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                h2 { text-align: center; }
            </style>
            <h2>LAPORAN DATA GEDUNG & RUANGAN</h2>
            <p>Tanggal Cetak: " . now()->format('d/m/Y H:i') . "</p>
            <table>
                <thead>
                    <tr>
                        <th>Nama Gedung</th>
                        <th>Lantai</th>
                        <th>Nama Ruangan</th>
                        <th>Client Pemilik</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach($rooms as $r) {
            $clientName = $r->building->client->name ?? '-';
            $buildingName = $r->building->name ?? '-';
            $html .= "<tr>
                <td>{$buildingName}</td>
                <td>{$r->floor}</td>
                <td>{$r->name}</td>
                <td>{$clientName}</td>
            </tr>";
        }
        $html .= "</tbody></table>";

        $pdf = Pdf::loadHTML($html);
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Data_Gedung_Ruangan_' . now()->format('d-m-Y') . '.pdf');
    }

    public function render()
    {
        // Data untuk Tabel dengan fitur pencarian
        $rooms = Room::with(['building.client'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%') // cari nama ruangan
                    ->orWhereHas('building', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%') // cari nama gedung
                        ->orWhereHas('client', function ($cq) {
                            $cq->where('name', 'like', '%' . $this->search . '%'); // cari nama client
                        });
                    });
            })
            ->latest()
            ->paginate(15);

        // Data untuk Pie Chart (Distribusi Gedung berdasarkan Nama Gedung)
        $buildingStats = Building::select('name', DB::raw('count(*) as count_building'))
            ->groupBy('name')
            ->get();

        $this->chartData = [
            'type' => 'pie',
            'data' => [
                'labels' => $buildingStats->pluck('name')->all(),
                'datasets' => [
                    [
                        'label' => 'Jumlah Terdaftar',
                        'data' => $buildingStats->pluck('count_building')->all(),
                    ]
                ]
            ]
        ];

        $this->barChartData = [
            'type' => 'bar',
            'data' => [
                'labels' => $buildingStats->pluck('name')->all(),
                'datasets' => [
                    [
                        'label' => 'Jumlah Terdaftar',
                        'data' => $buildingStats->pluck('count_building')->all(),
                    ]
                ]
            ]
        ];

        // Gunakan $this->view untuk me-passing data ke template HTML di bawah
        return $this->view(['rooms' => $rooms]);
    }
};
?>

<div>
    <x-header title="Admin | Manajemen Gedung" subtitle="Daftar semua gedung dan ruangan dari semua client" separator progress-indicator>
        <x-slot:actions>
            <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                <x-menu-item title="Export PDF" icon="o-document-text" wire:click="exportPdf" class="text-error" />
                <x-menu-item title="Export Excel" icon="o-table-cells" wire:click="exportExcel" class="text-success" />
            </x-dropdown>
        </x-slot:actions>
    </x-header>

    {{-- CHARTS SECTION --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <x-card title="Proporsi Gedung (Pie)" shadow>
            <div class="w-10 mx-auto mt-0 cursor-pointer" title="Klik area chart untuk memfilter tabel">
                 <x-chart wire:model="chartData" />
            </div>
        </x-card>
        <x-card title="Jumlah Gedung (Bar)" shadow>
            {{-- Bar chart dibatasi lebarnya agar ringkas --}}
            <div class="w-full max-w-xs mx-auto mt-4 cursor-pointer" title="Klik batang chart untuk memfilter tabel">
                 <x-chart wire:model="barChartData" />
            </div>
        </x-card>
    </div>

    {{-- TABLE SECTION --}}
    <x-card shadow>
        <div class="mb-4">
            <x-input icon="o-magnifying-glass" wire:model.live.debounce.500ms="search" placeholder="Cari gedung, ruangan, atau client..." class="w-full sm:max-w-xs" clearable />
        </div>

        {{-- Loading Indicator --}}
        <div wire:loading wire:target="exportPdf, exportExcel" class="mb-4">
            <x-alert title="Generating File..." icon="o-arrow-path" class="alert-info" />
        </div>
        {{-- $headers bisa langsung dipanggil tanpa $this-> karena bersifat public --}}
        <x-table :headers="$headers" :rows="$rooms" with-pagination>
            @scope('cell_action', $room)
                <x-button icon="o-pencil" wire:click="editRoom({{ $room->id }})" class="btn-ghost btn-sm text-info" />
            @endscope
        </x-table>
    </x-card>

    {{-- EDIT MODAL --}}
    <x-modal wire:model="editModal" title="Edit Data Infrastruktur" separator>
        <x-input label="Nama Gedung" wire:model="editBuildingName" class="mb-4" />
        <x-input label="Lantai" wire:model="editRoomFloor" class="mb-4" />
        <x-input label="Nama Ruangan" wire:model="editRoomName" />
        <x-slot:actions>
            <x-button label="Batal" wire:click="$set('editModal', false)" />
            <x-button label="Simpan" wire:click="updateRoom" class="btn-primary" />
        </x-slot:actions>
    </x-modal>

    {{-- SCRIPT UNTUK MENANGKAP KLIK PADA CHART JS --}}
    <script>
        document.addEventListener('click', function(e) {
            const canvas = e.target.closest('canvas');
            if (canvas) {
                const chart = Chart.getChart(canvas);
                if (chart) {
                    const elements = chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, true);
                    if (elements.length) {
                        const firstPoint = elements[0];
                        const label = chart.data.labels[firstPoint.index];
                        
                        @this.set('search', label);
                    }
                }
            }
        });
    </script>
</div>