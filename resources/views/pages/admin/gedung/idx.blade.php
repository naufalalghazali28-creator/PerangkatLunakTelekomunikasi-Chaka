<?php

use Livewire\Component;
use App\Models\BEMS\Room;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithPagination, Toast;

    public string $buildingFilter = '';
    public string $sensorFilter = '';  

    public string $search = '';

    public bool $editModal = false;
    public $editRoomId;
    public $editRoomName;
    public $editRoomFloor;
    public $editBuildingId;
    public $editBuildingName;

    public array $headers = [
        ['key' => 'building.name',        'label' => 'Nama Gedung'],
        ['key' => 'floor',                 'label' => 'Lantai'],
        ['key' => 'name',                  'label' => 'Nama Ruangan'],
        ['key' => 'building.client.name',  'label' => 'Client Pemilik'],
        ['key' => 'node_count',            'label' => 'Total Node'],
        ['key' => 'active_nodes',          'label' => 'Node Aktif'],
        ['key' => 'action',                'label' => 'Aksi'],
    ];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedBuildingFilter()
    {
        $this->resetPage();
    }

    public function updatedSensorFilter()
    {
        $this->resetPage();
    }

    // ── Computed ─────────────────────────────────────────
    #[Computed]
    public function nodeSummary()
    {
        $total    = Node::count();
        $active   = Node::where('status', true)->count();
        return [
            'total'    => $total,
            'active'   => $active,
            'inactive' => $total - $active,
        ];
    }

    #[Computed]
    public function dashboardSummary()
    {
        return [
            'buildings' => Building::query()
                ->selectRaw('COUNT(DISTINCT LOWER(TRIM(name))) as total')
                ->value('total'),

            'rooms' => Room::count(),

            'active' => Node::where('status', true)->count(),

            'inactive' => Node::where('status', false)->count(),
        ];
    }

    // Data untuk chart tipe sensor (donut)
    #[Computed]
    public function nodeTypeData()
    {
        return Node::selectRaw('node_type, count(*) as total')
            ->groupBy('node_type')->get()
            ->mapWithKeys(fn($r) => [$r->node_type => (int)$r->total]);
    }

    // Data untuk Stacked Bar per gedung
    #[Computed]
    public function buildingNodeData()
    {
        return Building::with(['rooms.nodes'])->get()
            ->groupBy(fn($b) => strtolower(trim($b->name)))
            ->map(function ($group) {
                $nodes = $group->flatMap->rooms->flatMap->nodes;
                return [
                    'name'     => trim($group->first()->name),
                    'active'   => $nodes->where('status', true)->count(),
                    'inactive' => $nodes->where('status', false)->count(),
                ];
            })->values();
    }

    #[Computed]
    public function buildingOptions()
    {
        return Building::orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    // Data line chart: nilai dummy per tipe sensor
    #[Computed]
    public function sensorLineData()
    {
        $activeNodes = Node::where('status', true)->get();
        if ($activeNodes->isEmpty()) return [];

        // Generate 7 titik waktu dummy (simulasi historis)
        $types  = ['temperature', 'current', 'voltage', 'light'];
        $colors = ['#3b82f6', '#f59e0b', '#ef4444', '#22c55e'];
        $labels = collect(range(6, 0))->map(fn($i) => now()->subMinutes($i * 10)->format('H:i'))->values()->toArray();

        $datasets = [];
        foreach ($types as $idx => $type) {
            $count = $activeNodes->where('node_type', $type)->count();
            if ($count === 0) continue;

            $data = match($type) {
                'temperature' => collect(range(0,6))->map(fn() => round(mt_rand(200, 350)/10, 1))->toArray(),
                'current'     => collect(range(0,6))->map(fn() => round(mt_rand(5, 150)/10, 2))->toArray(),
                'voltage'     => collect(range(0,6))->map(fn() => round(mt_rand(2100, 2400)/10, 1))->toArray(),
                'light'       => collect(range(0,6))->map(fn() => mt_rand(100, 1000))->toArray(),
                default       => [],
            };

            $label = match($type) {
                'temperature' => "Suhu (°C) [{$count} sensor]",
                'current'     => "Arus (A) [{$count} sensor]",
                'voltage'     => "Tegangan (V) [{$count} sensor]",
                'light'       => "Cahaya (lux) [{$count} sensor]",
                default       => $type,
            };

            $datasets[] = [
                'label'           => $label,
                'data'            => $data,
                'borderColor'     => $colors[$idx],
                'backgroundColor' => $colors[$idx] . '15',
                'tension'         => 0.4,
                'fill'            => true,
                'pointRadius'     => 4,
            ];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    // ── Edit ─────────────────────────────────────────────
    public function editRoom($roomId)
    {
        $room = Room::with('building')->find($roomId);
        if ($room) {
            $this->editRoomId      = $room->id;
            $this->editRoomName    = $room->name;
            $this->editRoomFloor   = $room->floor;
            $this->editBuildingId  = $room->building_id;
            $this->editBuildingName = $room->building->name ?? '';
            $this->editModal       = true;
        }
    }

    public function updateRoom()
    {
        $this->validate([
            'editBuildingName' => 'required',
            'editRoomName'     => 'required',
            'editRoomFloor'    => 'required',
        ]);

        Room::where('id', $this->editRoomId)->update([
            'name'  => $this->editRoomName,
            'floor' => $this->editRoomFloor,
        ]);

        if ($this->editBuildingId) {
            Building::where('id', $this->editBuildingId)->update(['name' => $this->editBuildingName]);
        }

        $this->success('Data ruangan dan gedung berhasil diupdate!');
        $this->editModal = false;
    }

    public function deleteRoom($roomId)
    {
        $room = Room::find($roomId);

        if (!$room) {
            $this->error('Data ruangan tidak ditemukan');
            return;
        }

        // Cek apakah masih ada node
        if ($room->nodes()->count() > 0) {
            $this->error('Ruangan tidak dapat dihapus karena masih memiliki node.');
            return;
        }

        $room->delete();

        $this->success('Ruangan berhasil dihapus');
    }

    // ── Export ───────────────────────────────────────────
    public function exportExcel()
    {
        $search = $this->search;
        return Excel::download(
            new class($search) implements
                \Maatwebsite\Excel\Concerns\FromQuery,
                \Maatwebsite\Excel\Concerns\WithHeadings,
                \Maatwebsite\Excel\Concerns\WithMapping,
                \Maatwebsite\Excel\Concerns\ShouldAutoSize
            {
                public function __construct(private string $search) {}
                public function query()
                {
                    return Room::with(['building.client'])
                        ->withCount(['nodes', 'nodes as active_nodes_count' => fn($q) => $q->where('status', true)])
                        ->when($this->search, fn($q) =>
                            $q->where('name', 'like', "%{$this->search}%")
                              ->orWhereHas('building', fn($b) => $b->where('name', 'like', "%{$this->search}%"))
                        );
                }
                public function map($room): array
                {
                    static $no = 0; $no++;
                    return [
                        $no,
                        $room->building->name ?? '-',
                        $room->floor,
                        $room->name,
                        $room->building->client->name ?? '-',
                        $room->nodes_count,
                        $room->active_nodes_count,
                    ];
                }
                public function headings(): array
                {
                    return ['No', 'Gedung', 'Lantai', 'Ruangan', 'Client', 'Total Node', 'Node Aktif'];
                }
            },
            'Data_Gedung_' . now()->format('d-m-Y') . '.xlsx'
        );
    }
   
    public function exportPdf(): mixed
    {
        $rooms = Room::with(['building.client'])
            ->withCount([
                'nodes',
                'nodes as active_nodes_count' => fn($q) => $q->where('status', true),
            ])
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhereHas('building', fn($b) => $b->where('name', 'like', "%{$this->search}%"))
            )
            ->latest()->get();

        $s    = $this->nodeSummary;
        $now  = now()->format('d/m/Y H:i');
        $count = $rooms->count();

        $rows = '';
        foreach ($rooms as $i => $r) {
            $rows .= '<tr>'
                . '<td>' . ($i + 1) . '</td>'
                . '<td>' . e($r->building->name ?? '-') . '</td>'
                . '<td>' . $r->floor . '</td>'
                . '<td>' . e($r->name) . '</td>'
                . '<td>' . e($r->building->client->name ?? '-') . '</td>'
                . '<td>' . $r->nodes_count . '</td>'
                . '<td class="blue">' . $r->active_nodes_count . '</td>'
                . '</tr>';
        }

        $css = '
            body{font-family:sans-serif;font-size:10px;color:#333}
            h2{text-align:center;font-size:13px;margin:0 0 2px}
            p.sub{text-align:center;color:#888;font-size:9px;margin:0 0 10px}
            .stats{display:flex;gap:12px;margin-bottom:12px}
            .stat{flex:1;border:1px solid #e5e7eb;border-radius:6px;padding:6px;text-align:center}
            .val{font-size:18px;font-weight:bold}
            .lbl{font-size:8px;color:#6b7280}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #ddd;padding:5px 7px;text-align:left}
            th{background:#16a34a;color:#fff;font-size:9px;text-transform:uppercase}
            tr:nth-child(even){background:#f9fafb}
            .blue{color:#2563eb;font-weight:bold}
        ';

        $html = "
        <style>{$css}</style>
        <h2>LAPORAN MANAJEMEN GEDUNG &amp; SENSOR</h2>
        <p class='sub'>Tanggal Cetak: {$now}</p>
        <div class='stats'>
            <div class='stat'><div class='val'>{$s['total']}</div><div class='lbl'>Total Node</div></div>
            <div class='stat'><div class='val' style='color:#2563eb'>{$s['active']}</div><div class='lbl'>Node Aktif</div></div>
            <div class='stat'><div class='val' style='color:#6b7280'>{$s['inactive']}</div><div class='lbl'>Node Nonaktif</div></div>
            <div class='stat'><div class='val'>{$count}</div><div class='lbl'>Total Ruangan</div></div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>No</th><th>Gedung</th><th>Lantai</th><th>Ruangan</th>
                    <th>Client</th><th>Total Node</th><th>Node Aktif</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
        </table>";

        $filename = 'Laporan_Gedung_' . now()->format('d-m-Y') . '.pdf';
        $pdf      = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        $output   = $pdf->output();

        return response()->streamDownload(
            fn() => print($output),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    #[Computed]
    public function rooms()
    {
        return Room::with(['building.client'])
            ->withCount([
                'nodes',
                'nodes as active_nodes_count' => fn($q) => $q->where('status', true)
            ])

            ->when($this->search, function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhereHas('building', fn($b) =>
                        $b->where('name', 'like', "%{$this->search}%")
                        ->orWhereHas('client', fn($c) =>
                            $c->where('name', 'like', "%{$this->search}%")
                        )
                    );
            })

            ->when($this->buildingFilter, function ($q) {
                $q->where('building_id', $this->buildingFilter);
            })

            ->when($this->sensorFilter, function ($q) {
                $q->whereHas('nodes', function ($n) {
                    $n->where('node_type', $this->sensorFilter);
                });
            })

            ->latest()
            ->paginate(15);
    }
}; ?>

<div
    x-data="{
        charts: {},
        initCharts() {
            this.$nextTick(() => {
                this.destroyAll();
                this.buildTypeChart();
                this.buildBuildingChart();
                this.buildLineChart();
            });
        },
        destroyAll() {
            Object.values(this.charts).forEach(c => { try { c.destroy(); } catch(e){} });
            this.charts = {};
        },
        isDark() { return document.documentElement.classList.contains('dark'); },
        grid()   { return this.isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)'; },
        lbl()    { return this.isDark() ? '#a1a1aa' : '#71717a'; },

        buildTypeChart() {
            const el = document.getElementById('chartType');
            if (!el) return;
            const raw  = {{ Js::from($this->nodeTypeData) }};
            const map  = { temperature: 'Suhu', current: 'Arus', voltage: 'Tegangan', light: 'Cahaya' };
            const clrs = { temperature: '#3b82f6', current: '#f59e0b', voltage: '#ef4444', light: '#22c55e' };
            const keys = Object.keys(raw);
            this.charts.type = new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: keys.map(k => map[k] ?? k),
                    datasets: [{
                        data: keys.map(k => raw[k]),
                        backgroundColor: keys.map(k => clrs[k] ?? '#6366f1'),
                        borderWidth: 2,
                        borderColor: this.isDark() ? '#18181b' : '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom', labels: { color: this.lbl(), font: { size: 11 } } } },
                    cutout: '60%',
                }
            });
        },

        buildBuildingChart() {
            const el = document.getElementById('chartBuilding');
            if (!el) return;
            const rows = {{ Js::from($this->buildingNodeData) }};
            this.charts.building = new Chart(el, {
                type: 'bar',
                data: {
                    labels: rows.map(r => r.name),
                    datasets: [
                        { label: 'Aktif',    data: rows.map(r => r.active),   backgroundColor: '#3b82f6', borderRadius: 5 },
                        { label: 'Nonaktif', data: rows.map(r => r.inactive), backgroundColor: this.isDark() ? '#3f3f46' : '#d4d4d8', borderRadius: 5 },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: this.lbl(), font: { size: 11 } } } },
                    scales: {
                        x: { stacked: true, ticks: { color: this.lbl() }, grid: { display: false } },
                        y: { stacked: true, ticks: { color: this.lbl(), stepSize: 1 }, grid: { color: this.grid() }, beginAtZero: true }
                    }
                }
            });
        },

        buildLineChart() {
            const el = document.getElementById('chartLine');
            if (!el) return;
            const data = {{ Js::from($this->sensorLineData) }};
            if (!data || !data.datasets || data.datasets.length === 0) return;
            this.charts.line = new Chart(el, {
                type: 'line',
                data: { labels: data.labels, datasets: data.datasets },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { labels: { color: this.lbl(), font: { size: 11 } } } },
                    scales: {
                        x: { ticks: { color: this.lbl() }, grid: { color: this.grid() } },
                        y: { ticks: { color: this.lbl() }, grid: { color: this.grid() }, beginAtZero: true }
                    }
                }
            });
        }
    }"
    x-init="initCharts()"
>

    <x-header title="Admin | Manajemen Gedung"
        subtitle="Daftar semua gedung, ruangan, dan status sensor"
        separator progress-indicator>
        <x-slot:actions>
            <x-dropdown label="Export" icon="o-arrow-down-tray" class="btn-outline btn-sm" right>
                <x-menu-item title="Export PDF"   icon="o-document-text"
                    @click="
                        const imgs = [...document.querySelectorAll('canvas')].map(c => c.toDataURL('image/png'));
                        $wire.exportPdf(imgs[0]??null, imgs[1]??null, imgs[2]??null)
                    " class="text-error" />
                <x-menu-item title="Export Excel" icon="o-table-cells" wire:click="exportExcel" class="text-success" />
            </x-dropdown>
        </x-slot:actions>
    </x-header>

    {{-- NODE SUMMARY CARDS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        {{-- TOTAL GEDUNG --}}
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 flex items-center justify-center">
                    <x-icon name="o-building-office-2" class="w-6 h-6 text-indigo-500"/>
                </div>
                <div>
                    <p class="text-3xl font-bold">
                        {{ $this->dashboardSummary['buildings'] }}
                    </p>

                    <p class="text-sm text-zinc-500">
                        Total Gedung
                    </p>
                </div>
            </div>
        </x-card>

        {{-- TOTAL RUANGAN --}}
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-cyan-500/10 flex items-center justify-center">
                    <x-icon name="o-home-modern" class="w-6 h-6 text-cyan-500"/>
                </div>

                <div>
                    <p class="text-3xl font-bold">
                        {{ $this->dashboardSummary['rooms'] }}
                    </p>

                    <p class="text-sm text-zinc-500">
                        Total Ruangan
                    </p>
                </div>
            </div>
        </x-card>

        {{-- NODE AKTIF --}}
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center">
                    <x-icon name="o-bolt" class="w-6 h-6 text-blue-500"/>
                </div>

                <div>
                    <p class="text-3xl font-bold text-blue-500">
                        {{ $this->dashboardSummary['active'] }}
                    </p>

                    <p class="text-sm text-zinc-500">
                        Node Aktif
                    </p>
                </div>
            </div>
        </x-card>

        {{-- NODE NONAKTIF --}}
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-zinc-500/10 flex items-center justify-center">
                    <x-icon name="o-pause-circle" class="w-6 h-6 text-zinc-500"/>
                </div>

                <div>
                    <p class="text-3xl font-bold text-zinc-500">
                        {{ $this->dashboardSummary['inactive'] }}
                    </p>

                    <p class="text-sm text-zinc-500">
                        Node Nonaktif
                    </p>
                </div>
            </div>
        </x-card>

    </div>

    {{-- CHARTS ROW 1 --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
        <x-card title="Tipe Sensor Terdaftar" shadow
            class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex justify-center py-2" style="min-height:220px">
                <canvas id="chartType" style="max-height:220px"></canvas>
            </div>
        </x-card>

        <x-card title="Node Aktif per Gedung" subtitle="Stacked: aktif vs nonaktif" shadow
            class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div style="min-height:220px">
                <canvas id="chartBuilding" style="max-height:220px"></canvas>
            </div>
        </x-card>
    </div>

    {{-- CHART LINE SENSOR --}}
    @if($this->nodeSummary['active'] > 0)
    <x-card title="Grafik Data Sensor Aktif" subtitle="Simulasi data historis 70 menit terakhir per tipe sensor"
        shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl mb-4">
        <canvas id="chartLine" style="max-height:200px"></canvas>
    </x-card>
    @else
    <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl mb-4">
        <div class="py-8 text-center text-zinc-400">
            <x-icon name="o-chart-bar" class="w-10 h-10 mx-auto mb-2 opacity-20" />
            <p class="text-sm">Grafik line akan muncul setelah ada sensor yang diaktifkan oleh Operator.</p>
        </div>
    </x-card>
    @endif

    {{-- TABLE --}}
    <x-card
        title="Daftar Gedung dan Ruangan"
        subtitle="Informasi gedung, ruangan, client, dan status node"
        shadow
        class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl"
    >
        <div class="mb-4 flex flex-col md:flex-row gap-3">
            <x-input
            icon="o-magnifying-glass"
            wire:model.live.debounce.500ms="search"
            placeholder="Cari gedung, ruangan, atau client..."
            class="flex-1"
            clearable
        />

        <x-select
            wire:model.live="buildingFilter"
            placeholder="Semua Gedung"
            class="w-full md:w-56"
            :options="collect($this->buildingOptions)
                ->map(fn($name, $id) => [
                    'id' => $id,
                    'name' => $name
                ])
                ->values()
                ->toArray()"
        />

        <x-select
            wire:model.live="sensorFilter"
            placeholder="Semua Sensor"
            class="w-full md:w-52"
            :options="[
                ['id' => 'temperature', 'name' => 'Suhu'],
                ['id' => 'current', 'name' => 'Arus'],
                ['id' => 'voltage', 'name' => 'Tegangan'],
                ['id' => 'light', 'name' => 'Cahaya'],
            ]"
        />

        <x-dropdown
            label="Export"
            icon="o-arrow-down-tray"
            class="btn-outline"
            right
        >

            <x-menu-item
                title="Export PDF"
                icon="o-document-text"
                @click="
                    const imgs = [...document.querySelectorAll('canvas')]
                        .map(c => c.toDataURL('image/png'));

                    $wire.exportPdf(
                        imgs[0] ?? null,
                        imgs[1] ?? null,
                        imgs[2] ?? null
                    );
                "
                class="text-error"
            />

            <x-menu-item
                title="Export Excel"
                icon="o-table-cells"
                wire:click="exportExcel"
                class="text-success"
            />

        </x-dropdown>
        </div>

        <div wire:loading wire:target="exportPdf,exportExcel" class="mb-3">
            <x-alert title="Generating File..." icon="o-arrow-path" class="alert-info" />
        </div>

        <x-table :headers="$headers" :rows="$this->rooms" with-pagination
            class="
                [&_thead]:bg-zinc-50 [&_thead]:dark:bg-zinc-800
                [&_th]:text-zinc-500 [&_th]:text-xs [&_th]:uppercase
                [&_tbody_tr]:border-zinc-100 [&_tbody_tr]:dark:border-zinc-800
                [&_tbody_tr:hover]:bg-zinc-50 [&_tbody_tr:hover]:dark:bg-zinc-800/50
            "
        >
            @scope('cell_node_count', $room)
                <span class="text-sm font-medium">{{ $room->nodes_count }}</span>
            @endscope

            @scope('cell_active_nodes', $room)
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full {{ $room->active_nodes_count > 0 ? 'bg-blue-500 animate-pulse' : 'bg-zinc-400' }}"></span>
                    <span class="text-sm font-semibold {{ $room->active_nodes_count > 0 ? 'text-blue-500' : 'text-zinc-400' }}">
                        {{ $room->active_nodes_count }}
                    </span>
                </div>
            @endscope

            @scope('cell_action', $room)
            <div class="flex gap-1">
                <x-button
                    icon="o-pencil"
                    wire:click="editRoom({{ $room->id }})"
                    class="btn-ghost btn-sm hover:bg-blue-500/10 hover:text-blue-500"
                />

                <x-button
                    icon="o-trash"
                    wire:click="deleteRoom({{ $room->id }})"
                    wire:confirm="Yakin ingin menghapus ruangan ini?"
                    class="btn-ghost btn-sm hover:bg-red-500/10 hover:text-red-500"
                />
            </div>
        @endscope
        </x-table>
    </x-card>

    {{-- EDIT MODAL --}}
    <x-modal wire:model="editModal" title="Edit Data Infrastruktur" separator
        class="bg-white dark:bg-zinc-900 rounded-2xl">
        <div class="space-y-4">
            <x-input label="Nama Gedung"  wire:model="editBuildingName" icon="o-building-office" />
            <x-input label="Lantai"        wire:model="editRoomFloor"    icon="o-numbered-list" />
            <x-input label="Nama Ruangan" wire:model="editRoomName"      icon="o-map-pin" />
        </div>
        <x-slot:actions>
            <x-button label="Batal"  wire:click="$set('editModal', false)" />
            <x-button label="Simpan" wire:click="updateRoom" class="btn-primary" />
        </x-slot:actions>
    </x-modal>

    <script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('open-pdf', (data) => {
            console.log(data);
            const base64 = data.pdfBase64 ?? data[0]?.pdfBase64;
            if (!base64) {
                console.error('PDF Base64 kosong');
                return;
            }
            const binary = atob(base64);
            const len = binary.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            const blob = new Blob(
                [bytes],
                { type: 'application/pdf' }
            );
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');
        });
    });
    </script>
</div>