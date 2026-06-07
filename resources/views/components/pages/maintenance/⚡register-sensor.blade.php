<?php

namespace App\Livewire\Pages\Maintenance;

use Livewire\Component;
use App\Models\BEMS\Building;
use App\Models\BEMS\Room;
use App\Models\BEMS\Node;
use Mary\Traits\Toast;

class RegisterSensor extends Component
{
    use Toast;

    public $building_id;
    public $floor;
    public $room_id;
    public $name;
    public $node_type = 'temperature'; 
    public $mqtt_topic;
    public $unit = ''; 

    public $buildings = [];
    public $floors = [];
    public $rooms = [];

    public function mount()
    {
        // Ubah menjadi array id & name agar x-select MaryUI bisa membaca
        $this->buildings = Building::all()->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->toArray();
    }

    public function updatedBuildingId($value)
    {
        // Ambil lantai unik
        $this->floors = Room::where('building_id', $value)
            ->distinct()
            ->orderBy('floor', 'asc')
            ->get(['floor'])
            ->map(fn($r) => ['id' => $r->floor, 'name' => 'Lantai ' . $r->floor])
            ->toArray();
        
        $this->reset(['floor', 'room_id', 'rooms']);
    }

    public function updatedFloor($value)
    {
        // Ambil ruangan berdasarkan building & lantai
        $this->rooms = Room::where('building_id', $this->building_id)
            ->where('floor', $value)
            ->get(['id', 'name'])
            ->toArray();
        
        $this->reset('room_id');
    }

    public function save()
    {
        $this->validate([
            'room_id' => 'required',
            'name' => 'required|min:3',
            'node_type' => 'required',
            'mqtt_topic' => 'required|unique:bems_nodes,mqtt_topic',
        ]);

        $config = [];
        if (in_array($this->node_type, ['temperature', 'current', 'voltage'])) {
            $config['unit'] = $this->unit;
        }

        Node::create([
            'room_id' => $this->room_id,
            'name' => $this->name,
            'node_type' => $this->node_type,
            'mqtt_topic' => $this->mqtt_topic,
            'config' => json_encode($config), // Pastikan format JSON
            'status' => true
        ]);

        $this->success('Sensor berhasil didaftarkan!');
        $this->reset(['name', 'mqtt_topic', 'unit']);
    }

    public function render()
    {
        return view('livewire.pages.maintenance.register-sensor');
    }
}