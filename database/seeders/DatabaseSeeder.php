<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        Role::create(['name' => 'admin']);

        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@bems.id',
            'password' => Hash::make('abcdef'),
        ]);

        $user->assignRole('admin');
    }
}
