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
    Schema::table('bems_clients', function (Blueprint $table) {
        $table->string('building')->nullable()->after('name'); // Nama Gedung
        $table->string('classroom')->nullable()->after('building'); // Kelas
        $table->date('expirity')->nullable()->change();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    Schema::table('bems_clients', function (Blueprint $table) {
        $table->dropColumn(['building', 'classroom']);
    });
    }
};
