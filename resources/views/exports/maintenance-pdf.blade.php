<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body  { font-family: sans-serif; font-size: 12px; color: #333; }
        h2    { text-align: center; margin-bottom: 4px; }
        p     { text-align: center; color: #666; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th    { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #fafafa; }
        .capitalize { text-transform: capitalize; }
    </style>
</head>
<body>
    <h2>LAPORAN DATA MAINTENANCE</h2>
    <p>Tanggal Cetak: {{ $printedAt }}</p>

    <table>
        <thead>
            <tr>
                <th>ID Staff</th>
                <th>Nama</th>
                <th>Email</th>
                <th>Role</th>
                <th>Client Pemilik</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $u)
                <tr>
                    <td>{{ $u->id }}</td>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td class="capitalize">{{ $u->role }}</td>
                    <td>
                        @if($u->clientOwner)
                            {{ $u->clientOwner->name }}
                        @else
                            Global (Admin)
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center; color:#999;">Tidak ada data maintenance.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>