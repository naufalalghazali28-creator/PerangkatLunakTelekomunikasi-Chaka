<?php

namespace App\Models\BEMS;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Node extends Model
{
    protected $table = 'bems_nodes';

    protected $fillable = [
        'room_id',
        'name',
        'node_type',
        'mqtt_topic',
        'config',
        'status',
        'created_by',
        'activated_by',   // ← tambahan
    ];

    protected $casts = [
        'config' => 'array',
        'status' => 'boolean',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    // Shortcut gedung
    public function building()
    {
        return $this->room->building();
    }

    // Siapa yang mendaftarkan node ini
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activator()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function latestReading()
    {
        return $this->hasOne(NodeReading::class)->ofMany('read_at', 'max');
    }
}
