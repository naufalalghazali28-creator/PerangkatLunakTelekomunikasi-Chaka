<?php

use Livewire\Component;
use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use App\Models\BEMS\Room;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public ?int   $selectedBuilding = null;
    public ?int   $selectedRoom     = null;
    public string $nodeName         = '';
    public string $nodeType         = 'temperature';
    public string $mqttTopic        = '';
    public string $mqttBroker       = 'broker.emqx.io';
    public int    $mqttPort         = 1883;
    public string $mqttUsername     = '';
    public string $mqttPassword     = '';

    // Auto-generate MQTT topic
    public function updatedNodeName(): void       { $this->generateTopic(); }
    public function updatedNodeType(): void       { $this->generateTopic(); }
    public function updatedSelectedRoom(): void   { $this->generateTopic(); }
    public function updatedSelectedBuilding(): void
    {
        $this->selectedRoom = null;
        $this->mqttTopic    = '';
    }

    private function generateTopic(): void
    {
        if ($this->nodeName && $this->nodeType && $this->selectedRoom) {
            $slug            = Str::slug($this->nodeName);
            $this->mqttTopic = "chaka/{$this->nodeType}/room{$this->selectedRoom}/{$slug}";
        }
    }

    #[Computed]
    public function buildings()
    {
        // Group by nama gedung agar tidak duplikat
        return Building::orderBy('name')->get()
            ->groupBy(fn($b) => strtolower(trim($b->name)))
            ->map(fn($group) => [
                'id'   => $group->first()->id,
                'name' => trim($group->first()->name),
            ])
            ->values()
            ->toArray();
    }

    #[Computed]
    public function rooms()
    {
        if (!$this->selectedBuilding) return [];

        // Ambil semua building dengan nama yang sama (duplikat), lalu ambil semua room-nya
        $buildingName = Building::find($this->selectedBuilding)?->name;
        $buildingIds  = Building::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($buildingName ?? ''))])->pluck('id');

        return Room::whereIn('building_id', $buildingIds)
            ->orderBy('floor')->orderBy('name')
            ->get()
            ->map(fn($r) => ['id' => $r->id, 'name' => "Lt.{$r->floor} — {$r->name}"])
            ->toArray();
    }

    public array $nodeTypes = [
        ['id' => 'temperature', 'name' => '🌡️  Suhu & Kelembaban'],
        ['id' => 'current',     'name' => '⚡  Arus Listrik'],
        ['id' => 'voltage',     'name' => '🔋  Tegangan Listrik'],
        ['id' => 'light',       'name' => '💡  Intensitas Cahaya'],
    ];

    private function defaultUnit(string $type): string
    {
        return match($type) {
            'temperature' => '°C',
            'current'     => 'A',
            'voltage'     => 'V',
            'light'       => 'lux',
            default       => '-',
        };
    }

    public function save(): void
    {
        $this->validate([
            'selectedBuilding' => 'required|exists:bems_buildings,id',
            'selectedRoom'     => 'required|exists:bems_rooms,id',
            'nodeName'         => 'required|min:3|max:100',
            'nodeType'         => 'required|in:temperature,current,voltage,light',
            'mqttTopic'        => 'required|unique:bems_nodes,mqtt_topic',
            'mqttBroker'       => 'required',
            'mqttPort'         => 'required|integer|min:1|max:65535',
        ], [
            'selectedBuilding.required' => 'Pilih gedung terlebih dahulu.',
            'selectedRoom.required'     => 'Pilih ruangan terlebih dahulu.',
            'nodeName.required'         => 'Nama node wajib diisi.',
            'nodeName.min'              => 'Nama node minimal 3 karakter.',
            'mqttTopic.unique'          => 'MQTT topic ini sudah digunakan node lain.',
            'mqttBroker.required'       => 'Broker MQTT wajib diisi.',
        ]);

        Node::create([
            'room_id'    => $this->selectedRoom,
            'name'       => $this->nodeName,
            'node_type'  => $this->nodeType,
            'mqtt_topic' => $this->mqttTopic,
            'status'     => false, // default nonaktif — operator yang aktifkan
            'config'     => [
                'unit'     => $this->defaultUnit($this->nodeType),
                'broker'   => $this->mqttBroker,
                'port'     => $this->mqttPort,
                'username' => $this->mqttUsername ?: null,
                'password' => $this->mqttPassword ?: null,
            ],
            'created_by' => Auth::id(),
        ]);

        $this->success("Node '{$this->nodeName}' berhasil didaftarkan! Menunggu aktivasi oleh Operator.");
        $this->reset(['selectedBuilding', 'selectedRoom', 'nodeName', 'mqttTopic', 'mqttUsername', 'mqttPassword']);
        $this->nodeType   = 'temperature';
        $this->mqttBroker = 'broker.emqx.io';
        $this->mqttPort   = 1883;
    }
}; ?>

<div>
    <x-header
        title="Daftarkan Node"
        subtitle="Tambahkan node sensor baru ke ruangan yang tersedia"
        separator
        progress-indicator
    >
        <x-slot:actions>
            <x-button label="Node Inventory" icon="o-server-stack" href="/maintenance/nodes" class="btn-outline btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-5 gap-6">

        {{-- FORM --}}
        <div class="col-span-3">
            <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">

                {{-- Section: Lokasi --}}
                <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-3">📍 Lokasi Pemasangan</p>
                <div class="space-y-4 mb-6">
                    <x-select
                        label="Gedung"
                        wire:model.live="selectedBuilding"
                        :options="$this->buildings"
                        placeholder="— Pilih Gedung —"
                        icon="o-building-office"
                    />
                    <x-select
                        label="Ruangan"
                        wire:model.live="selectedRoom"
                        :options="$this->rooms"
                        placeholder="{{ $selectedBuilding ? '— Pilih Ruangan —' : '— Pilih gedung dulu —' }}"
                        icon="o-map-pin"
                        :disabled="!$selectedBuilding"
                    />
                </div>

                {{-- Section: Info Node --}}
                <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-3">🔌 Informasi Node</p>
                <div class="space-y-4 mb-6">
                    <x-input
                        label="Nama Node"
                        wire:model.live="nodeName"
                        placeholder="Cth: Sensor Suhu AC Ruang Server"
                        icon="o-cpu-chip"
                        hint="Nama unik yang mudah dikenali teknisi"
                    />
                    <x-select
                        label="Tipe Sensor"
                        wire:model.live="nodeType"
                        :options="$nodeTypes"
                        icon="o-tag"
                    />
                </div>

                {{-- Section: Konfigurasi MQTT --}}
                <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-3">📡 Konfigurasi MQTT</p>
                <div class="space-y-4">
                    <x-input
                        label="MQTT Topic"
                        wire:model="mqttTopic"
                        icon="o-signal"
                        class="font-mono text-sm"
                        hint="Auto-generate dari nama, tipe, dan ruangan"
                        readonly
                    />
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <x-input
                                label="Broker"
                                wire:model="mqttBroker"
                                placeholder="broker.emqx.io"
                                icon="o-server"
                            />
                        </div>
                        <div>
                            <x-input
                                label="Port"
                                wire:model="mqttPort"
                                type="number"
                                placeholder="1883"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-input
                            label="Username (opsional)"
                            wire:model="mqttUsername"
                            placeholder="mqtt_user"
                            icon="o-user"
                        />
                        <x-input
                            label="Password (opsional)"
                            wire:model="mqttPassword"
                            type="password"
                            placeholder="••••••••"
                            icon="o-lock-closed"
                        />
                    </div>
                </div>

                <x-slot:actions>
                    <x-button label="Reset" wire:click="$refresh" class="btn-ghost" />
                    <x-button
                        label="Daftarkan Node"
                        icon="o-plus"
                        wire:click="save"
                        spinner="save"
                        class="btn-primary"
                    />
                </x-slot:actions>
            </x-card>
        </div>

        {{-- PREVIEW & INFO --}}
        <div class="col-span-2 space-y-4">

            {{-- Preview box --}}
            <x-card shadow class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
                <p class="text-xs font-semibold text-zinc-400 uppercase tracking-widest mb-4">Preview Node</p>

                @if($nodeName && $selectedRoom)
                <div class="space-y-3">
                    @foreach([
                        ['label' => 'Nama',       'value' => $nodeName],
                        ['label' => 'Tipe',        'value' => $nodeType],
                        ['label' => 'MQTT Topic',  'value' => $mqttTopic,   'mono' => true],
                        ['label' => 'Broker',      'value' => $mqttBroker . ':' . $mqttPort, 'mono' => true],
                        ['label' => 'Status Awal', 'value' => 'Nonaktif'],
                    ] as $row)
                    <div class="flex justify-between items-start gap-2 text-sm">
                        <span class="text-zinc-400 shrink-0">{{ $row['label'] }}</span>
                        <span class="{{ ($row['mono'] ?? false) ? 'font-mono text-xs text-green-500' : 'font-medium text-zinc-800 dark:text-zinc-200' }} text-right break-all">
                            {{ $row['value'] }}
                        </span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="py-8 text-center text-zinc-400">
                    <x-icon name="o-cpu-chip" class="w-10 h-10 mx-auto mb-2 opacity-20" />
                    <p class="text-sm">Isi form untuk melihat preview</p>
                </div>
                @endif
            </x-card>

            {{-- Info box --}}
            <x-card shadow class="bg-blue-500/5 border border-blue-500/20 rounded-2xl">
                <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest mb-3">ℹ️ Informasi</p>
                <ul class="space-y-2 text-xs text-zinc-400">
                    <li class="flex gap-2"><span class="text-blue-400 shrink-0">•</span> Node yang baru didaftarkan statusnya <span class="text-white font-medium">Nonaktif</span> secara default.</li>
                    <li class="flex gap-2"><span class="text-blue-400 shrink-0">•</span> Pengaktifan dilakukan oleh <span class="text-white font-medium">Operator</span> melalui halaman Sensor Control.</li>
                    <li class="flex gap-2"><span class="text-blue-400 shrink-0">•</span> MQTT Topic di-generate otomatis dan bersifat unik per node.</li>
                    <li class="flex gap-2"><span class="text-blue-400 shrink-0">•</span> Konfigurasi broker disimpan di kolom <code class="text-green-400">config</code> dalam format JSON.</li>
                </ul>
            </x-card>

        </div>
    </div>
</div>