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
        Schema::table('bems_nodes', function (Blueprint $table) {
            $table->foreignId('activated_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bems_nodes', function (Blueprint $table) {
            $table->dropForeign(['activated_by']);
            $table->dropColumn('activated_by');
        });
    }
};
