<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use App\Exports\NodeExport;
use App\Imports\NodeImport;
use Livewire\Attributes\Computed;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination, WithFileUploads;

    public string $search         = '';
    public string $filterType     = '';
    public ?int   $filterBuilding = null;
    public string $filterDate     = '';
    public bool   $importModal    = false;
    public $importFile            = null;
    public array  $importErrors   = [];
    public ?int   $importedCount  = null;

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedFilterType(): void     { $this->resetPage(); }
    public function updatedFilterBuilding(): void { $this->resetPage(); }
    public function updatedFilterDate(): void     { $this->resetPage(); }

    #[Computed]
    public function logs()
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
            ->when($this->filterDate, fn($q) => $q->whereDate('created_at', $this->filterDate))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function buildings()
    {
        return Building::orderBy('name')->get()
            ->groupBy(fn($b) => strtolower(trim($b->name)))
            ->map(fn($g) => ['id' => $g->first()->id, 'name' => trim($g->first()->name)])
            ->values()->toArray();
    }

    public array $nodeTypes = [
        ['id' => 'temperature', 'name' => 'Suhu & Kelembaban'],
        ['id' => 'current',     'name' => 'Arus Listrik'],
        ['id' => 'voltage',     'name' => 'Tegangan'],
        ['id' => 'light',       'name' => 'Cahaya'],
    ];

    // ── EXPORT ──────────────────────────────────────────
    public function exportExcel(): mixed
    {
        return Excel::download(
            new NodeExport($this->search, $this->filterType, $this->filterBuilding),
            'Log_Instalasi_' . now()->format('d-m-Y') . '.xlsx'
        );
    }

    public function exportPdf(): void
    {
        $nodes = Node::with('room.building')
            ->when($this->search,         fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterType,     fn($q) => $q->where('node_type', $this->filterType))
            ->when($this->filterBuilding, fn($q) =>
                $q->whereHas('room', fn($r) => $r->where('building_id', $this->filterBuilding))
            )
            ->when($this->filterDate, fn($q) => $q->whereDate('created_at', $this->filterDate))
            ->latest()->get();

        // Tambah eager load creator jika belum
        $typeMap = ['temperature' => 'Suhu', 'current' => 'Arus', 'voltage' => 'Tegangan', 'light' => 'Cahaya'];

        $html  = '<style>';
        $html .= 'body{font-family:sans-serif;font-size:10px;color:#333}';
        $html .= 'h2{text-align:center;font-size:13px;margin-bottom:2px}';
        $html .= 'p.sub{text-align:center;color:#888;margin:0 0 12px;font-size:9px}';
        $html .= 'table{width:100%;border-collapse:collapse}';
        $html .= 'th,td{border:1px solid #ddd;padding:5px 7px;text-align:left}';
        $html .= 'th{background:#16a34a;color:#fff;font-size:9px;text-transform:uppercase}';
        $html .= 'tr:nth-child(even){background:#f9fafb}';
        $html .= '.mono{font-family:monospace;font-size:8px;color:#059669}';
        $html .= '.aktif{color:#16a34a;font-weight:bold}.naktif{color:#6b7280}';
        $html .= '</style>';
        $html .= '<h2>LOG INSTALASI NODE</h2>';
        $html .= '<p class="sub">Tanggal Cetak: ' . now()->format('d/m/Y H:i') . ' | Total: ' . $nodes->count() . ' node</p>';
        $html .= '<table><thead><tr>';
        $html .= '<th>No</th><th>Nama Node</th><th>Tipe</th><th>Gedung</th><th>Ruangan</th>';
        $html .= '<th>Pendaftar</th><th>MQTT Topic</th><th>Status</th><th>Tanggal Daftar</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($nodes as $i => $node) {
            $tipe   = $typeMap[$node->node_type] ?? $node->node_type;
            $status = $node->status ? '<span class="aktif">Aktif</span>' : '<span class="naktif">Nonaktif</span>';
            $html .= '<tr>';
            $html .= '<td>' . ($i+1) . '</td>';
            $html .= '<td>' . e($node->name) . '</td>';
            $html .= '<td>' . $tipe . '</td>';
            $html .= '<td>' . e($node->room?->building?->name ?? '-') . '</td>';
            $html .= '<td>' . e($node->room?->name ?? '-') . '</td>';
            $html .= '<td>' . e($node->creator?->name ?? '-') . '<br><small style="color:#6b7280">' . e($node->creator?->email ?? '') . '</small></td>';
            $html .= '<td class="mono">' . e($node->mqtt_topic) . '</td>';
            $html .= '<td>' . $status . '</td>';
            $html .= '<td>' . $node->created_at->format('d M Y H:i') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        $this->dispatch('open-pdf', pdfBase64: base64_encode($pdf->output()));
    }

    public function downloadTemplate(): mixed
    {
        return Excel::download(
            new class implements
                \Maatwebsite\Excel\Concerns\FromArray,
                \Maatwebsite\Excel\Concerns\WithHeadings,
                \Maatwebsite\Excel\Concerns\ShouldAutoSize,
                \Maatwebsite\Excel\Concerns\WithStyles
            {
                public function array(): array {
                    return [
                        ['Sensor Suhu Server',  'temperature', 'NamaGedung', 'NamaRuangan', 'broker.emqx.io', 1883],
                        ['Sensor Arus Panel',   'current',     'NamaGedung', 'NamaRuangan', 'broker.emqx.io', 1883],
                        ['Sensor Cahaya Lobby', 'light',       'NamaGedung', 'NamaRuangan', 'broker.emqx.io', 1883],
                    ];
                }
                public function headings(): array {
                    return ['nama_node', 'tipe_sensor', 'gedung', 'ruangan', 'broker', 'port'];
                }
                public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                       'startColor' => ['argb' => 'FF16A34A']],
                        ],
                    ];
                }
            },
            'Template_Import_Node.xlsx'
        );
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
        unset($this->logs);
    }
}; ?>

<div>
    <x-header
        title="Log Instalasi"
        subtitle="Riwayat pendaftaran node berdasarkan waktu pemasangan"
        separator
        progress-indicator
    />

    {{-- FILTER + ACTION BAR --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <x-input
            wire:model.live.debounce.400ms="search"
            placeholder="Cari nama / MQTT topic..."
            icon="o-magnifying-glass"
            class="flex-1 min-w-[200px]"
            clearable
        />
        <x-select wire:model.live="filterBuilding" :options="$this->buildings" placeholder="Semua Gedung" class="w-44" />
        <x-select wire:model.live="filterType"     :options="$nodeTypes"       placeholder="Semua Tipe"   class="w-40" />
        <x-input  wire:model.live="filterDate"     type="date"                                            class="w-44" />

        @if($search || $filterBuilding || $filterType || $filterDate)
        <x-button
            label="Reset"
            wire:click="$set('search',''); $set('filterBuilding', null); $set('filterType',''); $set('filterDate','')"
            class="btn-ghost btn-sm"
        />
        @endif

        <div class="ml-auto flex gap-2">
            <x-button label="Import" icon="o-arrow-up-tray" wire:click="$set('importModal', true)" class="btn-outline btn-sm" />
            <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                <x-menu-item title="Export Excel" icon="o-table-cells"   wire:click="exportExcel" class="text-success" />
                <x-menu-item title="Export PDF"   icon="o-document-text" wire:click="exportPdf"   class="text-error"   />
            </x-dropdown>
        </div>
    </div>

    <div wire:loading wire:target="exportPdf,exportExcel" class="mb-3">
        <x-alert title="Membuat file..." icon="o-arrow-path" class="alert-info" />
    </div>

    {{-- LOG LIST --}}
    <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
        @forelse($this->logs as $node)
        @php
            $badge = match($node->node_type) {
                'temperature' => ['Suhu',     'badge-info'],
                'current'     => ['Arus',     'badge-warning'],
                'voltage'     => ['Tegangan', 'badge-error'],
                'light'       => ['Cahaya',   'badge-success'],
                default       => [$node->node_type, 'badge-ghost'],
            };
        @endphp
        <div class="flex items-center gap-4 py-4 border-b border-zinc-100 dark:border-zinc-800 last:border-0">

            {{-- Timeline dot --}}
            <div class="w-3 h-3 rounded-full shrink-0 {{ $node->status ? 'bg-green-500 ring-2 ring-green-500/20' : 'bg-zinc-400 ring-2 ring-zinc-400/20' }}"></div>

            {{-- Info --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $node->name }}</p>
                    <x-badge value="{{ $badge[0] }}" class="{{ $badge[1] }} badge-outline badge-sm" />
                    @if(!$node->status)
                        <x-badge value="Menunggu Aktivasi" class="badge-ghost badge-sm" />
                    @else
                        <x-badge value="Aktif" class="badge-success badge-sm" />
                    @endif
                </div>
                <p class="text-xs text-zinc-400 mt-0.5">
                    <span class="font-medium text-zinc-500">{{ $node->room?->building?->name }}</span>
                    › {{ $node->room?->name }}
                </p>
                <code class="text-[10px] text-green-500 mt-0.5 block">{{ $node->mqtt_topic }}</code>
            </div>

            {{-- MQTT Broker --}}
            @if(!empty($node->config['broker']))
            <div class="hidden md:block text-right shrink-0">
                <p class="text-[11px] font-mono text-zinc-400">{{ $node->config['broker'] }}:{{ $node->config['port'] ?? 1883 }}</p>
                <p class="text-[10px] text-zinc-500">Broker</p>
            </div>
            @endif

            {{-- Waktu --}}
            <div class="text-right shrink-0">
                <p class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ $node->created_at->format('d M Y') }}</p>
                <p class="text-[10px] text-zinc-400">{{ $node->created_at->format('H:i') }} · {{ $node->created_at->diffForHumans() }}</p>
            </div>
        </div>
        @empty
        <div class="py-16 text-center text-zinc-400">
            <x-icon name="o-clipboard-document-list" class="w-12 h-12 mx-auto mb-3 opacity-20" />
            <p class="text-sm font-medium">Belum ada log instalasi.</p>
            <p class="text-xs mt-1">Node yang didaftarkan akan muncul di sini.</p>
        </div>
        @endforelse

        @if($this->logs->hasPages())
        <div class="mt-4">{{ $this->logs->links() }}</div>
        @endif
    </x-card>

    {{-- IMPORT MODAL --}}
    <x-modal wire:model="importModal" title="Import Node dari Excel" separator class="bg-white dark:bg-zinc-900 rounded-2xl">
        <div class="space-y-4">
            <div class="flex items-center justify-between p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                <div>
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Template Excel</p>
                    <p class="text-xs text-zinc-400">Download template sebelum isi data</p>
                </div>
                <x-button label="Download" icon="o-arrow-down-tray" wire:click="downloadTemplate" class="btn-ghost btn-sm" />
            </div>

            <x-file wire:model="importFile" label="Pilih File Excel" hint="Format: .xlsx, .xls, atau .csv" accept=".xlsx,.xls,.csv" />

            @if(count($importErrors) > 0)
            <div class="rounded-xl border border-red-500/30 bg-red-500/5 p-3 max-h-40 overflow-y-auto">
                <p class="text-xs font-semibold text-red-500 mb-2">{{ count($importErrors) }} baris gagal:</p>
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

    <script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('open-pdf', (event) => {
            const base64 = event[0]?.pdfBase64 || event.pdfBase64;
            if (!base64) return;
            const bytes = atob(base64);
            const arr   = new Uint8Array([...bytes].map(c => c.charCodeAt(0)));
            const url   = URL.createObjectURL(new Blob([arr], { type: 'application/pdf' }));
            if (!window.open(url, '_blank')) alert('Izinkan popup untuk melihat PDF.');
        });
    });
    </script>
</div>