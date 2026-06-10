<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use App\Models\BEMS\NodeReading;
use App\Services\MqttService;
use Livewire\Attributes\Computed;

new class extends Component {
    use WithPagination;

    public string $search         = '';
    public ?int   $filterBuilding = null;
    public string $filterType     = '';
    public string $filterStatus   = '';
    public ?int   $selectedNodeId = null;
    public bool   $historyModal   = false;

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

    public array $headers = [
        ['key' => 'status',    'label' => ''],
        ['key' => 'name',      'label' => 'Nama Sensor'],
        ['key' => 'node_type', 'label' => 'Tipe'],
        ['key' => 'building',  'label' => 'Gedung'],
        ['key' => 'room',      'label' => 'Ruangan'],
        ['key' => 'client',    'label' => 'Client'],
        ['key' => 'pendaftar', 'label' => 'Pendaftar'],
        ['key' => 'live_data', 'label' => 'Data Terkini'],
    ];

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedFilterBuilding(): void { $this->resetPage(); }
    public function updatedFilterType(): void     { $this->resetPage(); }
    public function updatedFilterStatus(): void   { $this->resetPage(); }

    #[Computed]
    public function sensors()
    {
        return Node::with(['room.building.client', 'creator'])
            ->when($this->search,        fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterType,    fn($q) => $q->where('node_type', $this->filterType))
            ->when($this->filterStatus !== '', fn($q) => $q->where('status', (bool)$this->filterStatus))
            ->when($this->filterBuilding, fn($q) =>
                $q->whereHas('room', fn($r) => $r->where('building_id', $this->filterBuilding))
            )
            ->latest()->paginate(15);
    }

    #[Computed]
    public function buildings()
    {
        return Building::orderBy('name')->get()
            ->groupBy(fn($b) => strtolower(trim($b->name)))
            ->map(fn($g) => ['id' => $g->first()->id, 'name' => trim($g->first()->name)])
            ->values()->toArray();
    }

    public function getLiveValue(Node $node): string
    {
        $mqtt = new MqttService();
        $d    = $mqtt->generateDummyPayload($node->node_type);
        return match($node->node_type) {
            'temperature' => ($d['suhu'] ?? '—') . '°C / ' . ($d['kelembaban'] ?? '—') . '%',
            'current'     => ($d['arus']     ?? '—') . ' A',
            'voltage'     => ($d['tegangan'] ?? '—') . ' V',
            'light'       => ($d['cahaya']   ?? '—') . ' lux',
            default       => '—',
        };
    }

    public function openHistory(int $id): void
    {
        $this->selectedNodeId = $id;
        $this->historyModal   = true;
    }

    #[Computed]
    public function selectedNode()
    {
        if (!$this->selectedNodeId) return null;
        return Node::with('room.building')->find($this->selectedNodeId);
    }

    #[Computed]
    public function historyReadings()
    {
        if (!$this->selectedNodeId) return collect();
        return NodeReading::where('node_id', $this->selectedNodeId)
            ->latest('read_at')->take(50)->get();
    }

    #[Computed]
    public function historyChartData()
    {
        $readings = $this->historyReadings->reverse()->values();
        $node     = $this->selectedNode;
        if (!$node || $readings->isEmpty()) return [];

        $labels = $readings->map(fn($r) => \Carbon\Carbon::parse($r->read_at)->format('H:i:s'))->toArray();
        $data   = $readings->map(function($r) use ($node) {
            $p = $r->payload;
            return match($node->node_type) {
                'temperature' => $p['suhu']    ?? 0,
                'current'     => $p['arus']     ?? 0,
                'voltage'     => $p['tegangan'] ?? 0,
                'light'       => $p['cahaya']   ?? 0,
                default       => 0,
            };
        })->toArray();

        $unit = match($node->node_type) {
            'temperature'=>'°C','current'=>'A','voltage'=>'V','light'=>'lux',default=>''
        };

        return ['labels' => $labels, 'data' => $data, 'unit' => $unit, 'name' => $node->name];
    }

    // ── Export ────────────────────────────────────────────────────────────────

    /**
     * Simpan filter aktif ke session, lalu redirect ke route download.
     * Pola ini wajib di Livewire v4 karena response file tidak bisa di-return
     * langsung dari method component (di-intercept sebagai JSON Livewire).
     */
    public function exportPdf(): void
    {
        session(['sensor_pdf_filters' => [
            'search'          => $this->search,
            'filterBuilding'  => $this->filterBuilding,
            'filterType'      => $this->filterType,
            'filterStatus'    => $this->filterStatus,
        ]]);

        $this->redirect(route('viewer.sensors.export-pdf'), navigate: false);
    }

    public function exportExcel(): void
    {
        session(['sensor_excel_filters' => [
            'search'          => $this->search,
            'filterBuilding'  => $this->filterBuilding,
            'filterType'      => $this->filterType,
            'filterStatus'    => $this->filterStatus,
        ]]);

        $this->redirect(route('viewer.sensors.export-excel'), navigate: false);
    }
}; ?>

<div>
    <x-header title="List Sensor" subtitle="Semua sensor yang terdaftar di sistem" separator progress-indicator />

    {{-- Loading indicator saat export --}}
    <div wire:loading wire:target="exportPdf,exportExcel" class="mb-3">
        <x-alert title="Menyiapkan file, harap tunggu..." icon="o-arrow-path" class="alert-info" />
    </div>

    {{-- FILTER + EXPORT sejajar --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <x-input wire:model.live.debounce.400ms="search" placeholder="Cari nama sensor..." icon="o-magnifying-glass" class="flex-1 min-w-[180px]" clearable />
        <x-select wire:model.live="filterBuilding" :options="$this->buildings" placeholder="Semua Gedung" class="w-44" />
        <x-select wire:model.live="filterType"     :options="$nodeTypes"        placeholder="Semua Tipe"   class="w-40" />
        <x-select wire:model.live="filterStatus"   :options="$statusOptions"    placeholder="Semua Status" class="w-36" />
        @if($search || $filterBuilding || $filterType || $filterStatus)
        <x-button label="Reset"
            wire:click="$set('search',''); $set('filterBuilding',null); $set('filterType',''); $set('filterStatus','')"
            class="btn-ghost btn-sm" />
        @endif
        {{-- Tombol export sejajar dengan filter --}}
        <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
            <x-menu-item title="Export PDF"   icon="o-document-text" wire:click="exportPdf"   class="text-error" />
            <x-menu-item title="Export Excel" icon="o-table-cells"   wire:click="exportExcel" class="text-success" />
        </x-dropdown>
    </div>

    <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
        <x-table
            :headers="$headers"
            :rows="$this->sensors"
            with-pagination
            class="[&_thead]:bg-zinc-50 [&_thead]:dark:bg-zinc-800 [&_th]:text-zinc-500 [&_th]:text-xs [&_th]:uppercase [&_tbody_tr]:border-zinc-100 [&_tbody_tr]:dark:border-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 [&_tbody_tr:hover]:dark:bg-zinc-800/50"
        >
            @scope('cell_status', $node)
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full {{ $node->status ? 'bg-blue-500 animate-pulse' : 'bg-zinc-400' }}"></span>
                    <span class="text-xs {{ $node->status ? 'text-blue-500' : 'text-zinc-400' }}">{{ $node->status ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
            @endscope

            @scope('cell_node_type', $node)
                @php $b = match($node->node_type) {
                    'temperature' => ['Suhu',     'badge-info'],
                    'current'     => ['Arus',     'badge-warning'],
                    'voltage'     => ['Tegangan', 'badge-error'],
                    'light'       => ['Cahaya',   'badge-success'],
                    default       => [$node->node_type, 'badge-ghost'],
                }; @endphp
                <x-badge value="{{ $b[0] }}" class="{{ $b[1] }} badge-outline badge-sm" />
            @endscope

            @scope('cell_building',  $node) <span class="text-sm">{{ $node->room?->building?->name ?? '-' }}</span> @endscope
            @scope('cell_room',      $node) <span class="text-sm">{{ $node->room?->name ?? '-' }}</span> @endscope
            @scope('cell_client',    $node) <span class="text-sm">{{ $node->room?->building?->client?->name ?? '-' }}</span> @endscope

            @scope('cell_pendaftar', $node)
                @if($node->creator)
                <div>
                    <p class="text-xs font-medium">{{ $node->creator->name }}</p>
                    <p class="text-[10px] text-zinc-400">{{ $node->creator->email }}</p>
                </div>
                @else <span class="text-xs text-zinc-400">—</span>
                @endif
            @endscope

            @scope('cell_live_data', $node)
                @if($node->status)
                <span class="text-xs font-mono font-semibold text-green-500">{{ $this->getLiveValue($node) }}</span>
                @else
                <span class="text-xs text-zinc-400">—</span>
                @endif
            @endscope

            @scope('actions', $node)
                <x-button
                    icon="o-chart-bar"
                    wire:click="openHistory({{ $node->id }})"
                    class="btn-ghost btn-sm hover:bg-violet-500/10 hover:text-violet-500"
                    title="Lihat history"
                />
            @endscope
        </x-table>
    </x-card>

    {{-- HISTORY MODAL --}}
    <x-modal wire:model="historyModal" title="{{ $this->selectedNode?->name ?? 'History Sensor' }}"
        separator class="bg-white dark:bg-zinc-900 rounded-2xl" style="max-width:700px">

        @if($this->selectedNode)
        <div class="mb-4 flex gap-2 flex-wrap text-xs">
            <span class="px-2 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                <span class="font-semibold">Gedung:</span> {{ $this->selectedNode->room?->building?->name ?? '-' }}
            </span>
            <span class="px-2 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                <span class="font-semibold">Ruangan:</span> {{ $this->selectedNode->room?->name ?? '-' }}
            </span>
        </div>

        @if(count($this->historyChartData))
        <div
            x-data="{
                chart: null,
                init() {
                    this.$nextTick(() => {
                        const el = document.getElementById('vHistoryChart');
                        if (!el) return;
                        const d      = {{ Js::from($this->historyChartData) }};
                        const isDark = document.documentElement.classList.contains('dark');
                        const lbl    = isDark ? '#a1a1aa' : '#71717a';
                        const grid   = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
                        this.chart = new Chart(el, {
                            type: 'line',
                            data: {
                                labels: d.labels,
                                datasets: [{
                                    label: d.name + ' (' + d.unit + ')',
                                    data: d.data,
                                    borderColor: '#8b5cf6',
                                    backgroundColor: 'rgba(139,92,246,0.08)',
                                    tension: 0.4, fill: true, pointRadius: 3,
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: { legend: { labels: { color: lbl, font: { size: 11 } } } },
                                scales: {
                                    x: { ticks: { color: lbl, maxTicksLimit: 8 }, grid: { color: grid } },
                                    y: { ticks: { color: lbl }, grid: { color: grid }, beginAtZero: true }
                                }
                            }
                        });
                    });
                },
                destroy() { if (this.chart) { try { this.chart.destroy(); } catch(e) {} } }
            }"
            x-init="init()" x-destroy="destroy()"
        >
            <canvas id="vHistoryChart" style="max-height:180px" class="mb-4"></canvas>
        </div>
        @endif

        <div class="max-h-60 overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="sticky top-0 bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="text-left px-3 py-2 text-zinc-500 uppercase">No</th>
                        <th class="text-left px-3 py-2 text-zinc-500 uppercase">Nilai</th>
                        <th class="text-left px-3 py-2 text-zinc-500 uppercase">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->historyReadings as $i => $r)
                    @php
                        $p   = $r->payload;
                        $val = match($this->selectedNode->node_type) {
                            'temperature' => ($p['suhu']??'—').'°C / '.($p['kelembaban']??'—').'%',
                            'current'     => ($p['arus']??'—').' A',
                            'voltage'     => ($p['tegangan']??'—').' V',
                            'light'       => ($p['cahaya']??'—').' lux',
                            default       => json_encode($p),
                        };
                    @endphp
                    <tr class="border-b border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-3 py-2 text-zinc-400">{{ $i + 1 }}</td>
                        <td class="px-3 py-2 font-mono font-semibold text-violet-500">{{ $val }}</td>
                        <td class="px-3 py-2 text-zinc-400">{{ \Carbon\Carbon::parse($r->read_at)->format('d M Y H:i:s') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-3 py-6 text-center text-zinc-400">Belum ada history pembacaan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        <x-slot:actions>
            <x-button label="Tutup" wire:click="$set('historyModal', false)" />
        </x-slot:actions>
    </x-modal>
</div>