<?php

namespace App\Models\BEMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Client extends Model
{
    protected $table    = 'bems_clients';
    protected $guarded  = [];
    protected $fillable = [
        'code',
        'name',
        'user_id',
        'expirity',
        'remain',
    ];

    protected $casts = [
        'expirity' => 'date',
    ];

    // FIX: tambahkan return yang hilang
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}