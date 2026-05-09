<?php

namespace App\Models\BEMS;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'bems_rooms'; 

    protected $fillable = ['building_id', 'name', 'floor'];

    public function building() {
        return $this->belongsTo(Building::class, 'building_id');
    }
}