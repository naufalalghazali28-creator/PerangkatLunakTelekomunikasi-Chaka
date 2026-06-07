<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body  { font-family: sans-serif; font-size: 10px; color: #333; }
        h2    { text-align: center; margin-bottom: 2px; font-size: 13px; }
        p.sub { text-align: center; color: #888; margin: 0 0 12px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 5px 7px; text-align: left; }
        th    { background: #2563eb; color: #fff; font-size: 9px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f8fafc; }
        .aktif    { color: #2563eb; font-weight: bold; }
        .nonaktif { color: #6b7280; }
        .mono { font-family: monospace; font-size: 8px; color: #059669; }
    </style>
</head>
<body>
    <h2>LAPORAN SENSOR CONTROL</h2>
    <p class="sub">Tanggal Cetak: {{ $printedAt }} &nbsp;|&nbsp; Total: {{ $nodes->count() }} node</p>
    <table>
        <thead>
            <tr>
                <th>No</th><th>Nama Node</th><th>Tipe</th><th>Gedung</th>
                <th>Ruangan</th><th>Pendaftar</th><th>MQTT Topic</th><th>Status</th><th>Didaftarkan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($nodes as $i => $node)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $node->name }}</td>
                <td>{{ ucfirst($node->node_type) }}</td>
                <td>{{ $node->room?->building?->name ?? '-' }}</td>
                <td>{{ $node->room?->name ?? '-' }}</td>
                <td>{{ $node->creator?->name ?? '-' }}<br><span style="color:#6b7280;font-size:8px">{{ $node->creator?->email ?? '' }}</span></td>
                <td class="mono">{{ $node->mqtt_topic }}</td>
                <td class="{{ $node->status ? 'aktif' : 'nonaktif' }}">{{ $node->status ? 'Aktif' : 'Nonaktif' }}</td>
                <td>{{ $node->created_at->format('d/m/Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>