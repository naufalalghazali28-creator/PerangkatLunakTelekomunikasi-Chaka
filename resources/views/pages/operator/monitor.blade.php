<?php

use Livewire\Component;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use App\Services\MqttService;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public array  $liveData      = [];
    public ?int   $filterBuilding = null;
    public string $filterType    = '';

    public array $nodeTypes = [
        ['id' => 'temperature', 'name' => 'Suhu & Kelembaban'],
        ['id' => 'current',     'name' => 'Arus Listrik'],
        ['id' => 'voltage',     'name' => 'Tegangan'],
        ['id' => 'light',       'name' => 'Cahaya'],
    ];

    public function mount(): void
    {
        // Load dummy data untuk semua node aktif saat pertama kali
        $this->refreshAll();
    }

    #[Computed]
    public function activeNodes()
    {
        return Node::with('room.building')
            ->where('status', true)
            ->when($this->filterType, fn($q) => $q->where('node_type', $this->filterType))
            ->when($this->filterBuilding, fn($q) =>
                $q->whereHas('room', fn($r) => $r->where('building_id', $this->filterBuilding))
            )
            ->get();
    }

    #[Computed]
    public function buildings()
    {
        return Building::orderBy('name')->get()
            ->groupBy(fn($b) => strtolower(trim($b->name)))
            ->map(fn($g) => ['id' => $g->first()->id, 'name' => trim($g->first()->name)])
            ->values()->toArray();
    }

    public function refreshAll(): void
    {
        $mqtt = new MqttService();
        foreach (Node::where('status', true)->get() as $node) {
            $this->liveData[$node->id] = $mqtt->generateDummyPayload($node->node_type);
        }
        $this->success('Data di-refresh.');
        unset($this->activeNodes);
    }

    public function refreshOne(int $id): void
    {
        $node = Node::findOrFail($id);
        $mqtt = new MqttService();
        $this->liveData[$id] = $mqtt->generateDummyPayload($node->node_type);
    }
}; ?>

<div>
    <x-header
        title="Live Monitor"
        subtitle="Data real-time dari semua sensor yang aktif"
        separator
        progress-indicator
    >
        <x-slot:actions>
            <x-button label="Refresh Semua" icon="o-arrow-path" wire:click="refreshAll" spinner="refreshAll" class="btn-outline btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Filter --}}
    <div class="flex gap-3 mb-4 flex-wrap">
        <x-select wire:model.live="filterBuilding" :options="$this->buildings" placeholder="Semua Gedung" class="w-44" />
        <x-select wire:model.live="filterType"     :options="$nodeTypes"       placeholder="Semua Tipe"   class="w-40" />
    </div>

    @if($this->activeNodes->isEmpty())
    <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
        <div class="py-16 text-center text-zinc-400">
            <x-icon name="o-signal" class="w-12 h-12 mx-auto mb-3 opacity-20" />
            <p class="text-sm font-medium">Tidak ada sensor aktif.</p>
            <a href="/operator/control" class="text-xs text-blue-400 mt-1 inline-block">Aktifkan sensor di Sensor Control →</a>
        </div>
    </x-card>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($this->activeNodes as $node)
        @php
            $d     = $liveData[$node->id] ?? null;
            $badge = match($node->node_type) {
                'temperature' => ['Suhu',     'badge-info',    'border-blue-500/20'],
                'current'     => ['Arus',     'badge-warning', 'border-yellow-500/20'],
                'voltage'     => ['Tegangan', 'badge-error',   'border-red-500/20'],
                'light'       => ['Cahaya',   'badge-success', 'border-green-500/20'],
                default       => [$node->node_type, 'badge-ghost', 'border-zinc-700'],
            };
        @endphp
        <x-card shadow class="bg-white dark:bg-zinc-900 border {{ $badge[2] }} rounded-2xl">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse shrink-0"></span>
                        <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate">{{ $node->name }}</p>
                    </div>
                    <p class="text-xs text-zinc-400 ml-4 truncate">{{ $node->room?->building?->name }} › {{ $node->room?->name }}</p>
                </div>
                <x-badge value="{{ $badge[0] }}" class="{{ $badge[1] }} badge-outline badge-sm shrink-0 ml-2" />
            </div>

            {{-- Data --}}
            @if($d)
            <div class="space-y-2 mb-3">
                @foreach($d as $key => $val)
                @if(!in_array($key, ['timestamp', 'unit_suhu', 'unit_humid']))
                <div class="flex justify-between items-center p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800">
                    <span class="text-xs text-zinc-400 capitalize">{{ str_replace('_', ' ', $key) }}</span>
                    <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200 font-mono">
                        {{ $val }}
                        @if($key === 'suhu') °C
                        @elseif($key === 'kelembaban') %
                        @elseif($key === 'arus') A
                        @elseif($key === 'tegangan') V
                        @elseif($key === 'cahaya') lux
                        @endif
                    </span>
                </div>
                @endif
                @endforeach
            </div>
            @else
            <div class="py-4 text-center text-zinc-400 text-xs">Klik refresh untuk memuat data</div>
            @endif

            <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <p class="text-[10px] text-zinc-400 font-mono">{{ $node->mqtt_topic }}</p>
                <x-button icon="o-arrow-path" wire:click="refreshOne({{ $node->id }})" spinner="refreshOne({{ $node->id }})" class="btn-ghost btn-xs" />
            </div>
        </x-card>
        @endforeach
    </div>
    @endif
</div>