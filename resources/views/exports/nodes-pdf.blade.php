<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body  { font-family: sans-serif; font-size: 11px; color: #333; }
        h2    { text-align: center; margin-bottom: 2px; font-size: 14px; }
        p.sub { text-align: center; color: #888; margin: 0 0 16px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th    { background: #16a34a; color: #fff; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f9f9f9; }
        .badge-aktif    { color: #16a34a; font-weight: bold; }
        .badge-nonaktif { color: #6b7280; }
        .mono { font-family: monospace; font-size: 9px; color: #059669; }
    </style>
</head>
<body>
    <h2>LAPORAN NODE INVENTORY</h2>
    <p class="sub">Tanggal Cetak: {{ $printedAt }} &nbsp;|&nbsp; Total: {{ $nodes->count() }} node</p>
    <table>
        <thead>
            <tr>
                <th>No</th><th>Nama Node</th><th>Tipe</th><th>Gedung</th>
                <th>Ruangan</th><th>MQTT Topic</th><th>Broker</th><th>Status</th><th>Didaftarkan</th>
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
                <td class="mono">{{ $node->mqtt_topic }}</td>
                <td class="mono">{{ ($node->config['broker'] ?? '-') }}:{{ $node->config['port'] ?? 1883 }}</td>
                <td class="{{ $node->status ? 'badge-aktif' : 'badge-nonaktif' }}">
                    {{ $node->status ? 'Aktif' : 'Nonaktif' }}
                </td>
                <td>{{ $node->created_at->format('d/m/Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>