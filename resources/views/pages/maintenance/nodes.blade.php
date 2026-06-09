<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use App\Models\BEMS\Room;
use App\Exports\NodeExport;
use App\Imports\NodeImport;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination, WithFileUploads;

    public string  $search          = '';
    public ?int    $filterBuilding  = null;
    public string  $filterType      = '';
    public bool    $editModal       = false;
    public bool    $deleteModal     = false;
    public bool    $importModal     = false;

    // Edit
    public ?int    $editId          = null;
    public string  $editName        = '';
    public string  $editType        = 'temperature';
    public string  $editTopic       = '';
    public ?int    $editRoom        = null;
    public ?int    $editBuilding    = null;

    // Delete
    public ?int    $deleteId        = null;
    public string  $deleteName      = '';

    // Import
    public $importFile              = null;
    public array   $importErrors    = [];
    public ?int    $importedCount   = null;

    public array $headers = [
        ['key' => 'status',     'label' => ''],
        ['key' => 'name',       'label' => 'Nama Node'],
        ['key' => 'node_type',  'label' => 'Tipe'],
        ['key' => 'room',       'label' => 'Ruangan'],
        ['key' => 'building',   'label' => 'Gedung'],
        ['key' => 'mqtt_topic', 'label' => 'MQTT Topic'],
        ['key' => 'created_at', 'label' => 'Didaftarkan'],
        ['key' => 'creator',    'label' => 'Pendaftar'],
    ];

    public array $nodeTypes = [
        ['id' => 'temperature', 'name' => 'Suhu & Kelembaban'],
        ['id' => 'current',     'name' => 'Arus Listrik'],
        ['id' => 'voltage',     'name' => 'Tegangan'],
        ['id' => 'light',       'name' => 'Cahaya'],
    ];

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedFilterBuilding(): void { $this->resetPage(); }
    public function updatedFilterType(): void     { $this->resetPage(); }
    public function updatedEditName(): void       { $this->generateTopic(); }
    public function updatedEditType(): void       { $this->generateTopic(); }

    private function generateTopic(): void
    {
        if ($this->editName && $this->editType && $this->editRoom) {
            $this->editTopic = "chaka/{$this->editType}/room{$this->editRoom}/" . Str::slug($this->editName);
        }
    }

    #[Computed]
    public function nodes()
    {
        return Node::with(['room.building', 'creator'])
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('mqtt_topic', 'like', "%{$this->search}%")
            )
            ->when($this->filterType,     fn($q) => $q->where('node_type', $this->filterType))
            ->when($this->filterBuilding, fn($q) =>
                $q->whereHas('room', fn($r) => $r->where('building_id', $this->filterBuilding))
            )
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function buildings()
    {
        return Building::orderBy('name')->get()
            ->groupBy(fn($b) => strtolower(trim($b->name)))
            ->map(fn($g) => ['id' => $g->first()->id, 'name' => trim($g->first()->name)])
            ->values()->toArray();
    }

    #[Computed]
    public function editRooms()
    {
        if (!$this->editBuilding) return [];
        return Room::where('building_id', $this->editBuilding)
            ->orderBy('floor')->orderBy('name')
            ->get()
            ->map(fn($r) => ['id' => $r->id, 'name' => "Lt.{$r->floor} — {$r->name}"])
            ->toArray();
    }

    public function openEdit(int $id): void
    {
        $node = Node::with('room.building')->findOrFail($id);
        $this->editId       = $node->id;
        $this->editName     = $node->name;
        $this->editType     = $node->node_type;
        $this->editTopic    = $node->mqtt_topic;
        $this->editRoom     = $node->room_id;
        $this->editBuilding = $node->room?->building_id;
        $this->editModal    = true;
    }

    public function updateNode(): void
    {
        $this->validate([
            'editName'  => 'required|min:3|max:100',
            'editType'  => 'required|in:temperature,current,voltage,light',
            'editTopic' => "required|unique:bems_nodes,mqtt_topic,{$this->editId}",
        ]);

        Node::findOrFail($this->editId)->update([
            'name'       => $this->editName,
            'node_type'  => $this->editType,
            'mqtt_topic' => $this->editTopic,
        ]);

        $this->success("Node '{$this->editName}' berhasil diperbarui!");
        $this->editModal = false;
        unset($this->nodes);
    }

    public function confirmDelete(int $id): void
    {
        $node              = Node::findOrFail($id);
        $this->deleteId    = $id;
        $this->deleteName  = $node->name;
        $this->deleteModal = true;
    }

    public function deleteNode(): void
    {
        Node::findOrFail($this->deleteId)->delete();
        $this->success("Node '{$this->deleteName}' berhasil dihapus.");
        $this->deleteModal = false;
        unset($this->nodes);
    }

    // ── EXPORT ──────────────────────────────────────────
    public function exportExcel(): mixed
    {
        return Excel::download(
            new NodeExport($this->search, $this->filterType, $this->filterBuilding),
            'Node_Inventory_' . now()->format('d-m-Y') . '.xlsx'
        );
    }

    public function exportPdf(): void
    {
        $nodes = Node::with(['room.building', 'creator'])
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
            )
            ->when($this->filterType,     fn($q) => $q->where('node_type', $this->filterType))
            ->when($this->filterBuilding, fn($q) =>
                $q->whereHas('room', fn($r) => $r->where('building_id', $this->filterBuilding))
            )
            ->latest()->get();

        $tipeMap = ['temperature'=>'Suhu','current'=>'Arus','voltage'=>'Tegangan','light'=>'Cahaya'];

        $html  = '<style>';
        $html .= 'body{font-family:sans-serif;font-size:10px}';
        $html .= 'h2{text-align:center;font-size:13px;margin-bottom:2px}';
        $html .= 'p.sub{text-align:center;color:#888;margin:0 0 12px;font-size:9px}';
        $html .= 'table{width:100%;border-collapse:collapse}';
        $html .= 'th,td{border:1px solid #ddd;padding:5px 7px;text-align:left}';
        $html .= 'th{background:#16a34a;color:#fff;font-size:9px;text-transform:uppercase}';
        $html .= 'tr:nth-child(even){background:#f9fafb}';
        $html .= '.aktif{color:#16a34a;font-weight:bold}.nonaktif{color:#6b7280}';
        $html .= '.mono{font-family:monospace;font-size:8px;color:#059669}';
        $html .= '</style>';
        $html .= '<h2>LAPORAN NODE INVENTORY</h2>';
        $html .= '<p class="sub">Tanggal Cetak: ' . now()->format('d/m/Y H:i') . ' &nbsp;|&nbsp; Total: ' . $nodes->count() . ' node</p>';
        $html .= '<table><thead><tr>';
        $html .= '<th>No</th><th>Nama Node</th><th>Tipe</th><th>Gedung</th><th>Ruangan</th>';
        $html .= '<th>Pendaftar</th><th>MQTT Topic</th><th>Broker</th><th>Status</th><th>Didaftarkan</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($nodes as $i => $node) {
            $status = $node->status ? '<span class="aktif">Aktif</span>' : '<span class="nonaktif">Nonaktif</span>';
            $html .= '<tr>';
            $html .= '<td>' . ($i+1) . '</td>';
            $html .= '<td>' . e($node->name) . '</td>';
            $html .= '<td>' . ($tipeMap[$node->node_type] ?? $node->node_type) . '</td>';
            $html .= '<td>' . e($node->room?->building?->name ?? '-') . '</td>';
            $html .= '<td>' . e($node->room?->name ?? '-') . '</td>';
            $html .= '<td>' . e($node->creator?->name ?? '-') . '</td>';
            $html .= '<td class="mono">' . e($node->mqtt_topic) . '</td>';
            $html .= '<td class="mono">' . e(($node->config['broker'] ?? '-') . ':' . ($node->config['port'] ?? 1883)) . '</td>';
            $html .= '<td>' . $status . '</td>';
            $html .= '<td>' . $node->created_at->format('d/m/Y') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        $this->dispatch('open-pdf', pdfBase64: base64_encode($pdf->output()));
    }

    public function downloadTemplate(): mixed
    {
        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Template_Import_Node.xlsx"',
        ];

        return Excel::download(new class implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings,
            \Maatwebsite\Excel\Concerns\WithStyles,
            \Maatwebsite\Excel\Concerns\ShouldAutoSize
        {
            public function array(): array {
                return [
                    ['Sensor Suhu Server', 'temperature', 'NamaGedung', 'NamaRuangan', 'broker.emqx.io', '1883'],
                    ['Sensor Arus Panel', 'current',      'NamaGedung', 'NamaRuangan', 'broker.emqx.io', '1883'],
                ];
            }
            public function headings(): array {
                return ['nama_node', 'tipe_sensor', 'gedung', 'ruangan', 'broker', 'port'];
            }
            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array {
                return [
                    1 => ['font' => ['bold' => true],
                          'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                     'startColor' => ['argb' => 'FF16A34A']]],
                ];
            }
        }, 'Template_Import_Node.xlsx');
    }

    // ── IMPORT ──────────────────────────────────────────
    public function importExcel(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls,csv']);

        $import = new NodeImport();
        Excel::import($import, $this->importFile);

        $this->importedCount = $import->imported;
        $this->importErrors  = $import->errors;

        if ($import->imported > 0) {
            $this->success("{$import->imported} node berhasil diimport!");
        }
        if (empty($import->errors)) {
            $this->importModal = false;
            $this->importFile  = null;
        }

        unset($this->nodes);
    }
}; ?>

<div>
    <x-header
        title="Node Inventory"
        subtitle="Semua node sensor yang terdaftar di sistem"
        separator
        progress-indicator
    />

    {{-- FILTER BAR --}}
    <div class="flex items-center gap-3 mb-4 flex-wrap">
        <x-input
            wire:model.live.debounce.400ms="search"
            placeholder="Cari nama / MQTT topic..."
            icon="o-magnifying-glass"
            class="flex-1 min-w-[200px]"
            clearable
        />
        <x-select wire:model.live="filterBuilding" :options="$this->buildings" placeholder="Semua Gedung" class="w-44" />
        <x-select wire:model.live="filterType"     :options="$nodeTypes"       placeholder="Semua Tipe"   class="w-40" />
        @if($search || $filterBuilding || $filterType)
        <x-button label="Reset" wire:click="$set('search',''); $set('filterBuilding', null); $set('filterType', '')" class="btn-ghost btn-sm" />
        @endif

        <div class="ml-auto flex gap-2">
            {{-- Import --}}
            <x-button label="Import" icon="o-arrow-up-tray" wire:click="$set('importModal', true)" class="btn-outline btn-sm" />
            {{-- Export --}}
            <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                <x-menu-item title="Export Excel" icon="o-table-cells"   wire:click="exportExcel" class="text-success" />
                <x-menu-item title="Export PDF"   icon="o-document-text" wire:click="exportPdf"   class="text-error"   />
            </x-dropdown>
        </div>
    </div>

    {{-- Loading export --}}
    <div wire:loading wire:target="exportPdf,exportExcel" class="mb-3">
        <x-alert title="Membuat file..." icon="o-arrow-path" class="alert-info" />
    </div>

    {{-- TABLE --}}
    <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
        <x-table
            :headers="$headers"
            :rows="$this->nodes"
            with-pagination
            class="
                [&_thead]:bg-zinc-50 [&_thead]:dark:bg-zinc-800
                [&_th]:text-zinc-500 [&_th]:dark:text-zinc-400 [&_th]:text-xs [&_th]:uppercase
                [&_tbody_tr]:border-zinc-100 [&_tbody_tr]:dark:border-zinc-800
                [&_tbody_tr:hover]:bg-zinc-50 [&_tbody_tr:hover]:dark:bg-zinc-800/50
            "
        >
            @scope('cell_status', $node)
                <div class="flex justify-center">
                    <span class="w-2.5 h-2.5 rounded-full {{ $node->status ? 'bg-green-500 shadow-sm shadow-green-500/50' : 'bg-zinc-400' }}"></span>
                </div>
            @endscope

            @scope('cell_node_type', $node)
                @php $badge = match($node->node_type) {
                    'temperature' => ['Suhu',     'badge-info'],
                    'current'     => ['Arus',     'badge-warning'],
                    'voltage'     => ['Tegangan', 'badge-error'],
                    'light'       => ['Cahaya',   'badge-success'],
                    default       => [$node->node_type, 'badge-ghost'],
                }; @endphp
                <x-badge value="{{ $badge[0] }}" class="{{ $badge[1] }} badge-outline badge-sm" />
            @endscope

            @scope('cell_room', $node)
                <span class="text-sm">{{ $node->room?->name ?? '-' }}</span>
            @endscope

            @scope('cell_building', $node)
                <span class="text-sm">{{ $node->room?->building?->name ?? '-' }}</span>
            @endscope

            @scope('cell_mqtt_topic', $node)
                <code class="text-[11px] text-green-600 dark:text-green-400 bg-green-500/5 px-2 py-0.5 rounded-lg">{{ $node->mqtt_topic }}</code>
            @endscope

            @scope('cell_created_at', $node)
                <span class="text-xs text-zinc-400">{{ $node->created_at->format('d M Y') }}</span>
            @endscope

            @scope('cell_creator', $node)
                @if($node->creator)
                <div>
                    <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $node->creator->name }}</p>
                    <p class="text-[10px] text-zinc-400">{{ $node->creator->email }}</p>
                </div>
                @else
                <span class="text-xs text-zinc-400">—</span>
                @endif
            @endscope

            @scope('actions', $node)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" wire:click="openEdit({{ $node->id }})"    class="btn-ghost btn-sm hover:bg-blue-500/10 hover:text-blue-500" />
                    <x-button icon="o-trash"  wire:click="confirmDelete({{ $node->id }})" class="btn-ghost btn-sm hover:bg-red-500/10  hover:text-red-500"  />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- EDIT MODAL --}}
    <x-modal wire:model="editModal" title="Edit Node" separator class="bg-white dark:bg-zinc-900 rounded-2xl">
        <div class="space-y-4">
            <x-input  label="Nama Node"   wire:model.live="editName"  icon="o-cpu-chip" />
            <x-select label="Tipe Sensor" wire:model.live="editType"  :options="$nodeTypes" icon="o-tag" />
            <x-input  label="MQTT Topic"  wire:model="editTopic"      icon="o-signal" class="font-mono text-sm" readonly />
        </div>
        <x-slot:actions>
            <x-button label="Batal"  wire:click="$set('editModal', false)" />
            <x-button label="Simpan" wire:click="updateNode" spinner="updateNode" class="btn-primary" />
        </x-slot:actions>
    </x-modal>

    {{-- DELETE MODAL --}}
    <x-modal wire:model="deleteModal" title="Hapus Node" class="bg-white dark:bg-zinc-900 rounded-2xl">
        <p class="text-zinc-600 dark:text-zinc-300">Yakin hapus node <span class="font-bold text-red-500">{{ $deleteName }}</span>?</p>
        <p class="text-xs text-zinc-400 mt-1">Tindakan ini tidak bisa dibatalkan.</p>
        <x-slot:actions>
            <x-button label="Batal"  wire:click="$set('deleteModal', false)" />
            <x-button label="Hapus"  wire:click="deleteNode" spinner="deleteNode" class="btn-error" />
        </x-slot:actions>
    </x-modal>

    {{-- IMPORT MODAL --}}
    <x-modal wire:model="importModal" title="Import Node dari Excel" separator class="bg-white dark:bg-zinc-900 rounded-2xl">
        <div class="space-y-4">
            {{-- Download template --}}
            <div class="flex items-center justify-between p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                <div>
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Template Excel</p>
                    <p class="text-xs text-zinc-400">Download template sebelum mengisi data</p>
                </div>
                <x-button label="Download" icon="o-arrow-down-tray" wire:click="downloadTemplate" class="btn-ghost btn-sm" />
            </div>

            {{-- Upload --}}
            <x-file wire:model="importFile" label="Pilih File Excel" hint="Format: .xlsx atau .xls" accept=".xlsx,.xls,.csv" />

            {{-- Errors --}}
            @if(count($importErrors) > 0)
            <div class="rounded-xl border border-red-500/30 bg-red-500/5 p-3 max-h-40 overflow-y-auto">
                <p class="text-xs font-semibold text-red-500 mb-2">{{ count($importErrors) }} baris gagal diimport:</p>
                @foreach($importErrors as $err)
                <p class="text-xs text-red-400">• {{ $err }}</p>
                @endforeach
            </div>
            @endif

            @if($importedCount !== null)
            <x-alert title="{{ $importedCount }} node berhasil diimport." class="alert-success" />
            @endif
        </div>
        <x-slot:actions>
            <x-button label="Batal"  wire:click="$set('importModal', false)" />
            <x-button label="Import" wire:click="importExcel" spinner="importExcel" class="btn-primary" :disabled="!$importFile" />
        </x-slot:actions>
    </x-modal>

    {{-- PDF via JS --}}
    <script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('open-pdf', (event) => {
            const base64 = event[0]?.pdfBase64 || event.pdfBase64;
            if (!base64) return;
            const bytes  = atob(base64);
            const arr    = new Uint8Array([...bytes].map(c => c.charCodeAt(0)));
            const url    = URL.createObjectURL(new Blob([arr], { type: 'application/pdf' }));
            const win    = window.open(url, '_blank');
            if (!win) alert('Izinkan popup blocker untuk melihat PDF.');
        });
    });
    </script>
</div>