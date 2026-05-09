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
        Schema::table('users', function (Blueprint $table) {
            // Tambahkan kolom role (untuk membedakan operator, maintenance, dll)
            $table->string('role')->default('viewer')->after('email');
            
            // Tambahkan kolom parent_id (untuk menghubungkan staf ke Client)
            $table->unsignedBigInteger('parent_id')->nullable()->after('role');
            
            // Opsional: Tambahkan index agar pencarian role & parent lebih cepat
            $table->index(['role', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'parent_id']);
        });
    }
};
