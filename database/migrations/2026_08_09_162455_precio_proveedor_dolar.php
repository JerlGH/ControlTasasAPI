<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precio_proveedor_dolar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->string('estado', 30)->default('activo');
            $table->string('proveedor_actual');
            $table->decimal('precio_dolar', 12, 6);
            $table->dateTime('vigente_desde')->nullable();
            $table->timestamps();

            $table->index(['id_subagencia', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precio_proveedor_dolar');
    }
};
