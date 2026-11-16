<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_arqueo_madre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo_madre')->constrained('arqueo_madre')->restrictOnDelete();
            $table->foreignId('id_usuario')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_arqueo')->nullable()->constrained('arqueo')->restrictOnDelete();
            $table->enum('estado_fuente', ['pendiente', 'abierto', 'cerrado', 'anulado']);
            $table->json('datos')->nullable();
            $table->string('huella', 64)->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['id_arqueo_madre', 'id_usuario'], 'fin_1_u0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_arqueo_madre');
    }
};
