<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabel untuk menyimpan daftar Gedung milik Client
        Schema::create('bems_buildings', function (Blueprint $table) {
            $table->id();
            // Menghubungkan ke tabel bems_clients yang sudah kita buat sebelumnya
            $table->foreignId('client_id')->constrained('bems_clients')->onDelete('cascade');
            $table->string('name'); 
            $table->timestamps();
        });

        // 2. Tabel untuk menyimpan daftar Ruangan di dalam Gedung tersebut
        Schema::create('bems_rooms', function (Blueprint $table) {
            $table->id();
            // Menghubungkan ke tabel bems_buildings (Gedung)
            $table->foreignId('building_id')->constrained('bems_buildings')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bems_rooms');
        Schema::dropIfExists('bems_buildings');
    }
};