<?php

namespace App\Models\BEMS;

use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    protected $table = 'bems_buildings'; 

    protected $fillable = ['client_id', 'name'];

    public function rooms() {
        return $this->hasMany(Room::class, 'building_id');
    }

    public function client() {
        return $this->belongsTo(Client::class, 'client_id');
    }
}