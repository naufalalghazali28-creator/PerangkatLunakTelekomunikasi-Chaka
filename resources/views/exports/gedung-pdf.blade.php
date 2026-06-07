<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body  { font-family: sans-serif; font-size: 10px; color: #333; }
        h2    { text-align: center; font-size: 13px; margin-bottom: 2px; }
        p.sub { text-align: center; color: #888; margin: 0 0 10px; font-size: 9px; }
        .stats { display: flex; gap: 20px; margin-bottom: 12px; }
        .stat  { flex: 1; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 12px; text-align: center; }
        .stat .val { font-size: 20px; font-weight: bold; }
        .stat .lbl { font-size: 9px; color: #6b7280; }
        .charts { text-align: center; margin-bottom: 12px; }
        .charts img { width: 30%; display: inline-block; margin: 0 1%; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 5px 7px; text-align: left; }
        th    { background: #16a34a; color: #fff; font-size: 9px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f9fafb; }
        .blue { color: #2563eb; font-weight: bold; }
    </style>
</head>
<body>
    <h2>LAPORAN MANAJEMEN GEDUNG & SENSOR</h2>
    <p class="sub">Tanggal Cetak: {{ $printedAt }}</p>

    <div class="stats">
        <div class="stat"><div class="val">{{ $summary['total'] }}</div><div class="lbl">Total Node</div></div>
        <div class="stat"><div class="val" style="color:#2563eb">{{ $summary['active'] }}</div><div class="lbl">Node Aktif</div></div>
        <div class="stat"><div class="val" style="color:#6b7280">{{ $summary['inactive'] }}</div><div class="lbl">Node Nonaktif</div></div>
        <div class="stat"><div class="val">{{ $rooms->count() }}</div><div class="lbl">Total Ruangan</div></div>
    </div>

    @if($chartImg1 || $chartImg2 || $chartImg3)
    <div class="charts">
        @if($chartImg1) <img src="{{ $chartImg1 }}" /> @endif
        @if($chartImg2) <img src="{{ $chartImg2 }}" /> @endif
        @if($chartImg3) <img src="{{ $chartImg3 }}" /> @endif
    </div>
    @endif

    <table>
        <thead>
            <tr><th>No</th><th>Gedung</th><th>Lantai</th><th>Ruangan</th><th>Client</th><th>Total Node</th><th>Node Aktif</th></tr>
        </thead>
        <tbody>
            @foreach($rooms as $i => $room)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $room->building->name ?? '-' }}</td>
                <td>{{ $room->floor }}</td>
                <td>{{ $room->name }}</td>
                <td>{{ $room->building->client->name ?? '-' }}</td>
                <td>{{ $room->nodes_count }}</td>
                <td class="blue">{{ $room->active_nodes_count }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>