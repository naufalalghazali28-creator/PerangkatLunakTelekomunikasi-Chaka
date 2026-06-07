<?php

namespace App\Imports;

use App\Models\BEMS\Node;
use App\Models\BEMS\Building;
use App\Models\BEMS\Room;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class NodeImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public array $errors   = [];
    public int   $imported = 0;

    private array $validTypes = ['temperature', 'current', 'voltage', 'light'];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // +2 karena baris 1 = heading

            // Cari room berdasarkan nama gedung + nama ruangan
            $building = Building::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($row['gedung'] ?? ''))])->first();
            if (!$building) {
                $this->errors[] = "Baris {$rowNum}: Gedung '{$row['gedung']}' tidak ditemukan.";
                continue;
            }

            $room = Room::where('building_id', $building->id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($row['ruangan'] ?? ''))])
                ->first();
            if (!$room) {
                $this->errors[] = "Baris {$rowNum}: Ruangan '{$row['ruangan']}' di gedung '{$row['gedung']}' tidak ditemukan.";
                continue;
            }

            $type = strtolower(trim($row['tipe_sensor'] ?? ''));
            if (!in_array($type, $this->validTypes)) {
                $this->errors[] = "Baris {$rowNum}: Tipe '{$type}' tidak valid. Gunakan: temperature, current, voltage, light.";
                continue;
            }

            $name  = trim($row['nama_node'] ?? '');
            $topic = "chaka/{$type}/room{$room->id}/" . Str::slug($name);

            if (Node::where('mqtt_topic', $topic)->exists()) {
                $this->errors[] = "Baris {$rowNum}: Node '{$name}' sudah terdaftar (topic duplikat).";
                continue;
            }

            Node::create([
                'room_id'    => $room->id,
                'name'       => $name,
                'node_type'  => $type,
                'mqtt_topic' => $topic,
                'status'     => false,
                'config'     => [
                    'unit'   => match($type) {
                        'temperature' => '°C', 'current' => 'A',
                        'voltage'     => 'V',  'light'   => 'lux', default => '-'
                    },
                    'broker' => trim($row['broker'] ?? 'broker.emqx.io'),
                    'port'   => (int) ($row['port'] ?? 1883),
                ],
            ]);

            $this->imported++;
        }
    }
}