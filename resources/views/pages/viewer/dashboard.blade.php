<?php

use Livewire\Component;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use App\Models\BEMS\NodeReading;
use App\Services\MqttService;
use Livewire\Attributes\Computed;

new class extends Component {

    public ?int $selectedNodeId = null;

    public function mount(): void
    {
        $first = Node::where('status', true)->first();
        if ($first) $this->selectedNodeId = $first->id;
    }

    #[Computed]
    public function summary()
    {
        return [
            'buildings' => Building::distinct('name')->count(),
            'nodes'     => Node::count(),
            'active'    => Node::where('status', true)->count(),
            'inactive'  => Node::where('status', false)->count(),
        ];
    }

    #[Computed]
    public function buildings()
    {
        return Building::with(['rooms.nodes', 'client'])->get()
            ->groupBy(fn($b) => strtolower(trim($b->name)))
            ->map(function ($group) {
                $nodes = $group->flatMap->rooms->flatMap->nodes;
                return [
                    'name'     => trim($group->first()->name),
                    'client'   => $group->first()->client?->name ?? '-',
                    'rooms'    => $group->sum(fn($b) => $b->rooms->count()),
                    'total'    => $nodes->count(),
                    'active'   => $nodes->where('status', true)->count(),
                    'inactive' => $nodes->where('status', false)->count(),
                ];
            })->values();
    }

    #[Computed]
    public function activeNodeOptions()
    {
        return Node::with('room.building')
            ->where('status', true)
            ->get()
            ->map(fn($n) => [
                'id'   => $n->id,
                'name' => $n->name . ' — ' . ($n->room?->building?->name ?? '') . ' › ' . ($n->room?->name ?? ''),
            ])->toArray();
    }

    #[Computed]
    public function nodeTypeChartData()
    {
        $counts = Node::selectRaw('node_type, count(*) as total')
            ->groupBy('node_type')->get()
            ->mapWithKeys(fn($r) => [$r->node_type => (int)$r->total]);
        return [
            'labels' => ['Suhu', 'Arus', 'Tegangan', 'Cahaya'],
            'data'   => [
                $counts['temperature'] ?? 0,
                $counts['current']     ?? 0,
                $counts['voltage']     ?? 0,
                $counts['light']       ?? 0,
            ],
        ];
    }

    /**
     * Struktur data untuk GROUPED HORIZONTAL BAR (seperti gambar referensi).
     * Label = nama node (sumbu Y), dataset = tiap tipe sensor (warna berbeda).
     * Setiap dataset hanya mengisi nilai pada node yang sesuai tipenya, sisanya null
     * agar bar tidak ditumpuk tapi dikelompokkan per node.
     */
    #[Computed]
    public function allSensorData()
    {
        $nodes = Node::with('room.building')->where('status', true)->get();
        if ($nodes->isEmpty()) return [];

        $mqtt   = new MqttService();
        $labels = $nodes->map(fn($n) => substr($n->name, 0, 14) . (strlen($n->name) > 14 ? '…' : ''))->toArray();

        // Kumpulkan nilai per node
        $values = $nodes->map(function ($node) use ($mqtt) {
            $d = $mqtt->generateDummyPayload($node->node_type);
            return [
                'type'  => $node->node_type,
                'value' => match($node->node_type) {
                    'temperature' => $d['suhu']    ?? 0,
                    'current'     => $d['arus']     ?? 0,
                    'voltage'     => $d['tegangan'] ?? 0,
                    'light'       => $d['cahaya']   ?? 0,
                    default       => 0,
                },
            ];
        });

        // Buat satu dataset per tipe sensor — nilai null pada node yang bukan tipenya
        $typeMap = [
            'temperature' => ['label' => 'Suhu (°C)',     'color' => '#3b82f6'],
            'current'     => ['label' => 'Arus (A)',      'color' => '#f59e0b'],
            'voltage'     => ['label' => 'Tegangan (V)',  'color' => '#ef4444'],
            'light'       => ['label' => 'Cahaya (lux)',  'color' => '#22c55e'],
        ];

        $datasets = [];
        foreach ($typeMap as $type => $meta) {
            $data = $values->map(fn($v) => $v['type'] === $type ? $v['value'] : null)->toArray();
            // Hanya masukkan dataset jika ada minimal satu nilai
            if (collect($data)->filter(fn($v) => $v !== null)->isNotEmpty()) {
                $datasets[] = [
                    'label'           => $meta['label'],
                    'data'            => $data,
                    'backgroundColor' => $meta['color'],
                    'borderColor'     => $meta['color'],
                    'borderWidth'     => 0,
                    'borderRadius'    => 3,
                    'barThickness'    => 10,
                ];
            }
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    #[Computed]
    public function singleSensorData()
    {
        if (!$this->selectedNodeId) return [];
        $node = Node::find($this->selectedNodeId);
        if (!$node) return [];

        $readings = NodeReading::where('node_id', $this->selectedNodeId)
            ->latest('read_at')->take(20)->get()->reverse()->values();

        if ($readings->isEmpty()) {
            $mqtt   = new MqttService();
            $points = collect(range(9, 0))->map(function ($i) use ($node, $mqtt) {
                $p = $mqtt->generateDummyPayload($node->node_type);
                return [
                    'time'  => now()->subSeconds($i * 5)->format('H:i:s'),
                    'value' => match($node->node_type) {
                        'temperature' => $p['suhu'],
                        'current'     => $p['arus'],
                        'voltage'     => $p['tegangan'],
                        'light'       => $p['cahaya'],
                        default       => 0,
                    },
                ];
            });
            $labels = $points->pluck('time')->toArray();
            $data   = $points->pluck('value')->toArray();
        } else {
            $labels = $readings->map(fn($r) => \Carbon\Carbon::parse($r->read_at)->format('H:i:s'))->toArray();
            $data   = $readings->map(function ($r) use ($node) {
                $p = $r->payload;
                return match($node->node_type) {
                    'temperature' => $p['suhu']    ?? 0,
                    'current'     => $p['arus']     ?? 0,
                    'voltage'     => $p['tegangan'] ?? 0,
                    'light'       => $p['cahaya']   ?? 0,
                    default       => 0,
                };
            })->toArray();
        }

        $unit = match($node->node_type) {
            'temperature' => '°C', 'current' => 'A', 'voltage' => 'V', 'light' => 'lux', default => ''
        };

        return [
            'nodeName' => $node->name,
            'unit'     => $unit,
            'labels'   => $labels,
            'data'     => $data,
            'building' => $node->room?->building?->name ?? '-',
            'room'     => $node->room?->name ?? '-',
        ];
    }
}; ?>

<div
    x-data="{
        charts: {},
        componentId: '{{ $this->getId() }}',

        waitForChart(cb) {
            if (typeof Chart !== 'undefined') { cb(); return; }
            let t = 0;
            const iv = setInterval(() => {
                if (typeof Chart !== 'undefined') { clearInterval(iv); cb(); }
                else if (++t > 200) { clearInterval(iv); console.error('[CHAKA] Chart.js tidak ditemukan.'); }
            }, 50);
        },

        init() {
            this.$nextTick(() => this.waitForChart(() => this.buildAll()));

            /*
             * FIX UTAMA untuk chart hilang saat dropdown berubah:
             * Gunakan Livewire.hook 'commit' yang menyediakan component instance.
             * Dengan mengecek component.id === this.componentId, kita HANYA rebuild
             * chart milik komponen ini — tidak terganggu update komponen Livewire lain.
             */
            Livewire.hook('commit', ({ component, succeed }) => {
                if (component.id !== this.componentId) return;
                succeed(() => {
                    this.$nextTick(() => {
                        this.waitForChart(() => {
                            this.destroyAll();
                            this.buildAll();
                        });
                    });
                });
            });
        },

        destroyAll() {
            Object.values(this.charts).forEach(c => { try { c.destroy(); } catch(e) {} });
            this.charts = {};
        },

        isDark() { return document.documentElement.classList.contains('dark'); },
        grid()   { return this.isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)'; },
        lbl()    { return this.isDark() ? '#a1a1aa' : '#71717a'; },

        buildAll() {
            this.buildType();
            this.buildStacked();
            this.buildSingle();
        },

        buildType() {
            const el = document.getElementById('vChartType');
            if (!el) return;
            const d = {{ Js::from($this->nodeTypeChartData) }};
            this.charts.type = new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [{
                        data: d.data,
                        backgroundColor: ['#3b82f6','#f59e0b','#ef4444','#22c55e'],
                        borderWidth: 2,
                        borderColor: this.isDark() ? '#18181b' : '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: this.lbl(), font: { size: 11 } } }
                    },
                    cutout: '62%'
                }
            });
        },

        /*
         * Grouped horizontal bar — seperti gambar referensi.
         * indexAxis: 'y'  → bar horizontal
         * grouped (bukan stacked) → tiap node punya beberapa bar warna berbeda per tipe sensor
         */
        buildStacked() {
            const el = document.getElementById('vChartStacked');
            if (!el) return;
            const d = {{ Js::from($this->allSensorData) }};
            if (!d || !d.datasets || !d.datasets.length) return;

            this.charts.stacked = new Chart(el, {
                type: 'bar',
                data: { labels: d.labels, datasets: d.datasets },
                options: {
                    indexAxis: 'y',         // ← horizontal bar
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { color: this.lbl(), font: { size: 11 }, boxWidth: 12, padding: 16 }
                        }
                    },
                    scales: {
                        x: {
                            stacked: false,     // grouped, bukan stacked
                            beginAtZero: true,
                            ticks: { color: this.lbl() },
                            grid:  { color: this.grid() }
                        },
                        y: {
                            stacked: false,
                            ticks: { color: this.lbl(), font: { size: 11 } },
                            grid:  { display: false }
                        }
                    }
                }
            });
        },

        buildSingle() {
            const el = document.getElementById('vChartSingle');
            if (!el) return;
            const d = {{ Js::from($this->singleSensorData) }};
            if (!d || !d.labels || !d.labels.length) return;

            this.charts.single = new Chart(el, {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: d.nodeName + ' (' + d.unit + ')',
                        data: d.data,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139,92,246,0.08)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { color: this.lbl(), font: { size: 11 } } }
                    },
                    scales: {
                        x: { ticks: { color: this.lbl() }, grid: { color: this.grid() } },
                        y: { ticks: { color: this.lbl() }, grid: { color: this.grid() }, beginAtZero: true }
                    }
                }
            });
        }
    }"
    x-init="init()"
>
    <x-header title="Dashboard Viewer" subtitle="Pantau kondisi gedung dan sensor secara real-time" separator progress-indicator />

    {{-- STAT CARDS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        @foreach([
            ['label'=>'Gedung',       'val'=>$this->summary['buildings'], 'icon'=>'o-building-office', 'bg'=>'bg-violet-500/10',             'clr'=>'text-violet-500'],
            ['label'=>'Total Node',   'val'=>$this->summary['nodes'],     'icon'=>'o-server-stack',     'bg'=>'bg-zinc-100 dark:bg-zinc-800', 'clr'=>'text-zinc-900 dark:text-white'],
            ['label'=>'Node Aktif',   'val'=>$this->summary['active'],    'icon'=>'o-bolt',             'bg'=>'bg-blue-500/10',               'clr'=>'text-blue-500'],
            ['label'=>'Node Nonaktif','val'=>$this->summary['inactive'],  'icon'=>'o-pause-circle',     'bg'=>'bg-zinc-100 dark:bg-zinc-800', 'clr'=>'text-zinc-500'],
        ] as $s)
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl {{ $s['bg'] }} flex items-center justify-center shrink-0">
                    <x-icon name="{{ $s['icon'] }}" class="w-5 h-5 {{ $s['clr'] }}" />
                </div>
                <div>
                    <p class="text-2xl font-bold {{ $s['clr'] }}">{{ $s['val'] }}</p>
                    <p class="text-xs text-zinc-500">{{ $s['label'] }}</p>
                </div>
            </div>
        </x-card>
        @endforeach
    </div>

    {{-- ROW 1: Donut + Building List --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-4">
        <div class="lg:col-span-2">
            <x-card title="Distribusi Tipe Sensor" shadow
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl h-full">
                <div class="flex justify-center items-center py-2" style="min-height:220px">
                    <canvas id="vChartType" wire:key="chart-type" style="max-height:220px; max-width:300px"></canvas>
                </div>
            </x-card>
        </div>
        <div class="lg:col-span-3">
            <x-card title="Daftar Gedung" subtitle="Status node per gedung" shadow
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl h-full">
                @forelse($this->buildings as $b)
                <div class="flex items-center gap-4 py-3 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                    <div class="w-9 h-9 rounded-xl bg-violet-500/10 flex items-center justify-center shrink-0">
                        <x-icon name="o-building-office" class="w-4 h-4 text-violet-500" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate">{{ $b['name'] }}</p>
                        <p class="text-xs text-zinc-400">{{ $b['client'] }} · {{ $b['rooms'] }} ruangan</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs shrink-0">
                        <span class="flex items-center gap-1 text-blue-500 font-semibold">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>{{ $b['active'] }}
                        </span>
                        <span class="flex items-center gap-1 text-zinc-400">
                            <span class="w-2 h-2 rounded-full bg-zinc-400"></span>{{ $b['inactive'] }}
                        </span>
                        <span class="text-zinc-400">/ {{ $b['total'] }}</span>
                    </div>
                </div>
                @empty
                <div class="py-8 text-center text-zinc-400 text-sm">Belum ada gedung.</div>
                @endforelse
            </x-card>
        </div>
    </div>

    @if($this->summary['active'] > 0)

    {{-- ROW 2: Grouped Horizontal Bar semua sensor --}}
    <x-card title="Data Semua Sensor Aktif" subtitle="Perbandingan nilai tiap sensor — dikelompokkan per tipe"
        shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl mb-4">
        {{--
            Tinggi canvas disesuaikan dengan jumlah node agar bar tidak terlalu rapat.
            min-height cukup untuk 3 node, sisanya mengembang otomatis.
        --}}
        <div style="min-height: {{ max(160, count($this->activeNodeOptions) * 52) }}px; position:relative">
            <canvas id="vChartStacked" wire:key="chart-stacked"></canvas>
        </div>
    </x-card>

    {{-- ROW 3: Line chart satu sensor --}}
    <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl mb-4">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Grafik Detail Sensor</p>
                <p class="text-xs text-zinc-400">History 20 pembacaan terakhir</p>
            </div>
            <x-select
                wire:model.live="selectedNodeId"
                :options="$this->activeNodeOptions"
                placeholder="— Pilih Sensor —"
                class="w-80"
            />
        </div>

        @if($selectedNodeId && count($this->singleSensorData))
            @php $sd = $this->singleSensorData; @endphp
            <div class="flex gap-4 mb-3 text-xs flex-wrap">
                <span class="px-2 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                    <span class="font-semibold">Gedung:</span> {{ $sd['building'] }}
                </span>
                <span class="px-2 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                    <span class="font-semibold">Ruangan:</span> {{ $sd['room'] }}
                </span>
                <span class="px-2 py-1 rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">
                    <span class="font-semibold">Satuan:</span> {{ $sd['unit'] }}
                </span>
            </div>
            <canvas
                id="vChartSingle"
                wire:key="chart-single-{{ $selectedNodeId }}"
                style="max-height:200px"
            ></canvas>
        @else
            <div class="py-10 text-center text-zinc-400">
                <x-icon name="o-chart-bar" class="w-10 h-10 mx-auto mb-2 opacity-20" />
                <p class="text-sm">Pilih sensor dari dropdown untuk melihat grafik detailnya.</p>
            </div>
        @endif
    </x-card>

    @else

    <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl mb-4">
        <div class="py-12 text-center text-zinc-400">
            <x-icon name="o-chart-bar" class="w-12 h-12 mx-auto mb-3 opacity-20" />
            <p class="text-sm font-medium">Belum ada sensor aktif.</p>
            <p class="text-xs mt-1">Grafik akan muncul setelah operator mengaktifkan sensor.</p>
        </div>
    </x-card>

    @endif

</div>