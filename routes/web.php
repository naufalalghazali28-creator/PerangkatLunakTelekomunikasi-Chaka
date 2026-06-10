<?php

use Illuminate\Support\Facades\Route;

// ─── PUBLIC ───────────────────────────────────────
Route::livewire('/', 'pages::auth.login')->name('login');

// ─── PROTECTED ────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // ADMIN
    Route::livewire('/admin',                   'pages::admin.idx')->name('admin');
    Route::livewire('/admin/client',            'pages::admin.client.idx')->name('admin.client');
    Route::livewire('/admin/gedung',            'pages::admin.gedung.idx')->name('admin.gedung');
    Route::livewire('/admin/operator',          'pages::admin.operator.idx')->name('admin.operator');
    Route::livewire('/admin/maintenance',       'pages::admin.maintenance.idx')->name('admin.maintenance');
    Route::livewire('/admin/viewer',            'pages::admin.viewer.idx')->name('admin.viewer');

    // CLIENT
    Route::livewire('/client',                  'pages::client.idx')->name('client');

    // MAINTENANCE
    Route::livewire('/maintenance',             'pages::maintenance.dashboard')->name('maintenance.work');
    Route::livewire('/maintenance/register-node','pages::maintenance.register-node')->name('maintenance.register');
    Route::livewire('/maintenance/nodes',       'pages::maintenance.nodes')->name('maintenance.nodes');
    Route::livewire('/maintenance/logs',        'pages::maintenance.logs')->name('maintenance.logs');
    Route::livewire('/maintenance/akun',        'pages::maintenance.akun')->name('maintenance.akun');

    // OPERATOR
    Route::livewire('/operator',                'pages::operator.dashboard')->name('operator.work');
    Route::livewire('/operator/control',        'pages::operator.control')->name('operator.control');
    Route::livewire('/operator/monitor',        'pages::operator.monitor')->name('operator.monitor');
    Route::livewire('/operator/akun',           'pages::operator.akun')->name('operator.akun');

    // VIEWER
    Route::livewire('/viewer',                  'pages::viewer.dashboard')->name('viewer.work');
    Route::livewire('/viewer/gedung',           'pages::viewer.gedung')->name('viewer.gedung');
    Route::livewire('/viewer/sensors',          'pages::viewer.sensors')->name('viewer.sensors');
    Route::livewire('/viewer/akun',             'pages::viewer.akun')->name('viewer.akun');

    Route::get('/viewer/sensors/export-pdf', function () {
        $filters = session('sensor_pdf_filters', [
            'search' => '', 'filterBuilding' => null,
            'filterType' => '', 'filterStatus' => '',
        ]);

        $nodes = \App\Models\BEMS\Node::with(['room.building.client', 'creator', 'latestReading'])
            ->when($filters['search'],       fn($q) => $q->where('name', 'like', "%{$filters['search']}%"))
            ->when($filters['filterType'],   fn($q) => $q->where('node_type', $filters['filterType']))
            ->when($filters['filterStatus'] !== '', fn($q) => $q->where('status', (bool)$filters['filterStatus']))
            ->when($filters['filterBuilding'], fn($q) =>
                $q->whereHas('room', fn($r) => $r->where('building_id', $filters['filterBuilding']))
            )
            ->latest()->get();

        $rows = '';
        foreach ($nodes as $i => $node) {
            $tipe   = match($node->node_type) {
                'temperature'=>'Suhu','current'=>'Arus','voltage'=>'Tegangan','light'=>'Cahaya',default=>$node->node_type
            };
            $status = $node->status ? '<span style="color:#2563eb;font-weight:bold">Aktif</span>' : '<span style="color:#6b7280">Nonaktif</span>';
            $latestValue = $node->latestReading ? ($node->latestReading->payload['value'] ?? '-') : '-';
            $rows  .= '<tr>'
                . '<td>' . ($i+1) . '</td>'
                . '<td>' . e($node->name) . '</td>'
                . '<td>' . $tipe . '</td>'
                . '<td>' . e($node->room?->building?->name ?? '-') . '</td>'
                . '<td>' . e($node->room?->name ?? '-') . '</td>'
                . '<td>' . e($node->room?->building?->client?->name ?? '-') . '</td>'
                . '<td>' . e($node->creator?->name ?? '-') . '</td>'
                . '<td>' . e($node->created_at->format('d/m/Y H:i') ?? '-') . '</td>'
                . '<td>' . e($latestValue) . '</td>'
                . '<td>' . $status . '</td>'
                . '</tr>';
        }

        $html = '
        <style>
            body{font-family:sans-serif;font-size:9.5px;color:#1f2937}
            h2{text-align:center;font-size:13px;margin:0 0 4px}
            .sub{text-align:center;color:#6b7280;font-size:8.5px;margin:0 0 12px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #e5e7eb;padding:4px 6px;text-align:left}
            th{background:#7c3aed;color:#fff;font-size:8.5px;text-transform:uppercase}
            tr:nth-child(even){background:#f9fafb}
        </style>
        <h2>LAPORAN DATA SENSOR — SISTEM BEMS</h2>
        <p class="sub">Dicetak: ' . now()->format('d/m/Y H:i') . ' | Total: ' . count($nodes) . ' sensor</p>
        <table>
            <thead><tr>
                <th>No</th><th>Nama Sensor</th><th>Tipe</th><th>Gedung</th>
                <th>Ruangan</th><th>Client</th><th>Pendaftar</th><th>Tanggal Dibuat</th><th>Data Terkini</th><th>Status</th>
            </tr></thead>
            <tbody>' . $rows . '</tbody>
        </table>';

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download('Laporan_Sensor_' . now()->format('d-m-Y') . '.pdf');

    })->name('viewer.sensors.export-pdf');

    Route::get('/viewer/sensors/export-excel', function () {
        $filters = session('sensor_excel_filters', [
            'search' => '', 'filterBuilding' => null,
            'filterType' => '', 'filterStatus' => '',
        ]);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new class($filters) implements
                \Maatwebsite\Excel\Concerns\FromCollection,
                \Maatwebsite\Excel\Concerns\WithHeadings,
                \Maatwebsite\Excel\Concerns\WithMapping,
                \Maatwebsite\Excel\Concerns\ShouldAutoSize
            {
                public function __construct(private array $filters) {}

                public function collection()
                {
                    return \App\Models\BEMS\Node::with(['room.building.client', 'creator'])
                        ->when($this->filters['search'],      fn($q) => $q->where('name', 'like', "%{$this->filters['search']}%"))
                        ->when($this->filters['filterType'],  fn($q) => $q->where('node_type', $this->filters['filterType']))
                        ->when($this->filters['filterStatus'] !== '', fn($q) => $q->where('status', (bool)$this->filters['filterStatus']))
                        ->when($this->filters['filterBuilding'], fn($q) =>
                            $q->whereHas('room', fn($r) => $r->where('building_id', $this->filters['filterBuilding']))
                        )
                        ->latest()->get();
                }

                public function map($node): array
                {
                    static $no = 0; $no++;
                    return [
                        $no,
                        $node->name,
                        match($node->node_type) {
                            'temperature'=>'Suhu','current'=>'Arus','voltage'=>'Tegangan','light'=>'Cahaya',default=>$node->node_type
                        },
                        $node->room?->building?->name ?? '-',
                        $node->room?->name ?? '-',
                        $node->room?->building?->client?->name ?? '-',
                        $node->creator?->name ?? '-',
                        $node->status ? 'Aktif' : 'Nonaktif',
                    ];
                }

                public function headings(): array
                {
                    return ['No','Nama Sensor','Tipe','Gedung','Ruangan','Client','Pendaftar','Status'];
                }
            },
            'Data_Sensor_' . now()->format('d-m-Y') . '.xlsx'
        );
    })->name('viewer.sensors.export-excel');
});