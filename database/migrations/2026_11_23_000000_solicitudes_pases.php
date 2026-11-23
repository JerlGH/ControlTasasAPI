<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_pases', function (Blueprint $table) {
            $table->id();
            $table->uuid('clave')->unique();
            $table->foreignId('id_arqueo_origen')->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_arqueo_destino')->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_usuario_origen')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_usuario_destino')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_pase_salida')->nullable()->unique()->constrained('pases')->restrictOnDelete();
            $table->foreignId('id_pase_entrada')->nullable()->unique()->constrained('pases')->restrictOnDelete();
            $table->string('moneda', 3);
            $table->decimal('monto', 18, 2);
            $table->boolean('entre_subagencias')->default(false);
            $table->string('estado', 20)->default('pendiente');
            $table->string('referencia', 100)->nullable();
            $table->text('observacion')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();
            $table->index(['id_usuario_destino', 'estado']);
            $table->index(['id_arqueo_origen', 'estado']);
            $table->index(['id_arqueo_destino', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_pases');
    }
};
