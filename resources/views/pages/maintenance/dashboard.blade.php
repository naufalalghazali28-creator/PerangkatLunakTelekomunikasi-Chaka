<?php

use Livewire\Component;
use App\Models\BEMS\Building;
use App\Models\BEMS\Room;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public bool $sensorModal = false;
    public $editSensorId = null;

    public $selectedBuilding = null;
    public $selectedRoom = null;
    public $sensorName = '';
    public $sensorType = 'suhu';

    // Variabel array untuk menampung simulasi data sensor yang baru saja didaftarkan
    public array $registeredSensors = [];

    public array $sensorHeaders = [
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'name', 'label' => 'Nama Sensor'],
        ['key' => 'type', 'label' => 'Tipe Sensor'],
        ['key' => 'building', 'label' => 'Gedung'],
        ['key' => 'room', 'label' => 'Ruangan'],
        ['key' => 'action', 'label' => 'Aksi'],
    ];

    #[Computed]
    public function statusCount() {
        $counts = ['menyala' => 0, 'mati' => 0, 'trouble' => 0];
        foreach ($this->registeredSensors as $sensor) {
            if ($sensor['status'] == 'Menyala') {
                $counts['menyala']++;
            } elseif ($sensor['status'] == 'Mati') {
                $counts['mati']++;
            } elseif ($sensor['status'] == 'Trouble') {
                $counts['trouble']++;
            }
        }
        return $counts;
    }

    #[Computed]
    public function buildings() {
        // Memperbaiki duplikasi gedung dengan mengabaikan huruf besar/kecil dan spasi
        return Building::all()->groupBy(function($b) {
            return strtolower(trim($b->name));
        })->map(function($group) {
            return [
                'id' => $group->pluck('id')->implode(','), // Gabungkan semua ID jika nama gedungnya sama
                'name' => trim($group->first()->name)
            ];
        })->values()->toArray();
    }

    #[Computed]
    public function rooms() {
        // Mengambil ruangan berdasarkan gedung yang dipilih (mendukung multi ID)
        if ($this->selectedBuilding) {
            $buildingIds = explode(',', $this->selectedBuilding);
            return Room::whereIn('building_id', $buildingIds)->get()->map(function($r) {
                return [
                    'id' => $r->id,
                    'name' => "Lantai {$r->floor} - {$r->name}"
                ];
            })->toArray();
        }
        return [];
    }

    public function updatedSelectedBuilding() {
        // Reset pilihan ruangan setiap kali gedung diganti
        $this->selectedRoom = null;
    }

    public function openAddModal() {
        $this->reset(['editSensorId', 'selectedBuilding', 'selectedRoom', 'sensorName']);
        $this->sensorType = 'suhu';
        $this->sensorModal = true;
    }

    public function editSensor($id) {
        $sensor = collect($this->registeredSensors)->firstWhere('id', $id);
        if ($sensor) {
            $this->editSensorId = $id;
            $this->sensorName = $sensor['name'];
            $this->sensorType = $sensor['type'];
            $this->selectedBuilding = $sensor['building_id'];
            $this->selectedRoom = $sensor['room_id'];
            $this->sensorModal = true;
        }
    }

    public function saveSensor() {
        $this->validate([
            'selectedBuilding' => 'required',
            'selectedRoom' => 'required',
            'sensorName' => 'required|min:3',
            'sensorType' => 'required',
        ]);

        // Dapatkan nama gedung dan ruangan untuk ditampilkan di tabel
        $buildingName = collect($this->buildings())->firstWhere('id', $this->selectedBuilding)['name'] ?? '-';
        $roomName = collect($this->rooms())->firstWhere('id', $this->selectedRoom)['name'] ?? '-';

        if ($this->editSensorId) {
            // Proses pembaruan (Update)
            foreach ($this->registeredSensors as $key => $sensor) {
                if ($sensor['id'] === $this->editSensorId) {
                    $this->registeredSensors[$key]['name'] = $this->sensorName;
                    $this->registeredSensors[$key]['type'] = $this->sensorType;
                    $this->registeredSensors[$key]['building_id'] = $this->selectedBuilding;
                    $this->registeredSensors[$key]['building'] = $buildingName;
                    $this->registeredSensors[$key]['room_id'] = $this->selectedRoom;
                    $this->registeredSensors[$key]['room'] = $roomName;
                    break;
                }
            }
            $this->success("Sensor '{$this->sensorName}' berhasil diperbarui!");
        } else {
            // Proses pembuatan (Create)
            array_unshift($this->registeredSensors, [
                'id' => uniqid(),
                'name' => $this->sensorName,
                'type' => $this->sensorType,
                'building_id' => $this->selectedBuilding,
                'building' => $buildingName,
                'room_id' => $this->selectedRoom,
                'room' => $roomName,
                'status' => 'Mati', // Default status saat pendaftaran (diubah operator nantinya)
            ]);
            $this->success("Sensor '{$this->sensorName}' berhasil didaftarkan di ruangan yang dipilih!");
        }
        
        $this->reset(['sensorModal', 'selectedBuilding', 'selectedRoom', 'sensorName', 'sensorType']);
    }

    public function render() {
        return <<<'HTML'
        <div>
            {{-- Header Halaman --}}
            <x-header title="Dashboard Maintenance" subtitle="Pemantauan Perangkat Keras dan Sensor" separator progress-indicator>
                <x-slot:actions>
                    <x-button label="Daftarkan Sensor Baru" icon="o-plus" class="btn-primary btn-sm" wire:click="openAddModal" />
                </x-slot:actions>
            </x-header>

            {{-- Konten Utama --}}
            <div class="mb-4">
                <x-card title="Status Sensor (Node)" subtitle="Ringkasan kondisi alat" shadow>
                    <div class="flex justify-around items-center text-center mt-2">
                        <div><div class="text-3xl font-bold text-success">{{ $this->statusCount['menyala'] }}</div><div class="text-sm opacity-70">Menyala</div></div>
                        <div><div class="text-3xl font-bold text-error">{{ $this->statusCount['mati'] }}</div><div class="text-sm opacity-70">Mati</div></div>
                        <div><div class="text-3xl font-bold text-warning">{{ $this->statusCount['trouble'] }}</div><div class="text-sm opacity-70">Trouble</div></div>
                    </div>
                </x-card>
            </div>

            {{-- Tabel Daftar Sensor yang Terdaftar (Preview Sementara) --}}
            <div class="mt-6 animate-in slide-in-from-bottom-4 duration-500">
                <x-card title="Sensor Baru Didaftarkan" subtitle="Daftar perangkat keras yang didaftarkan pada sesi ini" shadow separator>
                    @if(count($registeredSensors) > 0)
                        <x-table :headers="$sensorHeaders" :rows="$registeredSensors">
                            @scope('cell_type', $sensor)
                                @if($sensor['type'] == 'suhu')
                                    <x-badge value="Suhu & Kelembaban" class="badge-info badge-outline" icon="o-cloud" />
                                @elseif($sensor['type'] == 'listrik')
                                    <x-badge value="Arus Listrik" class="badge-warning badge-outline" icon="o-bolt" />
                                @elseif($sensor['type'] == 'cahaya')
                                    <x-badge value="Cahaya" class="badge-success badge-outline" icon="o-sun" />
                                @else
                                    <x-badge value="Gerak (PIR)" class="badge-secondary badge-outline" icon="o-arrows-right-left" />
                                @endif
                            @endscope

                            @scope('cell_status', $sensor)
                                <div class="w-4 h-4 rounded-full inline-block {{ $sensor['status'] == 'Menyala' ? 'bg-success' : ($sensor['status'] == 'Trouble' ? 'bg-warning' : 'bg-error') }}" title="Status: {{ $sensor['status'] }}"></div>
                            @endscope
                            
                            @scope('cell_action', $sensor)
                                <x-button icon="o-pencil" wire:click="editSensor('{{ $sensor['id'] }}')" class="btn-ghost btn-sm text-info" />
                            @endscope
                        </x-table>
                    @else
                        <div class="text-center py-8 opacity-50">
                            <x-icon name="o-inbox" class="w-12 h-12 mx-auto mb-2" />
                            <p>Belum ada sensor yang didaftarkan. Silakan klik tombol <b>Daftarkan Sensor Baru</b>.</p>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- Modal Tambah/Edit Sensor --}}
            <x-modal wire:model="sensorModal" title="{{ $editSensorId ? 'Edit Data Sensor' : 'Daftarkan Sensor Baru' }}" separator>
                <div class="space-y-4">
                    <x-select 
                        label="Pilih Gedung" 
                        wire:model.live="selectedBuilding" 
                        :options="$this->buildings" 
                        placeholder="-- Pilih Gedung --" 
                        icon="o-building-office" 
                    />

                    <x-select 
                        label="Pilih Ruangan" 
                        wire:model="selectedRoom" 
                        :options="$this->rooms" 
                        placeholder="-- Pilih Ruangan --" 
                        icon="o-map-pin"
                    />
                    
                    <x-input label="Nama Sensor" wire:model="sensorName" placeholder="Contoh: Sensor Suhu AC 1" icon="o-cpu-chip" />

                    <x-select 
                        label="Tipe Sensor" 
                        wire:model="sensorType" 
                        :options="[['id' => 'suhu', 'name' => 'Sensor Suhu & Kelembaban'], ['id' => 'listrik', 'name' => 'Sensor Arus Listrik'], ['id' => 'cahaya', 'name' => 'Sensor Intensitas Cahaya'], ['id' => 'gerak', 'name' => 'Sensor Gerak (PIR)']]" 
                        icon="o-tag" 
                    />
                </div>
                
                <x-slot:actions>
                    <x-button label="Batal" wire:click="$set('sensorModal', false)" />
                    <x-button label="Simpan" wire:click="saveSensor" class="btn-primary" spinner="saveSensor" />
                </x-slot:actions>
            </x-modal>
        </div>
        HTML;
    }
}; ?>
