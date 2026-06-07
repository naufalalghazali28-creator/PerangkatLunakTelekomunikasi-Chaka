<?php

namespace App\Models;

use App\Models\ST\Program;
use App\Models\BEMS\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    use HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'parent_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function program()
    {
        return $this->hasOne(Program::class, 'user_id', 'id');
    }

    /**
     * Relasi ke Client melalui parent_id.
     * 
     * Logika:
     * - Staff (maintenance/operator/viewer) punya parent_id
     * - parent_id menunjuk ke user_id milik Client di tabel bems_clients
     * - Jadi: User(parent_id) → bems_clients(user_id)
     */
    public function clientOwner()
    {
        return $this->hasOneThrough(
            Client::class,   // Model tujuan
            User::class,     // Model perantara (parent user)
            'id',            // FK di users (id parent)
            'user_id',       // FK di bems_clients
            'parent_id',     // Local key di users (staff)
            'id'             // Local key di users (parent)
        );
    }
}