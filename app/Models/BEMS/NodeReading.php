<?php

namespace App\Models\BEMS;

use Illuminate\Database\Eloquent\Model;

class NodeReading extends Model
{
    public $timestamps = false;

    protected $table    = 'bems_node_readings';
    protected $fillable = ['node_id', 'payload', 'read_at'];
    protected $casts    = ['payload' => 'array', 'read_at' => 'datetime'];

    public function node()
    {
        return $this->belongsTo(Node::class, 'node_id');
    }
}
