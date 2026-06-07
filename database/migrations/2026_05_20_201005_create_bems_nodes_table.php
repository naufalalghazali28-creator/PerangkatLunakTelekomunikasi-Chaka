<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bems_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('bems_rooms');
            $table->string('name');
            $table->string('node_type'); // 'current', 'voltage', 'temperature', 'light'
            $table->string('mqtt_topic')->unique();
            $table->json('config')->nullable();
            $table->boolean('status')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bems_nodes');
    }
};