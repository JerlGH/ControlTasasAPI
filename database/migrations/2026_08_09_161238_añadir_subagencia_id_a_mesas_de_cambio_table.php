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
        Schema::table('mesas_cambio', function (Blueprint $table) {
            
        $table->unsignedBigInteger('id_subagencia')->nullable()->after('id');
        $table->foreign('id_subagencia')->references('id')->on('subagencias')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mesas_cambio', function (Blueprint $table) {
            $table->dropForeign(['id_subagencia']);
            $table->dropColumn('id_subagencia');
        });
    }
};
