<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use App\Services\MqttService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination, WithFileUploads;

    public string $search         = '';
    public ?int   $filterBuilding = null;
    public string $filterType     = '';
    public string $filterStatus   = '';
    public array  $liveData       = [];
    public ?int   $previewNodeId  = null;

    public array $headers = [
        ['key' => 'status',     'label' => 'Status'],
        ['key' => 'name',       'label' => 'Nama Node'],
        ['key' => 'node_type',  'label' => 'Tipe'],
        ['key' => 'building',   'label' => 'Gedung'],
        ['key' => 'room',       'label' => 'Ruangan'],
        ['key' => 'pendaftar',  'label' => 'Pendaftar'],
        ['key' => 'last_data',  'label' => 'Data Terakhir'],
    ];

    public array $nodeTypes = [
        ['id' => 'temperature', 'name' => 'Suhu & Kelembaban'],
        ['id' => 'current',     'name' => 'Arus Listrik'],
        ['id' => 'voltage',     'name' => 'Tegangan'],
        ['id' => 'light',       'name' => 'Cahaya'],
    ];

    public array $statusOptions = [
        ['id' => '1', 'name' => 'Aktif'],
        ['id' => '0', 'name' => 'Nonaktif'],
    ];

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedFilterBuilding(): void { $this->resetPage(); }
    public function updatedFilterType(): void     { $this->resetPage(); }
    public function updatedFilterStatus(): void   { $this->resetPage(); }

    #[Computed]
    public function nodes()
    {
        return Node::with(['room.building', 'creator'])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterType, fn($q) => $q->where('node_type', $this->filterType))
            ->when($this->filterStatus !== '', fn($q) => $q->where('status', (bool)$this->filterStatus))
            ->when($this->filterBuilding, fn($q) =>
                $q->whereHas('room', fn($r) => $r->where('building_id', $this->filterBuilding))
            )
            ->latest()
            ->paginate(12);
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
    public function summary()
    {
        return [
            'total'    => Node::count(),
            'active'   => Node::where('status', true)->count(),
            'inactive' => Node::where('status', false)->count(),
        ];
    }

    public function toggleNode(int $id): void
    {
        $node      = Node::findOrFail($id);
        $newStatus = !$node->status;
        $node->update(['status' => $newStatus]);

        if ($newStatus) {
            $mqtt    = new MqttService();
            $success = $mqtt->publishDummy($node);
            $this->liveData[$id] = $mqtt->generateDummyPayload($node->node_type);
            $this->previewNodeId = $id;
            $msg = $success ? "Node '{$node->name}' diaktifkan & data dummy dikirim ke MQTT."
                            : "Node '{$node->name}' diaktifkan. Data dummy disimulasikan lokal.";
            $this->success($msg);
        } else {
            $this->warning("Node '{$node->name}' dinonaktifkan.");
            unset($this->liveData[$id]);
            if ($this->previewNodeId === $id) $this->previewNodeId = null;
        }

        unset($this->nodes, $this->summary);
    }

    public function refreshDummy(int $id): void
    {
        $node = Node::findOrFail($id);
        if (!$node->status) { $this->error('Node tidak aktif.'); return; }
        $mqtt = new MqttService();
        $mqtt->publishDummy($node);
        $this->liveData[$id] = $mqtt->generateDummyPayload($node->node_type);
        $this->previewNodeId = $id;
        $this->success("Data dummy di-refresh.");
    }

    // ── EXPORT ──────────────────────────────────────────
    public function exportExcel(): mixed
    {
        $search   = $this->search;
        $type     = $this->filterType;
        $building = $this->filterBuilding;
        $status   = $this->filterStatus;

        return Excel::download(
            new class($search, $type, $building, $status) implements
                \Maatwebsite\Excel\Concerns\FromQuery,
                \Maatwebsite\Excel\Concerns\WithHeadings,
                \Maatwebsite\Excel\Concerns\WithMapping,
                \Maatwebsite\Excel\Concerns\ShouldAutoSize,
                \Maatwebsite\Excel\Concerns\WithStyles
            {
                public function __construct(
                    private string $search,
                    private string $type,
                    private ?int   $building,
                    private string $status,
                ) {}

                public function query()
                {
                    return Node::with(['room.building', 'creator'])
                        ->when($this->search,   fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                        ->when($this->type,     fn($q) => $q->where('node_type', $this->type))
                        ->when($this->status !== '', fn($q) => $q->where('status', (bool)$this->status))
                        ->when($this->building, fn($q) =>
                            $q->whereHas('room', fn($r) => $r->where('building_id', $this->building))
                        )->latest();
                }

                public function headings(): array
                {
                    return ['No', 'Nama Node', 'Tipe', 'Gedung', 'Ruangan', 'Pendaftar', 'Email Pendaftar', 'MQTT Topic', 'Status', 'Didaftarkan'];
                }

                public function map($node): array
                {
                    static $no = 0; $no++;
                    return [
                        $no,
                        $node->name,
                        ucfirst($node->node_type),
                        $node->room?->building?->name ?? '-',
                        $node->room?->name ?? '-',
                        $node->creator?->name ?? '-',
                        $node->creator?->email ?? '-',
                        $node->mqtt_topic,
                        $node->status ? 'Aktif' : 'Nonaktif',
                        $node->created_at->format('d/m/Y H:i'),
                    ];
                }

                public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
                {
                    return [
                        1 => [
                            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                       'startColor' => ['argb' => 'FF2563EB']],
                        ],
                    ];
                }
            },
            'Sensor_Control_' . now()->format('d-m-Y') . '.xlsx'
        );
    }

    public function exportPdf(): void
    {
        $nodes = Node::with(['room.building', 'creator'])
            ->when($this->search,   fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterType, fn($q) => $q->where('node_type', $this->filterType))
            ->when($this->filterStatus !== '', fn($q) => $q->where('status', (bool)$this->filterStatus))
            ->when($this->filterBuilding, fn($q) =>
                $q->whereHas('room', fn($r) => $r->where('building_id', $this->filterBuilding))
            )->latest()->get();

        $pdf = Pdf::loadView('exports.sensor-control-pdf', [
            'nodes'     => $nodes,
            'printedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        $this->dispatch('open-pdf', pdfBase64: base64_encode($pdf->output()));
    }
}; ?>

<div
    x-data="{}"
>
    <x-header
        title="Sensor Control"
        subtitle="Aktifkan atau nonaktifkan sensor & kirim data dummy via MQTT"
        separator
        progress-indicator
    />

    {{-- SUMMARY — BERJAJAR KE SAMPING --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                    <x-icon name="o-server-stack" class="w-6 h-6 text-zinc-500" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->summary['total'] }}</p>
                    <p class="text-xs text-zinc-500">Total Node</p>
                </div>
            </div>
        </x-card>
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-blue-500/10 flex items-center justify-center shrink-0">
                    <x-icon name="o-bolt" class="w-6 h-6 text-blue-500" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-blue-500">{{ $this->summary['active'] }}</p>
                    <p class="text-xs text-zinc-500">Node Aktif</p>
                </div>
            </div>
        </x-card>
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                    <x-icon name="o-pause-circle" class="w-6 h-6 text-zinc-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-zinc-500">{{ $this->summary['inactive'] }}</p>
                    <p class="text-xs text-zinc-500">Node Nonaktif</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- FILTER + EXPORT --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <x-input wire:model.live.debounce.400ms="search" placeholder="Cari node..." icon="o-magnifying-glass" class="flex-1 min-w-[180px]" clearable />
        <x-select wire:model.live="filterBuilding" :options="$this->buildings"  placeholder="Semua Gedung" class="w-40" />
        <x-select wire:model.live="filterType"     :options="$nodeTypes"         placeholder="Semua Tipe"   class="w-40" />
        <x-select wire:model.live="filterStatus"   :options="$statusOptions"     placeholder="Semua Status" class="w-36" />

        <div class="ml-auto flex gap-2">
            <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                <x-menu-item title="Export Excel" icon="o-table-cells"   wire:click="exportExcel" class="text-success" />
                <x-menu-item title="Export PDF"   icon="o-document-text" wire:click="exportPdf"   class="text-error"   />
            </x-dropdown>
        </div>
    </div>

    <div wire:loading wire:target="exportPdf,exportExcel" class="mb-3">
        <x-alert title="Membuat file..." icon="o-arrow-path" class="alert-info" />
    </div>

    <div class="grid gap-4" style="grid-template-columns: {{ $previewNodeId && isset($liveData[$previewNodeId]) ? '1fr 320px' : '1fr' }}">

        {{-- TABLE --}}
        <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <x-table
                :headers="$headers"
                :rows="$this->nodes"
                with-pagination
                class="
                    [&_thead]:bg-zinc-50 [&_thead]:dark:bg-zinc-800
                    [&_th]:text-zinc-500 [&_th]:text-xs [&_th]:uppercase
                    [&_tbody_tr]:border-zinc-100 [&_tbody_tr]:dark:border-zinc-800
                    [&_tbody_tr:hover]:bg-zinc-50 [&_tbody_tr:hover]:dark:bg-zinc-800/50
                "
            >
                @scope('cell_status', $node)
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $node->status ? 'bg-blue-500 animate-pulse' : 'bg-zinc-400' }}"></span>
                        <span class="text-xs {{ $node->status ? 'text-blue-500 font-semibold' : 'text-zinc-400' }}">
                            {{ $node->status ? 'Aktif' : 'Nonaktif' }}
                        </span>
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

                @scope('cell_building', $node)
                    <span class="text-sm">{{ $node->room?->building?->name ?? '-' }}</span>
                @endscope

                @scope('cell_room', $node)
                    <span class="text-sm">{{ $node->room?->name ?? '-' }}</span>
                @endscope

                @scope('cell_pendaftar', $node)
                    @if($node->creator)
                    <div>
                        <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $node->creator->name }}</p>
                        <p class="text-[10px] text-zinc-400">{{ $node->creator->email }}</p>
                    </div>
                    @else
                    <span class="text-xs text-zinc-400">—</span>
                    @endif
                @endscope

                @scope('cell_last_data', $node)
                    @if(isset($liveData[$node->id]))
                        @php $d = $liveData[$node->id]; @endphp
                        <span class="text-xs font-mono text-green-500 font-semibold">
                            @if(isset($d['suhu']))       {{ $d['suhu'] }}°C / {{ $d['kelembaban'] }}%
                            @elseif(isset($d['arus']))   {{ $d['arus'] }} A
                            @elseif(isset($d['tegangan'])) {{ $d['tegangan'] }} V
                            @elseif(isset($d['cahaya'])) {{ $d['cahaya'] }} lux
                            @endif
                        </span>
                    @elseif($node->status)
                        <span class="text-xs text-zinc-400 italic">Klik refresh</span>
                    @else
                        <span class="text-xs text-zinc-500">—</span>
                    @endif
                @endscope

                @scope('actions', $node)
                    <div class="flex gap-1">
                        <x-button
                            wire:click="toggleNode({{ $node->id }})"
                            spinner="toggleNode({{ $node->id }})"
                            icon="{{ $node->status ? 'o-pause' : 'o-play' }}"
                            class="btn-sm {{ $node->status ? 'btn-warning' : 'btn-primary' }}"
                        />
                        @if($node->status)
                        <x-button
                            wire:click="refreshDummy({{ $node->id }})"
                            spinner="refreshDummy({{ $node->id }})"
                            icon="o-arrow-path"
                            class="btn-sm btn-ghost hover:text-blue-500"
                        />
                        @endif
                    </div>
                @endscope
            </x-table>
        </x-card>

        {{-- LIVE PREVIEW --}}
        @if($previewNodeId && isset($liveData[$previewNodeId]))
        @php
            $pNode = $this->nodes->getCollection()->firstWhere('id', $previewNodeId)
                ?? Node::with('room.building')->find($previewNodeId);
            $d = $liveData[$previewNodeId];
        @endphp
        <div>
            <x-card shadow class="bg-white dark:bg-zinc-900 border border-blue-500/30 rounded-2xl sticky top-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest">Live Data Dummy</p>
                        <p class="text-sm font-bold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $pNode?->name }}</p>
                        <p class="text-xs text-zinc-400">{{ $pNode?->room?->building?->name }} › {{ $pNode?->room?->name }}</p>
                    </div>
                    <button wire:click="$set('previewNodeId', null)"
                        class="w-7 h-7 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-center text-zinc-400">
                        <x-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-2">
                    @foreach($d as $key => $val)
                    @if($key !== 'timestamp')
                    <div class="flex justify-between items-center p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800">
                        <span class="text-xs text-zinc-400 capitalize">{{ str_replace('_', ' ', $key) }}</span>
                        <span class="text-sm font-bold text-blue-500 font-mono">{{ $val }}</span>
                    </div>
                    @endif
                    @endforeach
                </div>

                <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800 flex justify-between items-center">
                    <p class="text-[10px] text-zinc-400">
                        {{ isset($d['timestamp']) ? \Carbon\Carbon::parse($d['timestamp'])->format('H:i:s') : now()->format('H:i:s') }}
                    </p>
                    <x-button label="Refresh" icon="o-arrow-path"
                        wire:click="refreshDummy({{ $previewNodeId }})"
                        spinner="refreshDummy({{ $previewNodeId }})"
                        class="btn-ghost btn-xs" />
                </div>

                @if($pNode)
                <div class="mt-3 p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                    <p class="text-[10px] font-semibold text-zinc-400 uppercase mb-1">MQTT Info</p>
                    <p class="font-mono text-[10px] text-green-500 break-all">{{ $pNode->mqtt_topic }}</p>
                    <p class="text-[10px] text-zinc-400 mt-1">
                        {{ $pNode->config['broker'] ?? '127.0.0.1' }}:{{ $pNode->config['port'] ?? 1883 }}
                    </p>
                </div>
                @endif
            </x-card>
        </div>
        @endif

    </div>

    <script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('open-pdf', (event) => {
            const base64 = event[0]?.pdfBase64 || event.pdfBase64;
            if (!base64) return;
            const arr = new Uint8Array([...atob(base64)].map(c => c.charCodeAt(0)));
            const url = URL.createObjectURL(new Blob([arr], { type: 'application/pdf' }));
            const win = window.open(url, '_blank');
            if (!win) alert('Izinkan popup untuk melihat PDF.');
        });
    });
    </script>
</div>