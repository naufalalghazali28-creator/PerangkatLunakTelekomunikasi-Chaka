<?php

namespace App\Imports;

use App\Models\BEMS\Client;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class ClientImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (!isset($row['code']) || !isset($row['name'])) {
                continue;
            }

            // Format Expirity Date (Menangani format tanggal Excel atau teks string)
            $expirity = null;
            if (!empty($row['expirity'])) {
                if (is_numeric($row['expirity'])) {
                    $expirity = Date::excelToDateTimeObject($row['expirity'])->format('Y-m-d');
                } else {
                    $expirity = Carbon::parse($row['expirity'])->format('Y-m-d');
                }
            }

            DB::transaction(function () use ($row, $expirity) {
                // Buat atau perbarui User
                $user = User::updateOrCreate(
                    ['email' => $row['code'] . "@bems.id"],
                    ['name' => $row['code'], 'password' => Hash::make($row['code'] . "1809##")]
                );

                // Buat atau perbarui Client
                Client::updateOrCreate(
                    ['code' => $row['code']], // Kode sebagai identifier unik
                    [
                        'name'      => $row['name'],
                        'user_id'   => $user->id,
                        'expirity'  => $expirity ?? now()->addYear(),
                        'building'  => $row['building'] ?? null,
                        'classroom' => $row['classroom'] ?? null,
                    ]
                );
            });
        }
    }

    public function rules(): array
    {
        return [
            'code' => 'required',
            'name' => 'required',
        ];
    }
}