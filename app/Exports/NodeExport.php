<?php

namespace App\Exports;

use App\Models\BEMS\Node;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class NodeExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        private string $search       = '',
        private string $filterType   = '',
        private ?int   $filterBuilding = null,
    ) {}

    public function title(): string
    {
        return 'Node Inventory';
    }

    public function query()
    {
        return Node::with('room.building')
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('mqtt_topic', 'like', "%{$this->search}%")
            )
            ->when($this->filterType, fn($q) => $q->where('node_type', $this->filterType))
            ->when($this->filterBuilding, fn($q) =>
                $q->whereHas('room', fn($r) => $r->where('building_id', $this->filterBuilding))
            )
            ->latest();
    }

    public function headings(): array
    {
        return ['No', 'Nama Node', 'Tipe Sensor', 'Gedung', 'Ruangan', 'MQTT Topic', 'Broker', 'Port', 'Status', 'Didaftarkan'];
    }

    public function map($node): array
    {
        static $no = 0;
        $no++;
        return [
            $no,
            $node->name,
            ucfirst($node->node_type),
            $node->room?->building?->name ?? '-',
            $node->room?->name ?? '-',
            $node->mqtt_topic,
            $node->config['broker'] ?? '-',
            $node->config['port']   ?? '-',
            $node->status ? 'Aktif' : 'Nonaktif',
            $node->created_at->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}