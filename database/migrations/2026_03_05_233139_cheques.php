<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            // Jornada donde se registra por primera vez la operacion del cheque.
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_cliente')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->string('estado', 30)->default('registrado');
            $table->enum('moneda', ['NIO', 'USD']);
            $table->string('numero_cheque', 100)->nullable();
            $table->string('banco', 100)->nullable();
            // El Excel contiene filas con ganancia sin principal conocido. No inventar un cero.
            $table->decimal('monto', 18, 2)->nullable();
            $table->decimal('ganancia', 18, 2)->default(0);
            $table->enum('moneda_ganancia', ['NIO', 'USD'])->nullable();
            $table->decimal('efectivo_entregado', 18, 2)->nullable();
            $table->dateTime('fecha_operacion')->nullable();
            $table->dateTime('fecha_liquidacion')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['id_arqueo', 'moneda']);
            $table->index(['banco', 'numero_cheque']);
            $table->unique(['id', 'moneda'], 'cheque_identidad_moneda_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
