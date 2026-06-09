<?php

use Livewire\Component;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use App\Services\MqttService;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public array $liveData = [];

    public string $selectedSensorType = 'temperature';

    public function mount(): void
    {
        $mqtt = new MqttService();
        foreach (Node::where('status', true)->get() as $node) {
            $this->liveData[$node->id] = $mqtt->generateDummyPayload($node->node_type);
        }
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

    #[Computed]
    public function buildings()
    {
        return Building::with(['rooms.nodes'])->get()
            ->groupBy(fn($b) => strtolower(trim($b->name)))
            ->map(function ($group) {
                $nodes = $group->flatMap->rooms->flatMap->nodes;
                return [
                    'name'     => trim($group->first()->name),
                    'rooms'    => $group->sum(fn($b) => $b->rooms->count()),
                    'total'    => $nodes->count(),
                    'active'   => $nodes->where('status', true)->count(),
                    'inactive' => $nodes->where('status', false)->count(),
                ];
            })->values();
    }

    #[Computed]
    public function activeNodes()
    {
        return Node::with('room.building')
            ->where('status', true)
            ->latest('updated_at')
            ->get();
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

    #[Computed]
    public function buildingChartData()
    {
        $buildings = $this->buildings;
        return [
            'labels'   => $buildings->pluck('name')->values()->toArray(),
            'active'   => $buildings->pluck('active')->values()->toArray(),
            'inactive' => $buildings->pluck('inactive')->values()->toArray(),
        ];
    }

    #[Computed]
    public function sensorChartData()
    {
        $activeNodes = Node::where('status', true)->get();

        if ($activeNodes->isEmpty()) {
            return [];
        }

        $labels = collect(range(6, 0))
            ->map(fn($i) => now()->subMinutes($i * 10)->format('H:i'))
            ->toArray();

        $datasets = [];

        $types = [
            'temperature' => [
                'label' => 'Suhu (°C)',
                'color' => '#3b82f6',
            ],
            'current' => [
                'label' => 'Arus (A)',
                'color' => '#f59e0b',
            ],
            'voltage' => [
                'label' => 'Tegangan (V)',
                'color' => '#ef4444',
            ],
            'light' => [
                'label' => 'Cahaya (lux)',
                'color' => '#22c55e',
            ],
        ];

        foreach ($types as $type => $meta) {

            $count = $activeNodes
                ->where('node_type', $type)
                ->count();

            if ($count === 0) {
                continue;
            }

            $data = [];

            for ($i = 0; $i < 7; $i++) {

                $sum = 0;

                foreach (
                    $activeNodes->where('node_type', $type)
                    as $node
                ) {

                    $payload = app(MqttService::class)
                        ->generateDummyPayload($type);

                    $sum += match ($type) {
                        'temperature' => $payload['suhu'],
                        'current' => $payload['arus'],
                        'voltage' => $payload['tegangan'],
                        'light' => $payload['cahaya'],
                        default => 0,
                    };
                }

                $data[] = round($sum / max($count,1),2);
            }

            $datasets[] = [
                'label' =>
                    $meta['label']
                    .' ['.$count.' sensor]',

                'data' => $data,

                'borderColor' => $meta['color'],
                'backgroundColor' => $meta['color'].'20',

                'fill' => true,
                'tension' => 0.4,
                'pointRadius' => 4,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    #[Computed]
    public function sensorDetailChartData()
    {
        $nodes = Node::with('room.building')
            ->where('status', true)
            ->where('node_type', $this->selectedSensorType)
            ->get();

        if ($nodes->isEmpty()) {
            return [];
        }

        $labels = collect(range(6, 0))
            ->map(fn($i) => now()->subMinutes($i * 10)->format('H:i'))
            ->toArray();

        $datasets = [];

        foreach ($nodes as $node) {

            $color = sprintf(
                '#%06X',
                mt_rand(0, 0xFFFFFF)
            );

            $data = [];

            for ($i = 0; $i < 7; $i++) {

                $payload = app(MqttService::class)
                    ->generateDummyPayload($node->node_type);

                $value = match ($node->node_type) {
                    'temperature' => $payload['suhu'] ?? 0,
                    'current'     => $payload['arus'] ?? 0,
                    'voltage'     => $payload['tegangan'] ?? 0,
                    'light'       => $payload['cahaya'] ?? 0,
                    default       => 0,
                };

                $data[] = $value;
            }

            $datasets[] = [
                'label' => $node->name,
                'location' =>
                    ($node->room?->building?->name ?? '-') .
                    ' • ' .
                    ($node->room?->name ?? '-'),

                'data' => $data,

                'borderColor' => $color,
                'backgroundColor' => $color.'20',

                'fill' => false,
                'tension' => 0.4,
                'pointRadius' => 4,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
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
                this.buildSensorChart();
                this.buildSensorDetailChart();
            });
        },
        destroyAll() {
            Object.values(this.charts).forEach(c => { try { c.destroy(); } catch(e){} });
            this.charts = {};
        },
        isDark() {
            return document.documentElement.classList.contains('dark');
        },
        gridColor() { return this.isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)'; },
        labelColor(){ return this.isDark() ? '#a1a1aa' : '#71717a'; },

        buildTypeChart() {
            const el = document.getElementById('chartNodeType');
            if (!el) return;
            const data = {{ Js::from($this->nodeTypeChartData) }};
            this.charts.type = new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: ['#3b82f6','#f59e0b','#ef4444','#22c55e'],
                        borderWidth: 2,
                        borderColor: this.isDark() ? '#18181b' : '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: this.labelColor(), padding: 14, font: { size: 11 } } },
                        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} node` } }
                    },
                    cutout: '62%',
                }
            });
        },

        buildBuildingChart() {
            const el = document.getElementById('chartBuilding');
            if (!el) return;
            const data = {{ Js::from($this->buildingChartData) }};
            this.charts.building = new Chart(el, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        { label: 'Aktif',    data: data.active,   backgroundColor: '#3b82f6', borderRadius: 5 },
                        { label: 'Nonaktif', data: data.inactive, backgroundColor: this.isDark() ? '#3f3f46' : '#d4d4d8', borderRadius: 5 },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: this.labelColor(), font: { size: 11 } } } },
                    scales: {
                        x: { stacked: true, ticks: { color: this.labelColor() }, grid: { display: false } },
                        y: { stacked: true, ticks: { color: this.labelColor(), stepSize: 1 }, grid: { color: this.gridColor() }, beginAtZero: true }
                    }
                }
            });
        },

        buildSensorChart() {
            const el = document.getElementById('chartSensorData');
            if (!el) return;
            const rows = {{ Js::from($this->sensorChartData) }};
            if (!rows.length) return;

            // Stacked Bar: setiap tipe sensor jadi satu layer (dataset)
            const types   = ['temperature', 'current', 'voltage', 'light'];
            const labels  = { temperature: 'Suhu (°C)', current: 'Arus (A)', voltage: 'Tegangan (V)', light: 'Cahaya (lux)' };
            const colors  = { temperature: '#3b82f6', current: '#f59e0b', voltage: '#ef4444', light: '#22c55e' };
            const unitMap = { temperature: '°C', current: 'A', voltage: 'V', light: 'lux' };

            // Buat satu dataset per tipe — nilai null jika node bukan tipe itu
            const nodeLabels = rows.map(r => r.label);
            const datasets   = types
                .filter(t => rows.some(r => r.type === t))
                .map(t => ({
                    label: labels[t],
                    data: rows.map(r => r.type === t ? r.value : 0),
                    backgroundColor: colors[t] + 'cc',
                    borderColor: colors[t],
                    borderWidth: 1,
                    borderRadius: 4,
                    stack: 'sensor',
                }));

            this.charts.sensor = new Chart(el, {
                type: 'bar',
                data: { labels: nodeLabels, datasets },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { labels: { color: this.labelColor(), font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    if (ctx.parsed.y === 0) return null;
                                    const t = types.find(t => labels[t] === ctx.dataset.label);
                                    return ` ${ctx.dataset.label}: ${ctx.parsed.y} ${unitMap[t] ?? ''}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { stacked: true, ticks: { color: this.labelColor() }, grid: { display: false } },
                        y: { stacked: true, ticks: { color: this.labelColor() }, grid: { color: this.gridColor() }, beginAtZero: true }
                    }
                }
            });
        }
    }"
    x-init="initCharts()"
>

    <x-header
        title="Dashboard Operator"
        subtitle="Ringkasan status sensor dan kondisi sistem"
        separator
        progress-indicator
    >
        <x-slot:actions>
            <x-button label="Sensor Control" icon="o-bolt"   href="/operator/control" class="btn-primary btn-sm" />
            <x-button label="Live Monitor"   icon="o-signal" href="/operator/monitor" class="btn-outline btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- STAT CARDS --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                    <x-icon name="o-server-stack" class="w-6 h-6 text-zinc-500" />
                </div>
                <div>
                    <p class="text-3xl font-bold">{{ $this->summary['total'] }}</p>
                    <p class="text-sm text-zinc-500">Total Node</p>
                </div>
            </div>
        </x-card>
        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center shrink-0">
                    <x-icon name="o-bolt" class="w-6 h-6 text-blue-500" />
                </div>
                <div>
                    <p class="text-3xl font-bold text-blue-500">{{ $this->summary['active'] }}</p>
                    <p class="text-sm text-zinc-500">Node Aktif</p>
                </div>
            </div>
        </x-card>

        <x-card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                    <x-icon name="o-pause-circle" class="w-6 h-6 text-zinc-400" />
                </div>
                <div>
                    <p class="text-3xl font-bold text-zinc-500">{{ $this->summary['inactive'] }}</p>
                    <p class="text-sm text-zinc-500">Node Nonaktif</p>
                </div>
            </div>
        </x-card>

    </div>

    {{-- ROW 1: Donut + Bar Gedung --}}
    <div class="grid grid-cols-2 gap-4 mb-4">
        <x-card title="Distribusi Tipe Sensor" subtitle="Jumlah node per jenis"
            shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div class="flex justify-center items-center py-2" style="min-height:220px">
                <canvas id="chartNodeType" style="max-height:220px"></canvas>
            </div>
        </x-card>

        <x-card title="Node per Gedung" subtitle="Aktif vs nonaktif per gedung"
            shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
            <div style="min-height:220px">
                <canvas id="chartBuilding" style="max-height:220px"></canvas>
            </div>
        </x-card>
    </div>

    {{-- ROW 2: Chart Data Sensor --}}
    <x-card title="Data Dummy Sensor Aktif" subtitle="Nilai terakhir dari setiap sensor yang aktif"
        shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl mb-4">
        @if($this->summary['active'] > 0)
        <canvas id="chartSensorData" style="max-height:180px"></canvas>
        @else
        <div class="py-10 text-center text-zinc-400">
            <x-icon name="o-chart-bar" class="w-10 h-10 mx-auto mb-2 opacity-20" />
            <p class="text-sm">Aktifkan sensor untuk melihat chart data.</p>
            <a href="/operator/control" class="text-xs text-blue-400 mt-1 inline-block">Buka Sensor Control →</a>
        </div>
        @endif
    </x-card>

    <x-card
        title="Detail Sensor"
        subtitle="Monitoring per sensor"
        shadow
        class="mb-4"
    >

        <div class="mb-4">

            <select
                wire:model.live="selectedSensorType"
                class="select select-bordered"
            >
                <option value="temperature">
                    Sensor Suhu
                </option>

                <option value="current">
                    Sensor Arus
                </option>

                <option value="voltage">
                    Sensor Tegangan
                </option>

                <option value="light">
                    Sensor Cahaya
                </option>
            </select>

        </div>

        <canvas id="chartSensorDetail"></canvas>

    </x-card>

    {{-- ROW 3: Tabel Gedung + Node Aktif --}}
    <div class="grid grid-cols-5 gap-4">
        <div class="col-span-3">
            <x-card title="Status per Gedung" shadow
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl h-full">
                @forelse($this->buildings as $b)
                <div class="flex items-center gap-4 py-3 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                    <div class="w-10 h-10 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                        <x-icon name="o-building-office" class="w-5 h-5 text-zinc-500" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate">{{ $b['name'] }}</p>
                        <p class="text-xs text-zinc-400">{{ $b['rooms'] }} ruangan · {{ $b['total'] }} node</p>
                    </div>
                    @if($b['total'] > 0)
                    <div class="w-28 shrink-0">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-blue-500 font-semibold">{{ $b['active'] }} aktif</span>
                            <span class="text-zinc-400">{{ $b['total'] }}</span>
                        </div>
                        <div class="h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full"
                                 style="width: {{ round($b['active']/$b['total']*100) }}%"></div>
                        </div>
                    </div>
                    @endif
                </div>
                @empty
                <div class="py-8 text-center text-zinc-400 text-sm">Belum ada gedung.</div>
                @endforelse
            </x-card>
        </div>

        <div class="col-span-2">
            <x-card title="Node Aktif" subtitle="Sensor yang sedang berjalan" shadow
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl h-full">
                @forelse($this->activeNodes->take(6) as $node)
                @php $badge = match($node->node_type) {
                    'temperature' => ['Suhu',     'badge-info'],
                    'current'     => ['Arus',     'badge-warning'],
                    'voltage'     => ['Tegangan', 'badge-error'],
                    'light'       => ['Cahaya',   'badge-success'],
                    default       => [$node->node_type, 'badge-ghost'],
                }; @endphp
                <div class="flex items-center gap-3 py-3 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse shrink-0"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200 truncate">{{ $node->name }}</p>
                        <p class="text-[11px] text-zinc-400 truncate">{{ $node->room?->building?->name }} › {{ $node->room?->name }}</p>
                    </div>
                    <x-badge value="{{ $badge[0] }}" class="{{ $badge[1] }} badge-outline badge-sm shrink-0" />
                </div>
                @empty
                <div class="py-8 text-center text-zinc-400">
                    <x-icon name="o-bolt" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                    <p class="text-sm">Belum ada node aktif.</p>
                    <a href="/operator/control" class="text-xs text-blue-400 mt-1 inline-block">Aktifkan sekarang →</a>
                </div>
                @endforelse
            </x-card>
        </div>
    </div>
</div>