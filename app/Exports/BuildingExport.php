<?php

namespace App\Exports;

use App\Models\BEMS\Building;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BuildingExport implements FromCollection, WithHeadings
{
    protected $search;
    protected $filterClient;

    public function __construct($search = null, $filterClient = null)
    {
        $this->search = $search;
        $this->filterClient = $filterClient;
    }

    public function collection()
    {
        return Building::with([
            'client',
            'rooms.nodes.creator',
            'rooms.nodes.activator'
        ])
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhereHas('client', fn($c) =>
                        $c->where('name', 'like', "%{$this->search}%"))
            )
            ->when($this->filterClient, fn($q) =>
                $q->where('client_id', $this->filterClient)
            )
            ->get()
            ->map(function ($building) {
                $nodeIds = $building->rooms
                    ->flatMap->nodes
                    ->pluck('id');

                $maintenance = \App\Models\BEMS\Node::whereIn('id', $nodeIds)
                    ->with('creator')
                    ->whereNotNull('created_by')
                    ->get()
                    ->pluck('creator.name')
                    ->filter()
                    ->unique()
                    ->implode(', ');

                $operators = \App\Models\BEMS\Node::whereIn('id', $nodeIds)
                    ->with('activator')
                    ->whereNotNull('activated_by')
                    ->get()
                    ->pluck('activator.name')
                    ->filter()
                    ->unique()
                    ->implode(', ');

                return [
                    'Gedung'           => $building->name,
                    'Client'           => $building->client?->name,
                    'Maintenance'      => $maintenance ?: '-',
                    'Operator'         => $operators ?: '-',
                    'Jumlah Ruangan'   => $building->rooms()->count(),
                    'Jumlah Node'      => $building->rooms()
                                                ->withCount('nodes')
                                                ->get()
                                                ->sum('nodes_count'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Gedung',
            'Client',
            'Maintenance',
            'Operator',
            'Jumlah Ruangan',
            'Jumlah Node',
        ];
    }
}