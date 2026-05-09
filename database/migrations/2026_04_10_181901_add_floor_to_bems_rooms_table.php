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
        Schema::table('bems_rooms', function (Blueprint $table) {
            // Menambahkan kolom floor setelah kolom name
            $table->string('floor')->default('1')->after('name'); 
        });
    }

    public function down(): void
    {
        Schema::table('bems_rooms', function (Blueprint $table) {
            $table->dropColumn('floor');
        });
    }
};
