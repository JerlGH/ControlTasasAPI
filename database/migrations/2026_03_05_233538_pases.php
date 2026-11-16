<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pases', function (Blueprint $table) {
            $table->id();
            // Cada fila conserva el movimiento declarado por una caja.
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_usuario')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_caja_contraparte')->nullable()
                ->constrained('cajas')->restrictOnDelete();
            $table->foreignId('id_pase_relacionado')->nullable()->unique()
                ->constrained('pases');
            $table->string('estado', 30)->default('registrado');
            $table->enum('tipo', ['Entrada', 'Salida']);
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 18, 2);
            $table->enum('medio', ['efectivo', 'cheque', 'transferencia', 'mixto'])->nullable();
            $table->string('detalle')->nullable()->comment('Nombre declarado de la otra caja, por ejemplo Caja2');
            $table->string('referencia', 100)->nullable();
            $table->dateTime('fecha_operacion')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['id_arqueo', 'tipo', 'moneda']);
            $table->index('referencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pases');
    }
};
